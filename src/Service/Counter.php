<?php

/**
 * @package     JooAg Shariff
 * @copyright   (C) 2014 - 2026 Joomla Agentur, newtastic GmbH
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace JoomlaAgency\Plugin\System\JooagShariff\Service;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Http\HttpFactory;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Share counts like the former Shariff backend (heiseonline/shariff-backend-php), with Joomla's HTTP client and
 * cache instead of bundled libraries. Only addresses on the allowed domains are counted.
 */
final class Counter
{
    private const AGENT = 'Mozilla/5.0 (compatible; JooAg Shariff; +https://github.com/joomla-agency/plg_jooag_shariff)';

    /**
     * @param   Registry                         $params        Plugin settings
     * @param   string[]                         $hosts         Allowed host names (lower case, without "www.")
     * @param   string[]                         $services      Services shown on the site (lower case)
     * @param   CacheControllerFactoryInterface  $cacheFactory  Joomla's cache
     */
    public function __construct(
        private readonly Registry $params,
        private readonly array $hosts,
        private readonly array $services,
        private readonly CacheControllerFactoryInterface $cacheFactory
    ) {
    }

    /**
     * Counts per service for an address of this site; services without an answer are left out.
     *
     * @return  array<string, int>
     */
    public function counts(string $url): array
    {
        $url = $this->allowedUrl($url);

        if ($url === null) {
            return [];
        }

        $services = array_values(array_intersect(Services::COUNTABLE, $this->services));

        if (!$this->facebookKey()) {
            $services = array_values(array_diff($services, ['facebook']));
        }

        if (!$services) {
            return [];
        }

        $seconds = max(60, (int) $this->params->get('cache_time', 60));
        $cache   = $this->cacheFactory->createCacheController('output', [
            'defaultgroup' => 'plg_jooag_shariff',
            'caching'      => true,
            'lifetime'     => (int) ceil($seconds / 60),
        ]);
        $key    = md5($url . '|' . implode(',', $services));
        $cached = $cache->get($key);

        if (\is_string($cached) && \is_array($data = json_decode($cached, true))) {
            return $data;
        }

        $timeout = min(30, max(1, (int) $this->params->get('client_timeout', 5)));
        $http    = (new HttpFactory())->getHttp(['userAgent' => self::AGENT]);
        $counts  = [];

        foreach ($services as $service) {
            try {
                $count = $this->fetch($http, $service, $url, $timeout);
            } catch (\Throwable) {
                $count = null;
            }

            if ($count !== null) {
                $counts[$service] = $count;
            }
        }

        $cache->store(json_encode($counts), $key);

        return $counts;
    }

    /**
     * The address without fragment, when it is http(s) on one of the allowed hosts; otherwise null.
     */
    private function allowedUrl(string $url): ?string
    {
        $url   = trim($url);
        $parts = parse_url($url);

        if ($url === '' || \strlen($url) > 2048 || !$parts || empty($parts['host'])
            || !\in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }

        $host = preg_replace('/^www\./', '', strtolower($parts['host']));

        if (!\in_array($host, $this->hosts, true)) {
            return null;
        }

        return explode('#', $url, 2)[0];
    }

    private function facebookKey(): string
    {
        foreach ((array) $this->params->get('services', []) as $row) {
            $row = (object) $row;

            if (strtolower((string) ($row->services ?? '')) === 'facebook'
                && trim((string) ($row->special_facebook_app_id ?? '')) !== ''
                && trim((string) ($row->special_facebook_app_secret ?? '')) !== '') {
                return rawurlencode(trim($row->special_facebook_app_id)) . '|' . rawurlencode(trim($row->special_facebook_app_secret));
            }
        }

        return '';
    }

    private function fetch(object $http, string $service, string $url, int $timeout): ?int
    {
        $address = rawurlencode($url);

        switch ($service) {
            case 'buffer':
                $data = $this->json($http->get('https://api.bufferapp.com/1/links/shares.json?url=' . $address, [], $timeout));

                return isset($data['shares']) ? (int) $data['shares'] : null;
            case 'facebook':
                $data = $this->json($http->get('https://graph.facebook.com/v25.0/?id=' . $address
                    . '&fields=og_object%7Bengagement%7D&access_token=' . $this->facebookKey(), [], $timeout));

                return isset($data['og_object']['engagement']['count']) ? (int) $data['og_object']['engagement']['count'] : null;
            case 'pinterest':
                // JSONP: x({...})
                $body = $this->body($http->get('https://api.pinterest.com/v1/urls/count.json?callback=x&url=' . $address, [], $timeout));
                $data = $body !== null && preg_match('/^x\((.*)\)$/s', trim($body), $m) ? json_decode($m[1], true) : null;

                return isset($data['count']) ? (int) $data['count'] : null;
            case 'reddit':
                $data = $this->json($http->get('https://www.reddit.com/api/info.json?url=' . $address, [], $timeout));

                if (!isset($data['data']['children']) || !\is_array($data['data']['children'])) {
                    return null;
                }

                return array_sum(array_map(static fn ($child) => (int) ($child['data']['score'] ?? 0), $data['data']['children']));
            case 'vk':
                // VK.Share.count(1, 42);
                $body = $this->body($http->get('https://vk.com/share.php?act=count&index=1&url=' . $address, [], $timeout));

                return $body !== null && preg_match('/VK\.Share\.count\(\d+,\s*(\d+)\)/', $body, $m) ? (int) $m[1] : null;
            case 'xing':
                $data = $this->json($http->post('https://www.xing-share.com/spi/shares/statistics?url=' . $address, '', [], $timeout));

                return isset($data['share_counter']) ? (int) $data['share_counter'] : null;
        }

        return null;
    }

    private function body(object $response): ?string
    {
        return $response->getStatusCode() === 200 ? (string) $response->getBody() : null;
    }

    private function json(object $response): ?array
    {
        $body = $this->body($response);
        $data = $body === null ? null : json_decode($body, true);

        return \is_array($data) ? $data : null;
    }
}
