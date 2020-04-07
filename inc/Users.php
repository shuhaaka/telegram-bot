<?php
/**
 * Users class
 *
 * @since 1.0
 */

namespace tgrambot;

use WP_Error;
use WP_User;

defined('ABSPATH') || exit;

class Users extends Instance
{
    protected static $_instance;
    protected $TgramBot;
    private $_login_dry_run = false;

    public function init()
    {
        $this->TgramBot = TgramBot::getInstance();

        if ($this->TgramBot->get_option('telegram_bot_two_factor_auth', false)) {
            add_action('login_message', [$this, 'login_message']);
            add_action('login_enqueue_scripts', [$this, 'login_enqueue_scripts']);
            add_action('login_form', [$this, 'login_form']);
            add_action('woocommerce_login_form', [$this, 'login_enqueue_scripts']);
            add_action('woocommerce_login_form', [$this, 'login_form']);
            add_filter('authenticate', [$this, 'login_authenticate'], 30, 3);
        }
    }

    /**
     * Verify Telegram bot after u+p authenticated
     *
     * @copyright This code inspired by DoLogin plugin, https://wordpress.org/plugins/dologin
     * @since  1.0
     * @param null|WP_User|WP_Error $user WP_User if the user is authenticated.
     *                                        WP_Error or null otherwise.
     * @param string $username Username or email address.
     * @param string $password User password
     * @return WP_User|WP_Error
     */
    public function login_authenticate($user, $username, $password)
    {
        $TgramBot = $this->TgramBot;

        if ($this->_login_dry_run)
            return $user;

        if (empty($username) || empty($password))
            return $user;

        if (is_wp_error($user))
            return $user;

        // If telegram is optional and the user doesn't have linked account, bypass
        $bot_user = $TgramBot->set_user(array('wp_id' => $user->ID));
        if (!$bot_user && !$TgramBot->get_option('telegram_bot_force_two_factor_auth', false))
            return $user;

        $error = new WP_Error();

        // Validate dynamic code
        if (empty($_POST['wptp-two_factor_code'])) {
            $error->add('dynamic_code_missing', $TgramBot->words['dynamic_code_missing']);
            defined('TGRAM_LOGIN_ERR') || define('TGRAM_LOGIN_ERR', true);
            return $error;
        }

        $code = $TgramBot->get_user_meta('tfa_code', false);
        $expireTime = $TgramBot->get_user_meta('tfa_expire_time', false);

        if (!$code || !$expireTime || $code != $_POST['wptp-two_factor_code']) {
            $error->add('dynamic_code_not_correct', $TgramBot->words['dynamic_code_not_correct']);
            defined('TGRAM_LOGIN_ERR') || define('TGRAM_LOGIN_ERR', true);
            return $error;
        }

        if ($code == $_POST['wptp-two_factor_code'] && $expireTime < current_time('U')) {
            $error->add('dynamic_code_expired', $TgramBot->words['dynamic_code_expired']);
            defined('TGRAM_LOGIN_ERR') || define('TGRAM_LOGIN_ERR', true);
            return $error;
        }

        $TgramBot->update_user_meta('tfa_code', false);
        $TgramBot->update_user_meta('tfa_expire_time', false);

        return $user;
    }

    /**
     * Send dynamic code
     *
     * @copyright This code inspired by DoLogin plugin, https://wordpress.org/plugins/dologin
     * @since  1.0
     */
    public function TgramBotTFA()
    {
        $TgramBot = $this->TgramBot;

        if (!$this->TgramBot->get_option('telegram_bot_two_factor_auth', false)) {
            return REST::ok(array('bypassed' => 1));
        }

        $field_u = 'log';
        $field_p = 'pwd';
        if (isset($_POST['woocommerce-login-nonce'])) {
            $field_u = 'username';
            $field_p = 'password';
        }

        if (empty($_POST[$field_u]) || empty($_POST[$field_p])) {
            return REST::err($TgramBot->words['empty_username_password']);
        }

        // Verify u & p first
        $this->_login_dry_run = true;
        $user = wp_authenticate($_POST[$field_u], $_POST[$field_p]);
        $this->_login_dry_run = false;
        if (is_wp_error($user)) {
            return REST::err($user->get_error_message());
        }

        // Search if the user has linked Telegram account
        $bot_user = $TgramBot->set_user(array('wp_id' => $user->ID));
        if (!$bot_user) {
            if (!$TgramBot->get_option('telegram_bot_force_two_factor_auth', false)) {
                return REST::ok(array('bypassed' => 1));
            }
            return REST::err($TgramBot->words['no_linked_telegram_account']);
        }

        // Generate dynamic code
        $code = HelpersWPTP::randomStrings(5);
        $expireTime = strtotime('+10 minutes', current_time('U'));
        $message = __('Dynamic Code',TGRAMPRO_PLUGIN_KEY) . ': ' . $code;

        $updateCode = $TgramBot->update_user_meta('tfa_code', $code);
        $updateExpireTime = $TgramBot->update_user_meta('tfa_expire_time', $expireTime);
        $result = false;

        if ($updateCode && $updateExpireTime)
            $result = $this->send_notification($bot_user, $message, $user);

        // Expected response
        if ($result && isset($result['ok']) && $result['ok']) {
            $usernameStar = HelpersWPTP::string2Stars($bot_user['username']);
            $message = sprintf(__('Sent dynamic code to this Telegram account @%s.',TGRAMPRO_PLUGIN_KEY), $usernameStar);
            return REST::ok(array('info' => $message));
        }

        if ($result && isset($result['ok']) && !$result['ok']) {
            return REST::err($TgramBot->words['error_sending_message']);
        }

        return REST::err($TgramBot->words['unknown_error']);
    }

    /**
     * Enqueue js
     *
     * @since  1.0
     * @access public
     */
    public function login_enqueue_scripts()
    {
        $this->login_enqueue_style();
        $js_version = date("ymd-Gis", filemtime(TGRAMPRO_ASSETS_DIR . 'js' . DIRECTORY_SEPARATOR . 'login.js'));

        wp_register_script('wptp-login', TGRAMPRO_URL . '/assets/js/login.js', array('jquery'), $js_version, false);

        $localize_data = array();
        $localize_data['login_url'] = get_rest_url(null, 'tgrambot/v1/telegram_bot_auth');
        wp_localize_script('wptp-login', 'tgrambot_login', $localize_data);

        wp_enqueue_script('wptp-login');
    }

    /**
     * Load style
     * @since 1.0
     */
    public function login_enqueue_style()
    {
        wp_enqueue_style('wptp-login', TGRAMPRO_URL . '/assets/css/login.css', array(), TGRAMPRO_VERSION, 'all');
        wp_enqueue_style('wptp-icon', TGRAMPRO_URL . '/assets/css/icon.css', array('wptp-login'), TGRAMPRO_VERSION, 'all');
    }

    /**
     * Display login form
     * @copyright This code from "DoLogin Security" WordPress plugin, https://wordpress.org/plugins/dologin
     * @since  1.0
     * @access public
     */
    public function login_form()
    {
        $inputClass = apply_filters('tgrambot_input_class', '');
        $login_form = '<p id="wptp-login-process">' . __('Telegram Authentication',TGRAMPRO_PLUGIN_KEY) . ':
                            <span id="wptp-process-msg"></span>
                        </p>
                        <p id="wptp-dynamic_code">
                            <label for="wptp-two_factor_code">' . __('Dynamic Code',TGRAMPRO_PLUGIN_KEY) . ':</label>
                            <br /><input type="text" name="wptp-two_factor_code" id="wptp-two_factor_code" class="' . $inputClass . '" autocomplete="off" />
                        </p>';
        $login_form = apply_filters('tgrambot_tfa_login_form_output', $login_form);
        echo $login_form;
    }

    /**
     * Login default display messages
     *
     * @copyright This code from "DoLogin Security" WordPress plugin, https://wordpress.org/plugins/dologin
     * @since  1.0
     * @access public
     */
    public function login_message($msg)
    {
        if (defined('TGRAM_LOGIN_ERR'))
            return $msg;

        $msg .= '<div class="message wptp-login-message"><span class="dashicons-wptp-telegram"></span> <strong class="title">' . $this->TgramBot->plugin_name .
            '</strong><br>' . __('Two step Telegram bot authentication is on',TGRAMPRO_PLUGIN_KEY) . '</div>';
        return $msg;
    }

    /**
     * Send dynamic code notification
     *
     * @param array $bot_user name.
     * @param string $message Message.
     * @param WP_User $user WordPress User.
     * @return bool|array
     */
    function send_notification($bot_user, $message, $user)
    {
        if ($bot_user) {
            $telegram = $this->TgramBot->telegram;
            $text = "*" . sprintf(__('Dear %s',TGRAMPRO_PLUGIN_KEY), $user->display_name) . "*\n";
            $text .= $message;
            $text = apply_filters('tgrambot_two_factor_auth_notification_text', $text, $bot_user, $message, $user);

            if ($text) {
                $keyboard = array(array(
                    array(
                        'text' => __('Display website',TGRAMPRO_PLUGIN_KEY),
                        'url' => get_bloginfo('url')
                    )
                ));
                $keyboards = $telegram->keyboard($keyboard, 'inline_keyboard');
                $telegram->sendMessage($text, $keyboards, $bot_user['user_id'], 'Markdown');
                return $telegram->get_last_result();
            }
        }
        return false;
    }
}
