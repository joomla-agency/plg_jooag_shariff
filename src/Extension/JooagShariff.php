<?php

/**
 * @package     JooAg Shariff
 * @copyright   (C) 2014 - 2026 Joomla Agentur, newtastic GmbH
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace JoomlaAgency\Plugin\System\JooagShariff\Extension;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Event\Application\BeforeRenderEvent;
use Joomla\CMS\Event\Content\AfterDisplayEvent;
use Joomla\CMS\Event\Content\BeforeDisplayEvent;
use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Event\Plugin\AjaxEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use JoomlaAgency\Plugin\System\JooagShariff\Service\Counter;
use JoomlaAgency\Plugin\System\JooagShariff\Service\Services;

\defined('_JEXEC') or die;

/**
 * Share buttons without tracking: plain links that only contact a network when the visitor clicks.
 * The markup and CSS classes are those of Heise Shariff, so the look and own CSS of existing sites stay.
 */
final class JooagShariff extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /**
     * {shariff} or {shariff theme=grey orientation=vertical style=icon}
     */
    private const SHORTTAG = '/\{shariff(?:\s+([^}]*))?\}/';

    private const ORIENTATIONS = ['horizontal', 'vertical'];
    private const THEMES       = ['color', 'grey', 'white'];
    private const STYLES       = ['standard', 'icon', 'icon-count'];

    /**
     * Info button addresses by article id (one query per article and request).
     */
    private array $infoUrls = [];

    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepare'       => 'onContentPrepare',
            'onContentBeforeDisplay' => 'onContentBeforeDisplay',
            'onContentAfterDisplay'  => 'onContentAfterDisplay',
            'onBeforeRender'         => 'onBeforeRender',
            'onAjaxJooag_shariff'    => 'onAjax',
        ];
    }

    /**
     * Short tag {shariff …} in articles and modules ("Prepare Content").
     */
    public function onContentPrepare(ContentPrepareEvent $event): void
    {
        $item = $event->getItem();

        if (!\is_object($item) || !isset($item->text) || !\is_string($item->text) || !str_contains($item->text, '{shariff')
            || !$this->isSite() || (int) $this->params->get('com_shorttag', 1) !== 1) {
            return;
        }

        // Excluded component: no buttons, and no raw {shariff} either (e.g. from a module on that page)
        if ($this->isDisallowedComponent()) {
            $item->text = preg_replace(self::SHORTTAG, '', $item->text);

            return;
        }

        $title = $event->getContext() === 'com_content.article' ? ($item->title ?? null) : null;

        $item->text = preg_replace_callback(
            self::SHORTTAG,
            fn (array $match): string => $this->render($this->shortTagOptions($match[1] ?? ''), $title, $item),
            $item->text
        );
    }

    public function onContentBeforeDisplay(BeforeDisplayEvent $event): void
    {
        $html = $this->forContent($event->getContext(), $event->getItem(), $event->getParams(), 'BeforeDisplay');

        if ($html !== '') {
            $event->addResult($html);
        }
    }

    public function onContentAfterDisplay(AfterDisplayEvent $event): void
    {
        $html = $this->forContent($event->getContext(), $event->getItem(), $event->getParams(), 'AfterDisplay');

        if ($html !== '') {
            $event->addResult($html);
        }
    }

    /**
     * "Show everywhere": buttons above and/or below the output of any component.
     */
    public function onBeforeRender(BeforeRenderEvent $event): void
    {
        if (!$this->isSite() || (int) $this->params->get('com_everywhere', 0) !== 1 || $this->isDisallowedComponent()
            || !$this->menuAllowed('com_everywhere')) {
            return;
        }

        $document = $this->getApplication()->getDocument();

        if (!$document instanceof HtmlDocument) {
            return;
        }

        $positions = $this->positions('com_everywhere');
        $before    = \in_array('BeforeDisplay', $positions, true) ? $this->render([]) : '';
        $after     = \in_array('AfterDisplay', $positions, true) ? $this->render([]) : '';

        if ($before !== '' || $after !== '') {
            $options = ['type' => 'component', 'name' => null, 'title' => null];
            $document->setBuffer($before . $document->getBuffer('component') . $after, $options);
        }
    }

    /**
     * Share counts for the buttons: index.php?option=com_ajax&plugin=jooag_shariff&group=system&format=json&url=…
     */
    public function onAjax(AjaxEvent $event): void
    {
        if (!$this->isSite() || (int) $this->params->get('shariff_counter', 0) !== 1) {
            $event->addResult([]);

            return;
        }

        // Only addresses this site has rendered buttons for: the page signs its own address (see render())
        $input = $this->getApplication()->getInput();
        $url   = (string) $input->get('url', '', 'RAW');

        if (!hash_equals($this->signature($url), (string) $input->get('sig', '', 'CMD'))) {
            $event->addResult([]);

            return;
        }

        $counter = new Counter(
            $this->params,
            $this->counterHosts(),
            array_keys($this->serviceRows()),
            Factory::getContainer()->get(CacheControllerFactoryInterface::class)
        );

        $event->addResult($counter->counts($url));
    }

    /**
     * Signature of a page address for the counter, with the site's secret key.
     */
    private function signature(string $url): string
    {
        return substr(hash_hmac('sha256', 'jooag_shariff|' . $url, (string) $this->getApplication()->get('secret')), 0, 32);
    }

    private function forContent(string $context, $item, $params, string $position): string
    {
        // As in all earlier versions: not when the view only shows the intro text.
        $introOnly = $params instanceof Registry ? $params->get('show_introtext') : (\is_array($params) ? ($params['show_introtext'] ?? null) : null);

        if (!empty($introOnly) || !$this->isSite() || $this->isDisallowedComponent()
            || !\in_array($position, $this->positions('com_content'), true)
            || !$this->contentAllowed($context, $item) || !$this->menuAllowed('com_content')) {
            return '';
        }

        return $this->render([], \is_object($item) ? ($item->title ?? null) : null, \is_object($item) ? $item : null);
    }

    private function isSite(): bool
    {
        return $this->getApplication()->isClient('site');
    }

    /**
     * Positions of a place ('com_content' or 'com_everywhere'): BeforeDisplay and/or AfterDisplay.
     * Up to version 7 both places shared the list "output_position"; it applies while a place has no own position.
     */
    private function positions(string $place): array
    {
        $position = (string) $this->params->get($place . '_position', '');

        if ($position === '') {
            return array_map('strval', (array) $this->params->get('output_position', ['BeforeDisplay']));
        }

        return match ($position) {
            'after' => ['AfterDisplay'],
            'both'  => ['BeforeDisplay', 'AfterDisplay'],
            default => ['BeforeDisplay'],
        };
    }

    private function isDisallowedComponent(): bool
    {
        $option = $this->getApplication()->getInput()->getCmd('option', '');

        foreach ((array) $this->params->get('disallow_components', []) as $row) {
            if ($option !== '' && (string) (((object) $row)->disallow_components ?? '') === $option) {
                return true;
            }
        }

        return false;
    }

    private function contentAllowed(string $context, $item): bool
    {
        if ((int) $this->params->get('com_content', 1) !== 1 || $context !== 'com_content.article') {
            return false;
        }

        $catid = \is_object($item) && isset($item->catid) ? (int) $item->catid : 0;

        return $this->assigned('com_content_category', $catid);
    }

    private function menuAllowed(string $prefix): bool
    {
        $app  = $this->getApplication();
        $menu = $app->getMenu()->getActive();

        return $this->assigned($prefix . '_menu', $menu ? (int) $menu->id : $app->getInput()->getInt('Itemid', 0));
    }

    /**
     * Assignment 0 = nowhere, 1 = all, 2 = only the selected, 3 = all except the selected.
     */
    private function assigned(string $prefix, int $id): bool
    {
        $selected = array_map('intval', (array) $this->params->get($prefix . '_select', []));

        return match ((int) $this->params->get($prefix . '_assignment', 1)) {
            1       => true,
            2       => \in_array($id, $selected, true),
            3       => !\in_array($id, $selected, true),
            default => false,
        };
    }

    /**
     * Only known keys and values of the short tag; anything else is ignored.
     */
    private function shortTagOptions(string $attributes): array
    {
        $options = [];
        $allowed = ['orientation' => self::ORIENTATIONS, 'theme' => self::THEMES, 'style' => self::STYLES];

        foreach (preg_split('/\s+/', trim(html_entity_decode(strip_tags($attributes)))) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $key   = strtolower(trim($key));
            $value = strtolower(trim($value, " \"'"));

            if (isset($allowed[$key]) && \in_array($value, $allowed[$key], true)) {
                $options[$key] = $value;
            }
        }

        return $options;
    }

    /**
     * The service rows of the settings in their order, keyed by service; unknown and retired services are left out.
     *
     * @return  array<string, object>
     */
    private function serviceRows(): array
    {
        $rows = [];

        foreach ((array) $this->params->get('services', []) as $row) {
            $row  = (object) $row;
            $name = strtolower(trim((string) ($row->services ?? '')));

            if (\in_array($name, Services::NAMES, true) && !isset($rows[$name])) {
                $rows[$name] = $row;
            }
        }

        return $rows;
    }

    private function choice(?string $value, array $allowed): string
    {
        return \in_array($value, $allowed, true) ? $value : $allowed[0];
    }

    /**
     * The buttons; empty when no service is set or the page is not HTML.
     */
    private function render(array $options, ?string $title = null, ?object $item = null): string
    {
        $rows     = $this->serviceRows();
        $app      = $this->getApplication();
        $document = $app->getDocument();

        if (!$rows || !$document instanceof HtmlDocument) {
            return '';
        }

        // Language of the button texts: the page's language, or a fixed one (idea of pull request #13 by thexmanxyz)
        $fixed       = (string) $this->params->get('shariff_lang', 'auto');
        $lang        = preg_match('/^[a-z]{2}$/', $fixed) ? $fixed : strtolower(explode('-', $app->getLanguage()->getTag())[0]);
        $orientation = $this->choice($options['orientation'] ?? $this->params->get('data_orientation'), self::ORIENTATIONS);
        $theme       = $this->choice($options['theme'] ?? $this->params->get('data_theme'), self::THEMES);
        $style       = $this->choice($options['style'] ?? $this->params->get('data_style'), self::STYLES);
        $page        = [
            'url'   => Uri::getInstance()->toString(['scheme', 'host', 'port', 'path', 'query']),
            'title' => trim(strip_tags(html_entity_decode((string) ($title ?: $document->getTitle()), ENT_QUOTES, 'UTF-8'))),
            'image' => $this->image($item, $document),
        ];
        $icons = Uri::root(true) . '/media/plg_system_jooag_shariff/images/icons.svg?' . $document->getMediaVersion();
        $items = '';

        foreach ($rows as $name => $row) {
            $page['infoUrl'] = $name === 'info' ? $this->infoUrl($row) : '';
            $link            = Services::link($name, $page, $row);
            $label           = Services::text($name, 'title', $lang);
            $attributes      = ['href' => $link['href'], 'title' => $label, 'aria-label' => $label];

            if ($link['popup']) {
                $attributes['data-rel'] = 'popup';
            }

            if ($link['popup'] || $link['blank']) {
                $attributes['target'] = '_blank';
                $attributes['rel']    = $name === 'info' ? 'noopener' : 'nofollow noopener noreferrer';
            }

            if ($link['action'] !== '') {
                $attributes['data-shariff-action'] = $link['action'];
                $attributes['role']                = 'button';
            }

            $items .= '<li class="shariff-button ' . $name . '"><a' . $this->attributes($attributes) . '>'
                . '<svg class="shariff-icon" aria-hidden="true" focusable="false"><use href="' . $this->escape($icons . '#' . $name) . '"></use></svg>'
                . ($style === 'standard' ? '<span class="share_text">' . $this->escape(Services::text($name, 'share', $lang)) . '</span>' : '')
                . '</a></li>';
        }

        $box = ['class' => 'shariff', 'data-url' => $page['url']];

        // The counts are filled in by the script, so only with the script and a style that shows numbers
        if ((int) $this->params->get('shariff_counter', 0) === 1 && $style !== 'icon'
            && (string) $this->params->get('shariffjs', 'shariff.complete.js') !== '-1') {
            $box['data-backend-url'] = Uri::base(true) . '/index.php?option=com_ajax&plugin=jooag_shariff&group=system&format=json';
            $box['data-sig']         = $this->signature($page['url']);
        }

        $this->useAssets($document);

        return '<div' . $this->attributes($box) . '><ul class="theme-' . $theme . ' orientation-' . $orientation
            . ' button-style-' . $style . ' shariff-col-' . \count($rows) . '">' . $items . '</ul></div>';
    }

    private function useAssets(HtmlDocument $document): void
    {
        $assets = $document->getWebAssetManager();
        $assets->getRegistry()->addExtensionRegistryFile('plg_system_jooag_shariff');

        if ((string) $this->params->get('shariffcss', 'shariff.complete.css') !== '-1') {
            $assets->useStyle('plg_system_jooag_shariff');
        }

        if ((string) $this->params->get('shariffjs', 'shariff.complete.js') !== '-1') {
            $assets->useScript('plg_system_jooag_shariff');
        }
    }

    /**
     * Image for Pinterest: full text or intro image of the article, otherwise an og:image already set on the page.
     */
    private function image(?object $item, HtmlDocument $document): string
    {
        $images = $item !== null && isset($item->images) ? json_decode((string) $item->images, true) : null;
        $path   = \is_array($images) ? ($images['image_fulltext'] ?? '') ?: ($images['image_intro'] ?? '') : '';
        $path   = $path !== '' ? HTMLHelper::cleanImageURL($path)->url : (string) $document->getMetaData('og:image', 'property');

        if ($path === '' || preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return rtrim(Uri::root(), '/') . '/' . ltrim($path, '/');
    }

    /**
     * Address of the info button: the chosen article, otherwise Shariff's info page.
     */
    private function infoUrl(object $row): string
    {
        $id = (int) ($row->special_data_info_display_article_id ?? 0);

        if ($id < 1) {
            return Services::DEFAULT_INFO_URL;
        }

        if (!isset($this->infoUrls[$id])) {
            $db      = $this->getDatabase();
            $article = $db->setQuery(
                $db->createQuery()
                    ->select($db->quoteName(['id', 'catid', 'language']))
                    ->from($db->quoteName('#__content'))
                    ->where($db->quoteName('id') . ' = :id')
                    ->bind(':id', $id, ParameterType::INTEGER)
            )->loadObject();

            $this->infoUrls[$id] = $article
                ? Route::_(RouteHelper::getArticleRoute($article->id, $article->catid, $article->language))
                : Services::DEFAULT_INFO_URL;
        }

        return $this->infoUrls[$id];
    }

    /**
     * Hosts whose addresses the counter may look up: the current one, or the domains set under "Share URL".
     */
    private function counterHosts(): array
    {
        $hosts = [];

        if ((int) $this->params->get('data_url', 0) === 1) {
            foreach ((array) $this->params->get('data_url_custom', []) as $row) {
                $domain = strtolower(trim((string) (((object) $row)->custom_domains ?? '')));
                $domain = parse_url(str_contains($domain, '//') ? $domain : '//' . $domain, PHP_URL_HOST) ?: '';

                if ($domain !== '') {
                    $hosts[] = preg_replace('/^www\./', '', $domain);
                }
            }
        }

        return $hosts ?: [preg_replace('/^www\./', '', strtolower(Uri::getInstance()->getHost()))];
    }

    private function attributes(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            $html .= ' ' . $name . '="' . $this->escape((string) $value) . '"';
        }

        return $html;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
