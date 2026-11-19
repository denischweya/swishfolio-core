<?php
/**
 * Points a kit's header, footer and menus at the pages a build created.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Onboarding;

/**
 * KitDesign copies a template's header, footer and menus with their links
 * kept as this site's URL plus the template's paths (/menu/, /story/). The
 * generated site has its own pages, so those paths would 404. This resolves
 * each internal path to a generated page, by the same rule generation uses
 * for links inside sections (PlanExecutor::links()):
 *
 *  - "/" is the home page;
 *  - a path naming a generated page's slug is that page;
 *  - a template page path whose key the plan reused is the plan's page;
 *  - any other template page path goes to the last page in the plan's menu
 *    (in practice the contact or booking page);
 *  - anything else on this site (demo projects, posts) goes home.
 *
 * Pure: everything WordPress-specific arrives as data.
 */
class KitLinks
{
    /** @var string */
    private $home;

    /** @var array[] slug => page */
    private $bySlug = [];

    /** @var array[] key => page */
    private $byKey = [];

    /** @var string[] template slug => template key */
    private $kitSlugs = [];

    /** @var array|null */
    private $fallback = null;

    /** @var array */
    private $front;

    /** @var array[] */
    private $nav = [];

    /**
     * @param string $home      This site's home URL.
     * @param array  $pages     Generated pages: key, title, slug, postId, url.
     * @param string $frontKey  Plan front page key.
     * @param array  $navKeys   Plan navigation keys, in order.
     * @param array  $kitPages  Template pages: key, slug.
     */
    public function __construct($home, array $pages, $frontKey, array $navKeys, array $kitPages)
    {
        $this->home  = rtrim((string) $home, '/');
        $this->front = ['key' => (string) $frontKey, 'title' => '', 'postId' => 0, 'url' => $this->home . '/'];

        foreach ($pages as $page) {
            $page = [
                'key'    => (string) $page['key'],
                'title'  => (string) $page['title'],
                'postId' => (int) $page['postId'],
                'url'    => (string) $page['key'] === (string) $frontKey ? $this->home . '/' : (string) $page['url'],
                'slug'   => isset($page['slug']) ? (string) $page['slug'] : '',
            ];
            $this->byKey[$page['key']] = $page;
            if ($page['slug'] !== '') {
                $this->bySlug[$page['slug']] = $page;
            }
            if ($page['key'] === (string) $frontKey) {
                $this->front = $page;
            }
        }

        foreach ($navKeys as $key) {
            if (isset($this->byKey[$key])) {
                $this->nav[] = $this->byKey[$key];
            }
        }

        $this->fallback = $this->nav !== [] ? end($this->nav) : $this->front;

        foreach ($kitPages as $kitPage) {
            $slug = isset($kitPage['slug']) ? (string) $kitPage['slug'] : (string) $kitPage['key'];
            $this->kitSlugs[trim($slug, '/')] = (string) $kitPage['key'];
        }
    }

    /**
     * @param string $path Site path, e.g. "/menu/".
     *
     * @return array The page it should point at: key, title, postId, url.
     */
    public function resolve($path)
    {
        $slug = trim((string) $path, '/');

        if ($slug === '' || $slug === 'home') {
            return $this->front;
        }

        if (isset($this->bySlug[$slug])) {
            return $this->bySlug[$slug];
        }

        if (isset($this->kitSlugs[$slug])) {
            $key = $this->kitSlugs[$slug];

            return isset($this->byKey[$key]) ? $this->byKey[$key] : $this->fallback;
        }

        return $this->front;
    }

    /**
     * @param string $url A URL found in markup.
     *
     * @return string|null Its site path when it points inside this site at a page, else null.
     */
    public function internalPath($url)
    {
        $url = html_entity_decode((string) $url, ENT_QUOTES, 'UTF-8');

        if ($url === '' || $url[0] === '#') {
            return null;
        }

        $homeHost = (string) parse_url($this->home, PHP_URL_HOST);
        $homePath = rtrim((string) parse_url($this->home, PHP_URL_PATH), '/');
        $host     = parse_url($url, PHP_URL_HOST);
        $scheme   = parse_url($url, PHP_URL_SCHEME);

        if ($scheme !== null && ! in_array(strtolower($scheme), ['http', 'https'], true)) {
            return null;
        }

        if ($host !== null && strtolower($host) !== strtolower($homeHost)) {
            return null;
        }

        if ($host === null && $url[0] !== '/') {
            return null;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);

        if ($homePath !== '' && strpos($path, $homePath) === 0) {
            $path = substr($path, strlen($homePath));
        }

        if (preg_match('#^/(wp-content|wp-admin|wp-json|wp-includes|feed)(/|$)#', $path) || preg_match('/\.[a-z0-9]{2,5}$/i', $path)) {
            return null;
        }

        return $path === '' ? '/' : $path;
    }

    /**
     * Rewrite internal links in markup: href attributes and navigation-link "url" attributes.
     *
     * @param string $markup Part or page markup.
     *
     * @return string
     */
    public function remap($markup)
    {
        $self = $this;

        $markup = (string) preg_replace_callback('/\bhref="([^"]*)"/', static function ($m) use ($self) {
            $path = $self->internalPath($m[1]);
            if ($path === null) {
                return $m[0];
            }
            $fragment = (string) parse_url(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), PHP_URL_FRAGMENT);

            return 'href="' . htmlspecialchars($self->resolve($path)['url'] . ($fragment !== '' ? '#' . $fragment : ''), ENT_QUOTES, 'UTF-8') . '"';
        }, (string) $markup);

        return self::mapNavigationLinks($markup, function (array $attrs) use ($self) {
            $path = isset($attrs['url']) ? $self->internalPath((string) $attrs['url']) : null;
            if ($path === null) {
                return $attrs;
            }

            return $self->pointAt($attrs, $self->resolve($path), false);
        });
    }

    /**
     * Rewrite a menu: every internal link points at its generated page and
     * takes that page's title; links that end up twice in a row collapse.
     *
     * @param string $content wp_navigation content.
     *
     * @return string
     */
    public function remapMenu($content)
    {
        $self = $this;
        $seen = [];

        return self::mapNavigationLinks((string) $content, function (array $attrs) use ($self, &$seen) {
            $path = isset($attrs['url']) ? $self->internalPath((string) $attrs['url']) : null;
            if ($path !== null) {
                $attrs = $self->pointAt($attrs, $self->resolve($path), true);
            }
            $key = isset($attrs['url']) ? (string) $attrs['url'] : '';
            if ($key !== '' && isset($seen[$key])) {
                return null;
            }
            $seen[$key] = true;

            return $attrs;
        });
    }

    /**
     * The main menu as the plan ordered it.
     *
     * @return string wp_navigation content.
     */
    public function menu()
    {
        $out = [];

        foreach ($this->nav as $page) {
            $out[] = '<!-- wp:navigation-link ' . self::json($this->pointAt(['kind' => 'custom'], $page, true)) . ' /-->';
        }

        return implode("\n\n", $out);
    }

    /**
     * @param array $attrs   navigation-link attributes.
     * @param array $page    Target page.
     * @param bool  $relabel Use the page's title as the label.
     *
     * @return array
     */
    public function pointAt(array $attrs, array $page, $relabel)
    {
        $attrs['url'] = $page['url'];

        if ($relabel && $page['title'] !== '') {
            $attrs['label'] = $page['title'];
        }

        // The front page is linked as "/" so it works before and after publishing.
        if (! empty($page['postId']) && $page['url'] !== $this->home . '/') {
            $attrs['type'] = 'page';
            $attrs['id']   = (int) $page['postId'];
            $attrs['kind'] = 'post-type';
        } else {
            unset($attrs['type'], $attrs['id']);
            $attrs['kind'] = 'custom';
        }

        return $attrs;
    }

    /**
     * @param string   $markup Markup.
     * @param callable $map    attrs => attrs, or null to drop the link.
     *
     * @return string
     */
    private static function mapNavigationLinks($markup, callable $map)
    {
        return (string) preg_replace_callback('#<!-- wp:navigation-link (\{.*?\}) /-->\s*#s', static function ($m) use ($map) {
            $attrs = json_decode($m[1], true);
            if (! is_array($attrs)) {
                return $m[0];
            }
            $attrs = $map($attrs);
            if ($attrs === null) {
                return '';
            }
            $tail = substr($m[0], strlen('<!-- wp:navigation-link ' . $m[1] . ' /-->'));

            return '<!-- wp:navigation-link ' . self::json($attrs) . ' /-->' . $tail;
        }, $markup);
    }

    /**
     * @param array $attrs Attributes.
     *
     * @return string
     */
    private static function json(array $attrs)
    {
        if (function_exists('serialize_block_attributes')) {
            return serialize_block_attributes($attrs);
        }

        $json = (string) json_encode($attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return str_replace(['--', '<', '>', '&', '\\"'], ['\\u002d\\u002d', '\\u003c', '\\u003e', '\\u0026', '\\u0022'], $json);
    }
}
