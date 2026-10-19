<?php

/**
 * @package     JooAg Shariff
 * @copyright   (C) 2014 - 2026 Joomla Agentur, newtastic GmbH
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace JoomlaAgency\Plugin\System\JooagShariff\Service;

\defined('_JEXEC') or die;

/**
 * The share services: share address, how the link opens and the texts per language.
 * Share addresses and texts follow heiseonline/shariff 3.3.1 (MIT License); Bluesky, Mastodon and Threads are new.
 */
final class Services
{
    /**
     * Services in the order of the settings list. The keys are the lower-case values stored in the "services" setting.
     */
    public const NAMES = [
        'bluesky', 'buffer', 'clipboard', 'diaspora', 'facebook', 'flipboard', 'linkedin', 'mail', 'mastodon',
        'pinterest', 'print', 'qzone', 'reddit', 'telegram', 'threads', 'threema', 'tumblr', 'twitter', 'vk', 'weibo',
        'whatsapp', 'xing', 'info',
    ];

    /**
     * Services of older versions that have been shut down by their providers; saved rows with them are skipped.
     */
    public const RETIRED = ['addthis', 'flattr', 'googleplus', 'pocket', 'stumbleupon', 'tencent-weibo'];

    /**
     * Services whose share count can be fetched (see Counter).
     */
    public const COUNTABLE = ['buffer', 'facebook', 'pinterest', 'reddit', 'vk', 'xing'];

    /**
     * Shariff's info page, used when no article is chosen for the info button (as in all earlier versions).
     */
    public const DEFAULT_INFO_URL = 'https://ct.de/-2467514';

    private static ?array $texts = null;

    /**
     * Text of a service in a language, falling back to English.
     *
     * @param   string  $name  Service
     * @param   string  $kind  'share' (button text) or 'title' (link title)
     * @param   string  $lang  Two-letter language code
     */
    public static function text(string $name, string $kind, string $lang): string
    {
        self::$texts ??= require __DIR__ . '/translations.php';
        $texts = self::$texts[$name][$kind] ?? [];

        return ($texts[$lang] ?? '') !== '' ? $texts[$lang] : ($texts['en'] ?? $name);
    }

    /**
     * The link of a service for a page.
     *
     * @param   string  $name     Service
     * @param   array   $page     url, title, image (absolute address or ''), infoUrl
     * @param   object  $options  The service's row of the "services" setting
     *
     * @return  array  href, popup (small window), blank (new tab), action ('print', 'copy' or '')
     */
    public static function link(string $name, array $page, object $options): array
    {
        $url   = rawurlencode($page['url']);
        $title = rawurlencode($page['title']);
        $text  = rawurlencode(trim($page['title'] . ' ' . $page['url']));
        $link  = ['href' => '', 'popup' => true, 'blank' => false, 'action' => ''];

        switch ($name) {
            case 'bluesky':
                $link['href'] = 'https://bsky.app/intent/compose?text=' . $text;
                break;
            case 'buffer':
                $link['href'] = 'https://buffer.com/add?text=' . $title . '&url=' . $url;
                break;
            case 'clipboard':
                $link = ['href' => $page['url'], 'popup' => false, 'blank' => false, 'action' => 'copy'];
                break;
            case 'diaspora':
                $link['href'] = 'https://share.diasporafoundation.org/?url=' . $url . '&title=' . $title;
                break;
            case 'facebook':
                $link['href'] = 'https://www.facebook.com/sharer/sharer.php?u=' . $url;
                break;
            case 'flipboard':
                $link['href'] = 'https://share.flipboard.com/bookmarklet/popout?v=2&title=' . $title . '&url=' . $url;
                break;
            case 'info':
                $display = (string) ($options->special_data_info_display ?? 'blank');
                $link    = [
                    'href'   => $page['infoUrl'],
                    'popup'  => $display === 'popup',
                    'blank'  => $display !== 'popup' && $display !== 'self',
                    'action' => '',
                ];
                break;
            case 'linkedin':
                $link['href'] = 'https://www.linkedin.com/sharing/share-offsite/?url=' . $url;
                break;
            case 'mail':
                $link = self::mail($page, $options);
                break;
            case 'mastodon':
                // Share₂Fedi asks for the own instance (Mastodon, Misskey, Friendica …); nothing loads before the click.
                $link['href'] = 'https://s2f.kytta.dev/?text=' . $text;
                break;
            case 'pinterest':
                $link['href'] = 'https://www.pinterest.com/pin/create/link/?url=' . $url
                    . ($page['image'] !== '' ? '&media=' . rawurlencode($page['image']) : '') . '&description=' . $title;
                break;
            case 'print':
                $link = ['href' => 'javascript:window.print();', 'popup' => false, 'blank' => false, 'action' => 'print'];
                break;
            case 'qzone':
                $link['href'] = 'https://sns.qzone.qq.com/cgi-bin/qzshare/cgi_qzshare_onekey?url=' . $url . '&title=' . $title;
                break;
            case 'reddit':
                $link['href'] = 'https://www.reddit.com/submit?url=' . $url . '&title=' . $title;
                break;
            case 'telegram':
                $link['href'] = 'https://t.me/share/url?url=' . $url . '&text=' . $title;
                break;
            case 'threads':
                $link['href'] = 'https://www.threads.com/intent/post?text=' . $text;
                break;
            case 'threema':
                $link = ['href' => 'threema://compose?text=' . $text, 'popup' => false, 'blank' => false, 'action' => ''];
                break;
            case 'tumblr':
                $link['href'] = 'https://www.tumblr.com/widgets/share/tool?canonicalUrl=' . $url;
                break;
            case 'twitter':
                $link['href'] = self::post($page, trim((string) ($options->{'data-twitter-via'} ?? ''), " @\t"));
                break;
            case 'vk':
                $link['href'] = 'https://vk.com/share.php?url=' . $url;
                break;
            case 'weibo':
                $link['href'] = 'https://service.weibo.com/share/share.php?url=' . $url . '&title=' . $title;
                break;
            case 'whatsapp':
                $link = ['href' => 'https://api.whatsapp.com/send?text=' . $text, 'popup' => false, 'blank' => true, 'action' => ''];
                break;
            case 'xing':
                $link['href'] = 'https://www.xing.com/spi/shares/new?url=' . $url;
                break;
        }

        return $link;
    }

    /**
     * Post on X: the text is shortened so that text, address and "via" stay within 280 characters.
     */
    private static function post(array $page, string $via): string
    {
        $room  = 280 - mb_strlen($page['url']) - mb_strlen($via) - 6;
        $title = $page['title'];

        if (mb_strlen($title) > $room) {
            $cut   = mb_substr($title, 0, max(0, $room - 1));
            $space = mb_strrpos($cut, ' ');
            $title = ($space ? mb_substr($cut, 0, $space) : $cut) . '…';
        }

        return 'https://x.com/intent/post?url=' . rawurlencode($page['url']) . '&text=' . rawurlencode($title)
            . ($via !== '' ? '&via=' . rawurlencode($via) : '');
    }

    /**
     * Mail: a "mailto:" address gets subject and body ({url} is replaced by the page address); any other address is a
     * form the visitor is sent to.
     */
    private static function mail(array $page, object $options): array
    {
        $target = trim((string) ($options->{'data-mail-url'} ?? ''));

        // Mail, web addresses and addresses on this site only, never other schemes such as javascript:
        if ($target === '' || (preg_match('#^[a-z][a-z0-9+.-]*:#i', $target) && !preg_match('#^(https?|mailto):#i', $target))) {
            $target = 'mailto:';
        }

        if (stripos($target, 'mailto:') === 0) {
            $subject = trim((string) ($options->{'data-mail-subject'} ?? '')) ?: $page['title'];
            $body    = trim((string) ($options->{'data-mail-body'} ?? ''));
            $body    = $body === '' ? $page['url'] : str_ireplace('{url}', $page['url'], $body);
            $glue    = str_contains($target, '?') ? '&' : '?';

            return [
                'href'   => $target . $glue . 'subject=' . rawurlencode($subject) . '&body=' . rawurlencode($body),
                'popup'  => false,
                'blank'  => false,
                'action' => '',
            ];
        }

        return ['href' => $target, 'popup' => false, 'blank' => stripos($target, 'http') === 0, 'action' => ''];
    }
}
