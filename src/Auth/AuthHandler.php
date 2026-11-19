<?php
/**
 * Front-end login and registration for the main site.
 *
 * Renders [swishfolio_auth form="login|register"], handles the posts through
 * admin-post.php (nonce-protected), and sends the default wp-login.php login
 * screen to the /login/ page on the main site only. Nothing here creates
 * accounts: the register form and handler point at the signup URL (filter
 * swishfolio_auth_signup_url; on the hosted network that is /get-started/). Error text is deliberately
 * generic so the forms cannot be used to find out which accounts exist.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Auth;

class AuthHandler
{
    public const ACTION_LOGIN    = 'sf_auth_login';
    public const ACTION_REGISTER = 'sf_auth_register';

    private const MESSAGES = [
        'invalid'   => 'That email, username or password did not match. Please try again.',
        'empty'     => 'Please enter your email or username and your password.',
        'nonce'     => 'Your session expired. Please try again.',
        'loggedout' => 'You have been signed out.',
    ];

    public function boot(): void
    {
        add_shortcode('swishfolio_auth', [ $this, 'renderShortcode' ]);

        add_action('admin_post_nopriv_' . self::ACTION_LOGIN, [ $this, 'handleLogin' ]);
        add_action('admin_post_' . self::ACTION_LOGIN, [ $this, 'handleLogin' ]);
        add_action('admin_post_nopriv_' . self::ACTION_REGISTER, [ $this, 'handleRegister' ]);
        add_action('admin_post_' . self::ACTION_REGISTER, [ $this, 'handleRegister' ]);

        add_action('template_redirect', [ $this, 'redirectLoggedIn' ]);
        add_action('login_init', [ $this, 'redirectWpLogin' ]);
    }

    // --- URLs ---------------------------------------------------------------

    private function pageUrl(string $slug): string
    {
        $page = get_page_by_path($slug);

        return $page ? get_permalink($page) : home_url('/' . $slug . '/');
    }

    private function isAuthPage(): ?string
    {
        if (! is_main_site() || ! is_page()) {
            return null;
        }
        foreach ([ 'login', 'register' ] as $slug) {
            if (is_page($slug)) {
                return $slug;
            }
        }

        return null;
    }

    private function destinationFor(\WP_User $user, string $requested): string
    {
        if ($requested !== '') {
            $safe = wp_validate_redirect($requested, '');
            if ($safe !== '') {
                return $safe;
            }
        }

        return user_can($user, 'edit_posts') && user_can($user, 'manage_options') ? admin_url() : home_url('/');
    }

    private function back(string $slug, string $error, string $redirect, string $prompt): void
    {
        $args = [ 'sf_auth_error' => $error ];
        if ($redirect !== '') {
            $args['redirect_to'] = $redirect;
        }
        if ($prompt !== '') {
            $args['prompt'] = $prompt;
        }
        wp_safe_redirect(add_query_arg($args, $this->pageUrl($slug)));
        exit;
    }

    // --- Redirects ----------------------------------------------------------

    public function redirectLoggedIn(): void
    {
        if (! is_user_logged_in() || $this->isAuthPage() === null) {
            return;
        }
        $requested = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : '';
        wp_safe_redirect($this->destinationFor(wp_get_current_user(), $requested));
        exit;
    }

    public function redirectWpLogin(): void
    {
        if (! is_main_site() || is_user_logged_in()) {
            return;
        }
        if ((defined('WP_CLI') && WP_CLI) || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            return;
        }
        $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : 'login';
        if ($action !== 'login' && $action !== '') {
            return;
        }
        // Interim (popup) logins and password-reset follow-ups keep the core screen.
        foreach ([ 'interim-login', 'checkemail', 'key', 'login_post' ] as $flag) {
            if (isset($_GET[ $flag ])) {
                return;
            }
        }

        $args = [];
        if (isset($_GET['redirect_to'])) {
            $args['redirect_to'] = esc_url_raw(wp_unslash($_GET['redirect_to']));
        }
        if (isset($_GET['loggedout'])) {
            $args['sf_auth_error'] = 'loggedout';
        }
        wp_safe_redirect(add_query_arg($args, $this->pageUrl('login')));
        exit;
    }

    // --- Handlers -----------------------------------------------------------

    private function postedRedirect(): string
    {
        return isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : '';
    }

    private function postedPrompt(): string
    {
        return isset($_POST['prompt']) ? $this->cleanPrompt(wp_unslash($_POST['prompt'])) : '';
    }

    private function cleanPrompt($value): string
    {
        $value = sanitize_textarea_field((string) $value);

        return function_exists('mb_substr') ? mb_substr($value, 0, 400) : substr($value, 0, 400);
    }

    public function handleLogin(): void
    {
        $redirect = $this->postedRedirect();
        $prompt   = $this->postedPrompt();

        if (is_user_logged_in()) {
            wp_safe_redirect($this->destinationFor(wp_get_current_user(), $redirect));
            exit;
        }
        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), self::ACTION_LOGIN)) {
            $this->back('login', 'nonce', $redirect, $prompt);
        }

        $login    = isset($_POST['log']) ? trim(sanitize_text_field(wp_unslash($_POST['log']))) : '';
        $password = isset($_POST['pwd']) ? (string) wp_unslash($_POST['pwd']) : '';
        if ($login === '' || $password === '') {
            $this->back('login', 'empty', $redirect, $prompt);
        }

        $user = wp_signon(
            [
                'user_login'    => $login,
                'user_password' => $password,
                'remember'      => ! empty($_POST['rememberme']),
            ],
            is_ssl()
        );
        if (is_wp_error($user)) {
            $this->back('login', 'invalid', $redirect, $prompt);
        }

        wp_safe_redirect($this->destinationFor($user, $redirect));
        exit;
    }

    /**
     * Accounts are never created here. Sign-up belongs to the site's own
     * registration flow (on the hosted network: Ultimate Multisite checkout at
     * /get-started/), so a post to this endpoint is sent there, idea and all.
     */
    public function handleRegister(): void
    {
        $redirect = $this->postedRedirect();
        $prompt   = $this->postedPrompt();

        if (is_user_logged_in()) {
            wp_safe_redirect($this->destinationFor(wp_get_current_user(), $redirect));
            exit;
        }

        wp_safe_redirect($this->withContext($this->signupUrl(), '', $prompt));
        exit;
    }

    /**
     * Where new accounts are made. Filter `swishfolio_auth_signup_url`
     * (swishfolio-extra points it at /get-started/); defaults to the site's
     * registration URL.
     */
    private function signupUrl(): string
    {
        $url = (string) apply_filters('swishfolio_auth_signup_url', wp_registration_url());

        return $url !== '' ? $url : home_url('/');
    }

    // --- Rendering ----------------------------------------------------------

    public function renderShortcode($atts): string
    {
        $atts = shortcode_atts([ 'form' => 'login' ], $atts, 'swishfolio_auth');
        $form = $atts['form'] === 'register' ? 'register' : 'login';

        $code   = isset($_GET['sf_auth_error']) ? sanitize_key(wp_unslash($_GET['sf_auth_error'])) : '';
        $notice = self::MESSAGES[ $code ] ?? '';
        $isInfo = $code === 'loggedout';

        $redirect = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : '';
        $prompt   = isset($_GET['prompt']) ? $this->cleanPrompt(wp_unslash($_GET['prompt'])) : '';

        ob_start();

        if ($prompt !== '') {
            echo '<div class="hp-auth__idea"><span>Your idea</span><p>' . esc_html($prompt) . '</p></div>';
        }
        if ($notice !== '') {
            printf(
                '<div class="hp-auth__notice%s" role="%s">%s</div>',
                $isInfo ? ' is-info' : '',
                $isInfo ? 'status' : 'alert',
                esc_html($notice)
            );
        }

        if ($form === 'register') {
            // No sign-up form here: accounts are created by the signup flow only.
            echo '<div class="hp-auth__closed"><h2>Create your site to get started.</h2><p>Your account is set up as part of choosing a plan and a design.</p>';
            echo '<a class="hp-auth__submit" href="' . esc_url($this->withContext($this->signupUrl(), '', $prompt)) . '">Get started</a>';
            echo '<p class="hp-auth__alt">Already have an account? <a href="' . esc_url($this->withContext($this->pageUrl('login'), $redirect, $prompt)) . '">Sign in</a></p></div>';

            return (string) ob_get_clean();
        }

        $action = self::ACTION_LOGIN;
        echo '<form class="hp-auth__form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" novalidate>';
        echo '<input type="hidden" name="action" value="' . esc_attr($action) . '">';
        wp_nonce_field($action, '_wpnonce', false);
        echo '<input type="hidden" name="redirect_to" value="' . esc_attr($redirect) . '">';
        echo '<input type="hidden" name="prompt" value="' . esc_attr($prompt) . '">';

        $this->field('log', 'Email or username', 'text', 'username');
        echo '<div class="hp-auth__field"><div class="hp-auth__row"><label for="sf-pwd">Password</label><a href="' . esc_url(wp_lostpassword_url()) . '">Forgot password?</a></div>';
        echo '<input id="sf-pwd" name="pwd" type="password" autocomplete="current-password" required></div>';
        echo '<label class="hp-auth__check"><input type="checkbox" name="rememberme" value="forever"> <span>Keep me signed in</span></label>';
        echo '<button class="hp-auth__submit" type="submit">Sign in</button>';
        echo '<p class="hp-auth__alt">New to Swishfolio? <a href="' . esc_url($this->withContext($this->signupUrl(), '', $prompt)) . '">Get started</a></p>';
        echo '</form>';

        return (string) ob_get_clean();
    }

    private function withContext(string $url, string $redirect, string $prompt): string
    {
        $args = array_filter([ 'redirect_to' => $redirect, 'prompt' => $prompt ]);

        return $args ? add_query_arg($args, $url) : $url;
    }

    private function field(string $name, string $label, string $type, string $autocomplete): void
    {
        printf(
            '<div class="hp-auth__field"><label for="sf-%1$s">%2$s</label><input id="sf-%1$s" name="%1$s" type="%3$s" autocomplete="%4$s" required></div>',
            esc_attr($name),
            esc_html($label),
            esc_attr($type),
            esc_attr($autocomplete)
        );
    }
}
