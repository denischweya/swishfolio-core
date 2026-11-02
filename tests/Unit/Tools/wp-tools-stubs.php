<?php
/**
 * WordPress stubs the tool suite needs on top of the generation stubs.
 *
 * Shares the same $GLOBALS['sfcore_test_wp'] fake site, so a tool test and a
 * generation test describe the same world. Every function is guarded, so a real
 * WordPress wins if these ever run loaded.
 *
 * Revisions are modelled properly rather than faked away: the journal's whole
 * correctness argument rests on wp_save_post_revision() capturing state before a
 * write and wp_restore_post_revision() putting it back, including the case where
 * the first returns null.
 *
 * @package SwishfolioCore
 */

require_once __DIR__ . '/../Generation/wp-generation-stubs.php';

if (! function_exists('sfcore_test_tools_reset')) {
    /**
     * The tool-suite state, on top of the generation suite's.
     *
     * The defaults live in sfcore_test_tools_defaults() rather than only here,
     * because the evals suite resets through sfcore_test_wp_reset() and never
     * calls this — and once this file is loaded anywhere in the process its
     * stubs are live for every test. A stub that assumed its own reset had run
     * failed exactly that way.
     *
     * @return void
     */
    function sfcore_test_tools_reset()
    {
        sfcore_test_wp_reset();

        $GLOBALS['sfcore_test_options'] = [];

        sfcore_test_tools_defaults();
    }

    /**
     * Fills in any tool-suite state key that is missing, leaving set ones alone.
     *
     * @return void
     */
    function sfcore_test_tools_defaults()
    {
        $defaults = [
            'revisions'         => [],
            'nextRevisionId'    => 900,
            'revisionsDisabled' => false,
            'missingRevisions'  => [],
            'trashFails'        => [],
            'blogId'            => 1,
            'attachmentMeta'    => [],
            'themeName'         => 'Swishfolio',
        ];

        if (! isset($GLOBALS['sfcore_test_wp']) || ! is_array($GLOBALS['sfcore_test_wp'])) {
            $GLOBALS['sfcore_test_wp'] = [];
        }

        foreach ($defaults as $key => $value) {
            if (! array_key_exists($key, $GLOBALS['sfcore_test_wp'])) {
                $GLOBALS['sfcore_test_wp'][$key] = $value;
            }
        }
    }
}

sfcore_test_tools_defaults();

if (! function_exists('get_current_blog_id')) {
    function get_current_blog_id()
    {
        return (int) ($GLOBALS['sfcore_test_wp']['blogId'] ?? 1);
    }
}

if (! function_exists('wp_save_post_revision')) {
    function wp_save_post_revision($postId)
    {
        sfcore_test_tools_defaults();

        if (! empty($GLOBALS['sfcore_test_wp']['revisionsDisabled'])) {
            return null;
        }

        $postId = (int) $postId;

        if (! isset($GLOBALS['sfcore_test_wp']['posts'][$postId])) {
            return null;
        }

        $post = $GLOBALS['sfcore_test_wp']['posts'][$postId];
        $id   = ++$GLOBALS['sfcore_test_wp']['nextRevisionId'];

        $GLOBALS['sfcore_test_wp']['revisions'][$id] = [
            'post_parent'  => $postId,
            'post_content' => isset($post['post_content']) ? $post['post_content'] : '',
            'post_title'   => isset($post['post_title']) ? $post['post_title'] : '',
        ];

        return $id;
    }
}

if (! function_exists('wp_restore_post_revision')) {
    function wp_restore_post_revision($revisionId)
    {
        sfcore_test_tools_defaults();

        $revisionId = (int) $revisionId;

        if (in_array($revisionId, (array) ($GLOBALS['sfcore_test_wp']['missingRevisions'] ?? []), true)) {
            return null;
        }

        if (! isset($GLOBALS['sfcore_test_wp']['revisions'][$revisionId])) {
            return null;
        }

        $revision = $GLOBALS['sfcore_test_wp']['revisions'][$revisionId];
        $parent   = (int) $revision['post_parent'];

        if (! isset($GLOBALS['sfcore_test_wp']['posts'][$parent])) {
            return null;
        }

        $GLOBALS['sfcore_test_wp']['posts'][$parent]['post_content'] = $revision['post_content'];
        $GLOBALS['sfcore_test_wp']['posts'][$parent]['post_title']   = $revision['post_title'];

        return $parent;
    }
}

if (! function_exists('wp_trash_post')) {
    function wp_trash_post($postId)
    {
        sfcore_test_tools_defaults();

        $postId = (int) $postId;

        if (in_array($postId, (array) ($GLOBALS['sfcore_test_wp']['trashFails'] ?? []), true)) {
            return false;
        }

        if (! isset($GLOBALS['sfcore_test_wp']['posts'][$postId])) {
            return false;
        }

        $GLOBALS['sfcore_test_wp']['posts'][$postId]['post_status'] = 'trash';

        return $GLOBALS['sfcore_test_wp']['posts'][$postId];
    }
}

if (! function_exists('delete_post_meta')) {
    function delete_post_meta($postId, $key, $value = '')
    {
        unset($GLOBALS['sfcore_test_wp']['meta'][(int) $postId][$key]);

        return true;
    }
}

if (! function_exists('delete_option')) {
    function delete_option($key)
    {
        if (! isset($GLOBALS['sfcore_test_options'][$key])) {
            return false;
        }

        unset($GLOBALS['sfcore_test_options'][$key]);

        return true;
    }
}

if (! function_exists('get_the_title')) {
    function get_the_title($post = 0)
    {
        $postId = (int) $post;

        return isset($GLOBALS['sfcore_test_wp']['posts'][$postId]['post_title'])
            ? (string) $GLOBALS['sfcore_test_wp']['posts'][$postId]['post_title']
            : '';
    }
}

if (! function_exists('current_time')) {
    function current_time($type = 'mysql', $gmt = 0)
    {
        return '2026-11-02 10:00:00';
    }
}

if (! function_exists('wp_hash')) {
    function wp_hash($data, $scheme = 'auth')
    {
        return hash_hmac('md5', (string) $data, 'sfcore-test-salt');
    }
}

if (! function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', 1, 2, 3, 4, 5, 6, 7, 8);
    }
}

if (! function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($text, $removeBreaks = false)
    {
        return trim(strip_tags((string) $text));
    }
}

if (! function_exists('strip_shortcodes')) {
    function strip_shortcodes($content)
    {
        return (string) $content;
    }
}

if (! function_exists('get_bloginfo')) {
    function get_bloginfo($show = '', $filter = 'raw')
    {
        $values = [
            'name'        => 'Acme Studio',
            'description' => 'Design and build',
        ];

        return isset($values[$show]) ? $values[$show] : '';
    }
}

if (! function_exists('get_locale')) {
    function get_locale()
    {
        return 'en_GB';
    }
}

if (! function_exists('wp_count_posts')) {
    function wp_count_posts($type = 'post', $perm = '')
    {
        $counts = ['publish' => 0, 'draft' => 0];

        foreach ((array) $GLOBALS['sfcore_test_wp']['posts'] as $post) {
            if (($post['post_type'] ?? '') !== $type) {
                continue;
            }

            $status = $post['post_status'] ?? 'draft';

            if (isset($counts[$status])) {
                ++$counts[$status];
            }
        }

        return (object) $counts;
    }
}

if (! function_exists('wp_get_attachment_url')) {
    function wp_get_attachment_url($postId = 0)
    {
        return 'https://example.test/uploads/' . (int) $postId . '.png';
    }
}

if (! function_exists('wp_get_attachment_metadata')) {
    function wp_get_attachment_metadata($postId = 0, $unfiltered = false)
    {
        return $GLOBALS['sfcore_test_wp']['attachmentMeta'][(int) $postId] ?? ['width' => 1200, 'height' => 800];
    }
}

if (! function_exists('wp_get_theme')) {
    function wp_get_theme($stylesheet = null, $themeRoot = null)
    {
        return new SfcoreTestTheme();
    }
}

if (! class_exists('SfcoreTestTheme')) {
    class SfcoreTestTheme
    {
        public function get($header)
        {
            return 'Name' === $header ? (string) $GLOBALS['sfcore_test_wp']['themeName'] : '';
        }

        public function get_stylesheet()
        {
            return 'swishfolio';
        }
    }
}

if (! function_exists('parse_blocks')) {
    /**
     * Enough of the real parser for the tool suite: block comments with optional
     * JSON attributes, self-closing or wrapping, nested to any depth. It also
     * emits the nameless whitespace node core emits between blocks, because
     * read-page has to skip those to keep its index paths right.
     */
    function parse_blocks($content)
    {
        $tokens = [];
        $pattern = '/<!--\s+(\/)?wp:([a-z][a-z0-9_-]*\/[a-z][a-z0-9_-]*|[a-z][a-z0-9_-]*)\s*(\{.*?\})?\s*(\/)?-->/s';

        preg_match_all($pattern, (string) $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        $cursor = 0;

        foreach ($matches as $match) {
            $offset = $match[0][1];
            $text   = substr((string) $content, $cursor, $offset - $cursor);

            if (trim($text) !== '' || $text !== '') {
                $tokens[] = ['text' => $text];
            }

            $name = $match[2][0];

            if (strpos($name, '/') === false) {
                $name = 'core/' . $name;
            }

            $tokens[] = [
                'closing' => '/' === $match[1][0],
                'void'    => isset($match[4]) && '/' === $match[4][0],
                'name'    => $name,
                'attrs'   => isset($match[3]) && '' !== $match[3][0]
                    ? (array) json_decode($match[3][0], true)
                    : [],
            ];

            $cursor = $offset + strlen($match[0][0]);
        }

        $tail = substr((string) $content, $cursor);

        if ('' !== $tail) {
            $tokens[] = ['text' => $tail];
        }

        $index = 0;

        return sfcore_test_parse_level($tokens, $index, null);
    }

    function sfcore_test_parse_level(array $tokens, &$index, $closingFor)
    {
        $blocks = [];

        while ($index < count($tokens)) {
            $token = $tokens[$index];

            if (isset($token['text'])) {
                ++$index;

                if (trim($token['text']) !== '') {
                    $blocks[] = [
                        'blockName'    => null,
                        'attrs'        => [],
                        'innerBlocks'  => [],
                        'innerHTML'    => $token['text'],
                        'innerContent' => [$token['text']],
                    ];
                }

                continue;
            }

            if (! empty($token['closing'])) {
                if ($closingFor === $token['name']) {
                    ++$index;

                    return $blocks;
                }

                ++$index;

                continue;
            }

            ++$index;

            if (! empty($token['void'])) {
                $blocks[] = [
                    'blockName'    => $token['name'],
                    'attrs'        => $token['attrs'],
                    'innerBlocks'  => [],
                    'innerHTML'    => '',
                    'innerContent' => [],
                ];

                continue;
            }

            $children = sfcore_test_parse_level($tokens, $index, $token['name']);

            $blocks[] = [
                'blockName'    => $token['name'],
                'attrs'        => $token['attrs'],
                'innerBlocks'  => array_values(
                    array_filter(
                        $children,
                        static function ($child) {
                            return ! empty($child['blockName']);
                        }
                    )
                ),
                'innerHTML'    => '',
                'innerContent' => [],
            ];
        }

        return $blocks;
    }
}

if (! class_exists('WP_Query')) {
    class WP_Query
    {
        public $posts = [];

        public $found_posts = 0;

        public function __construct($args = [])
        {
            $type   = $args['post_type'] ?? 'page';
            $status = $args['post_status'] ?? 'any';
            $search = $args['s'] ?? '';
            $limit  = (int) ($args['posts_per_page'] ?? 10);
            $offset = (int) ($args['offset'] ?? 0);

            $matched = [];

            foreach ((array) $GLOBALS['sfcore_test_wp']['posts'] as $post) {
                if (($post['post_type'] ?? '') !== $type) {
                    continue;
                }

                if ('any' !== $status && 'inherit' !== $status && ($post['post_status'] ?? '') !== $status) {
                    continue;
                }

                if ('' !== $search && stripos((string) ($post['post_title'] ?? ''), (string) $search) === false) {
                    continue;
                }

                $matched[] = (object) ($post + [
                    'post_excerpt'      => '',
                    'post_modified_gmt' => '2026-11-01 09:00:00',
                    'post_mime_type'    => '',
                ]);
            }

            $this->found_posts = count($matched);
            $this->posts       = array_slice($matched, $offset, $limit);
        }
    }
}
