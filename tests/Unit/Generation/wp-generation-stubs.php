<?php
/**
 * WordPress stubs the generation suite needs on top of tests/stubs/wp-functions.php.
 *
 * Every one is guarded, so the real WordPress function wins whenever these tests
 * are run against a loaded WordPress. serialize_block() mirrors core's algorithm
 * byte for byte — including the strtr map that escapes "--", "<", ">" and "&"
 * inside a block comment — because the composer's output is compared against it.
 *
 * @package SwishfolioCore
 */

if (! isset($GLOBALS['sfcore_test_wp'])) {
    $GLOBALS['sfcore_test_wp'] = [];
}

/**
 * Reset the fake WordPress between tests.
 *
 * @param array $overrides State to start from.
 *
 * @return void
 */
function sfcore_test_wp_reset(array $overrides = [])
{
    $GLOBALS['sfcore_test_wp'] = array_merge([
        'caps'        => true,
        'userId'      => 7,
        'loggedIn'    => true,
        'posts'       => [],
        'meta'        => [],
        'nextId'      => 100,
        'insertError' => null,
        'updateError' => null,
        'home'        => 'https://example.test',
    ], $overrides);
}

if (! function_exists('sanitize_title')) {
    function sanitize_title($title, $fallback = '', $context = 'save')
    {
        $title = strtolower(trim(strip_tags((string) $title)));
        $title = preg_replace('/[^a-z0-9\s\-_]/', '', $title);
        $title = preg_replace('/[\s_]+/', '-', (string) $title);
        $title = trim(preg_replace('/-+/', '-', (string) $title), '-');

        return $title === '' ? (string) $fallback : $title;
    }
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode($data, $flags = 0, $depth = 512)
    {
        return json_encode($data, $flags, $depth);
    }
}

if (! function_exists('serialize_block')) {
    function sfcore_test_serialize_block_attributes($attributes)
    {
        return strtr(
            (string) json_encode($attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            [
                '\\\\' => '\\u005c',
                '--'   => '\\u002d\\u002d',
                '<'    => '\\u003c',
                '>'    => '\\u003e',
                '&'    => '\\u0026',
                '\\"'  => '\\u0022',
            ]
        );
    }

    function serialize_block($block)
    {
        $content = '';
        $index   = 0;

        foreach ((array) $block['innerContent'] as $chunk) {
            $content .= is_string($chunk) ? $chunk : serialize_block($block['innerBlocks'][$index++]);
        }

        $name  = strpos((string) $block['blockName'], 'core/') === 0
            ? substr((string) $block['blockName'], 5)
            : (string) $block['blockName'];
        $attrs = empty($block['attrs']) ? '' : sfcore_test_serialize_block_attributes($block['attrs']) . ' ';

        if ($content === '') {
            return sprintf('<!-- wp:%s %s/-->', $name, $attrs);
        }

        return sprintf('<!-- wp:%s %s-->%s<!-- /wp:%s -->', $name, $attrs, $content, $name);
    }
}

if (! function_exists('current_user_can')) {
    function current_user_can($capability, ...$args)
    {
        $caps = $GLOBALS['sfcore_test_wp']['caps'];

        if ($caps === true) {
            return true;
        }

        if ($args !== [] && in_array($capability . ':' . (string) $args[0], (array) $caps, true)) {
            return true;
        }

        return in_array($capability, (array) $caps, true);
    }
}

if (! function_exists('get_current_user_id')) {
    function get_current_user_id()
    {
        return (int) $GLOBALS['sfcore_test_wp']['userId'];
    }
}

if (! function_exists('is_user_logged_in')) {
    function is_user_logged_in()
    {
        return ! empty($GLOBALS['sfcore_test_wp']['loggedIn']);
    }
}

if (! function_exists('get_post_type_object')) {
    function get_post_type_object($postType)
    {
        if ($postType !== 'page') {
            return null;
        }

        $object      = new \stdClass();
        $object->cap = new \stdClass();

        $object->cap->create_posts = 'edit_pages';
        $object->cap->edit_post    = 'edit_page';

        return $object;
    }
}

if (! function_exists('wp_slash')) {
    function wp_slash($value)
    {
        if (is_array($value)) {
            return array_map('wp_slash', $value);
        }

        return is_string($value) ? addslashes($value) : $value;
    }
}

if (! function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }

        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (! function_exists('wp_insert_post')) {
    function wp_insert_post($postArray, $wpError = false, $fireAfterHooks = true)
    {
        if ($GLOBALS['sfcore_test_wp']['insertError'] !== null) {
            return new \WP_Error_Stub($GLOBALS['sfcore_test_wp']['insertError']);
        }

        // Core unslashes before writing to the database. Mirroring that here is
        // what makes a handler that forgets to slash fail in a test rather than
        // only on a live site.
        $postArray = wp_unslash($postArray);

        $id = $GLOBALS['sfcore_test_wp']['nextId']++;

        $post = array_merge([
            'post_type'    => 'post',
            'post_status'  => 'draft',
            'post_title'   => '',
            'post_name'    => '',
            'post_content' => '',
            'post_author'  => 0,
        ], $postArray);

        $post['ID'] = $id;

        foreach ($GLOBALS['sfcore_test_wp']['posts'] as $existing) {
            if ($existing['post_name'] === $post['post_name'] && $existing['post_type'] === $post['post_type']) {
                $post['post_name'] .= '-2';
                break;
            }
        }

        $GLOBALS['sfcore_test_wp']['posts'][$id] = $post;

        return $id;
    }
}

if (! function_exists('wp_update_post')) {
    function wp_update_post($postArray, $wpError = false, $fireAfterHooks = true)
    {
        if ($GLOBALS['sfcore_test_wp']['updateError'] !== null) {
            return new \WP_Error_Stub($GLOBALS['sfcore_test_wp']['updateError']);
        }

        $postArray = wp_unslash($postArray);
        $id        = isset($postArray['ID']) ? (int) $postArray['ID'] : 0;

        if (! isset($GLOBALS['sfcore_test_wp']['posts'][$id])) {
            return new \WP_Error_Stub('No post to update.');
        }

        $GLOBALS['sfcore_test_wp']['posts'][$id] = array_merge(
            $GLOBALS['sfcore_test_wp']['posts'][$id],
            $postArray
        );

        return $id;
    }
}

if (! function_exists('update_post_meta')) {
    function update_post_meta($postId, $key, $value, $previous = '')
    {
        $GLOBALS['sfcore_test_wp']['meta'][(int) $postId][$key] = $value;

        return true;
    }
}

if (! function_exists('get_post_meta')) {
    function get_post_meta($postId, $key = '', $single = false)
    {
        $meta = isset($GLOBALS['sfcore_test_wp']['meta'][(int) $postId])
            ? $GLOBALS['sfcore_test_wp']['meta'][(int) $postId]
            : [];

        if ($key === '') {
            return $meta;
        }

        if (! isset($meta[$key])) {
            return $single ? '' : [];
        }

        return $single ? $meta[$key] : [$meta[$key]];
    }
}

if (! function_exists('get_post_field')) {
    function get_post_field($field, $postId = null, $context = 'display')
    {
        $id = (int) $postId;

        return isset($GLOBALS['sfcore_test_wp']['posts'][$id][$field])
            ? $GLOBALS['sfcore_test_wp']['posts'][$id][$field]
            : '';
    }
}

if (! function_exists('get_post_type')) {
    function get_post_type($post = null)
    {
        $id = (int) $post;

        return isset($GLOBALS['sfcore_test_wp']['posts'][$id])
            ? $GLOBALS['sfcore_test_wp']['posts'][$id]['post_type']
            : false;
    }
}

if (! function_exists('get_post_status')) {
    function get_post_status($post = null)
    {
        $id = (int) $post;

        return isset($GLOBALS['sfcore_test_wp']['posts'][$id])
            ? $GLOBALS['sfcore_test_wp']['posts'][$id]['post_status']
            : false;
    }
}

if (! function_exists('home_url')) {
    function home_url($path = '', $scheme = null)
    {
        return rtrim($GLOBALS['sfcore_test_wp']['home'], '/') . '/' . ltrim((string) $path, '/');
    }
}

if (! function_exists('get_page_link')) {
    function get_page_link($post = false, $leavename = false, $sample = false)
    {
        $id   = (int) $post;
        $slug = isset($GLOBALS['sfcore_test_wp']['posts'][$id])
            ? $GLOBALS['sfcore_test_wp']['posts'][$id]['post_name']
            : '';

        return $slug === '' ? home_url('?page_id=' . $id) : home_url($slug . '/');
    }
}

if (! function_exists('get_permalink')) {
    function get_permalink($post = 0, $leavename = false)
    {
        return get_page_link($post, $leavename, false);
    }
}

if (! function_exists('esc_html')) {
    function esc_html($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8', false);
    }
}
