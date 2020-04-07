<?php
namespace tgrambot;

class ProxyWPTP extends TgramBot
{
    public static $instance = null;
    private static $proxy;
    protected $tabID = 'proxy-wptp-tab';

    public function __construct()
    {
        parent::__construct();

        add_filter('tgrambot_settings_tabs', [$this, 'settings_tab'], 35);
        add_action('tgrambot_settings_content', [$this, 'settings_content']);
        add_action('tgrambot_helps_content', [$this, 'helps_google_proxy']);
        add_filter('tgrambot_image_send_mode', [$this, 'image_send_mode'], 35);
        add_filter('tgrambot_proxy_status', [$this, 'proxy_status'], 35);

        $this->setup_proxy();
    }

    function proxy_status($status)
    {
        return $this->get_option('proxy_status', $status);
    }

    function image_send_mode($mode)
    {
        $proxy_status = $this->get_option('proxy_status');
        if (!empty($proxy_status))
            $mode = 'image';
        return $mode;
    }

    /**
     * @copyright Base on WPTelegram_Proxy_Handler class in WP Telegram plugin, https://wordpress.org/plugins/wptelegram
     */
    function setup_proxy()
    {
        $proxy_status = $this->get_option('proxy_status');

        if (empty($proxy_status)) return;

        if ($proxy_status === 'google_script') {
            $google_script_url = $this->get_option('google_script_url');
            if (!empty($google_script_url)) {
                add_filter('tgrambot_api_remote_post_args', [$this, 'google_script_request_args'], 10, 3);
                add_filter('tgrambot_api_request_url', [$this, 'google_script_request_url']);
            }

        } elseif ($proxy_status === 'php_proxy')
            $this->setup_php_proxy();
    }

    /**
     * Setup PHP proxy
     * @copyright Base on WPTelegram_Proxy_Handler class in WP Telegram plugin, https://wordpress.org/plugins/wptelegram
     */
    private function setup_php_proxy()
    {
        $defaults = array(
            'host' => '',
            'port' => '',
            'type' => '',
            'username' => '',
            'password' => ''
        );

        // get the values from settings/defaults
        foreach ($defaults as $key => $value)
            self::$proxy[$key] = $this->get_option('proxy_' . $key, '');

        // modify curl
        add_action('http_api_curl', [$this, 'modify_http_api_curl'], 10, 3);
    }

    /**
     * Returns The proxy options
     * @copyright Base on WPTelegram_Proxy_Handler class in WP Telegram plugin, https://wordpress.org/plugins/wptelegram
     * @return array
     */
    private static function get_proxy()
    {
        return (array)apply_filters('tgrambot_api_curl_proxy', self::$proxy);
    }

    /**
     * Modify cURL handle
     * The method is not used by default but can be used to modify the behavior of cURL requests
     * @copyright Base on WPTelegram_Proxy_Handler class in WP Telegram plugin, https://wordpress.org/plugins/wptelegram
     *
     * @param resource $handle The cURL handle (passed by reference).
     * @param array $r The HTTP request arguments.
     * @param string $url The request URL.
     *
     * @return string
     * @since 1.0.0
     *
     */
    public function modify_http_api_curl(&$handle, $r, $url)
    {
        if ($this->check_remote_post($r, $url)) {
            foreach (self::get_proxy() as $option => $value)
                ${'proxy_' . $option} = apply_filters("tgrambot_api_curl_proxy_{$option}", $value);

            if (!empty($proxy_host) && !empty($proxy_port)) {
                if (!empty($proxy_type))
                    curl_setopt($handle, CURLOPT_PROXYTYPE, constant($proxy_type));
                curl_setopt($handle, CURLOPT_PROXY, $proxy_host);
                curl_setopt($handle, CURLOPT_PROXYPORT, $proxy_port);

                if (!empty($proxy_username) && !empty($proxy_password)) {
                    $authentication = $proxy_username . ':' . $proxy_password;
                    curl_setopt($handle, CURLOPT_PROXYAUTH, CURLAUTH_ANY);
                    curl_setopt($handle, CURLOPT_PROXYUSERPWD, $authentication);
                }
            }
        }
    }

    /**
     * @copyright Base on WPTelegram_Proxy_Handler class in WP Telegram plugin, https://wordpress.org/plugins/wptelegram
     */
    public static function google_script_request_args($args, $method, $token)
    {
        $args['body'] = array(
            'bot_token' => $token,
            'method' => $method,
            'args' => json_encode($args['body']),
        );
        $args['method'] = 'GET';

        return $args;
    }

    /**
     * @copyright Base on WPTelegram_Proxy_Handler class in WP Telegram plugin, https://wordpress.org/plugins/wptelegram
     */
    public function google_script_request_url($url)
    {
        return $this->get_option('google_script_url', $url);
    }

    function settings_tab($tabs)
    {
        $tabs[$this->tabID] = __('Proxy', 'telegram-bot');
        return $tabs;
    }

    function settings_content()
    {
        $this->options = get_option('telegram-bot');
        $proxy_status = $this->get_option('proxy_status');
        $proxy_type = $this->get_option('proxy_type');
        ?>
        <div id="<?php echo $this->tabID ?>-content" class="wptp-tab-content hidden">
            <table>
                <tr>
                    <th><?php _e('DISCLAIMER', 'telegram-bot') ?></th>
                    <td><?php _e('Use the proxy at your own risk!', 'telegram-bot') ?></td>
                </tr>
                <tr>
                    <td><?php _e('Proxy', 'telegram-bot') ?></td>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" value=""
                                       name="proxy_status" <?php checked($proxy_status, '') ?>> <?php _e('Inactive', 'telegram-bot') ?>
                            </label>
                            <label>
                                <input type="radio" value="google_script"
                                       name="proxy_status" <?php checked($proxy_status, 'google_script') ?>> <?php _e('Google Script', 'telegram-bot') ?>
                            </label>
                            <label>
                                <input type="radio" value="php_proxy"
                                       name="proxy_status" <?php checked($proxy_status, 'php_proxy') ?>> <?php _e('PHP Proxy', 'telegram-bot') ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>

            <table id="proxy_google_script"
                   class="proxy-status-wptp" <?php echo $proxy_status != 'google_script' ? 'style="display: none"' : '' ?>>
                <tr>
                    <td>
                        <label for="google_script_url"><?php _e('Google Script URL', 'telegram-bot') ?></label>
                    </td>
                    <td>
                        <input type="url" name="google_script_url" id="google_script_url"
                               value="<?php echo $this->get_option('google_script_url') ?>"
                               class="regular-text ltr"><br>
                        <span class="description"> &nbsp;<?php _e('The requests to Telegram will be sent via your Google Script.', 'telegram-bot') ?>
                        (<?php _e('See help page', 'telegram-bot') ?>)
                        </span>
                    </td>
                </tr>
            </table>

            <table id="proxy_php_proxy"
                   class="proxy-status-wptp" <?php echo $proxy_status != 'php_proxy' ? 'style="display: none"' : '' ?>>
                <tr>
                    <td>
                        <label for="proxy_host"><?php _e('Proxy Host', 'telegram-bot') ?></label>
                    </td>
                    <td>
                        <input type="text" name="proxy_host" id="proxy_host"
                               value="<?php echo $this->get_option('proxy_host') ?>"
                               class="regular-text ltr">
                        <span class="description"> &nbsp;<?php _e('Host IP or domain name like 192.168.55.124', 'telegram-bot') ?></span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="proxy_port"><?php _e('Proxy Port', 'telegram-bot') ?></label>
                    </td>
                    <td>
                        <input type="text" name="proxy_port" id="proxy_port"
                               value="<?php echo $this->get_option('proxy_port') ?>"
                               class="small-text ltr">
                        <span class="description"> &nbsp;<?php _e('Target Port like 8080', 'telegram-bot') ?></span>
                    </td>
                </tr>
                <tr>
                    <td><?php _e('Proxy Type', 'telegram-bot') ?></td>
                    <td>
                        <fieldset class="ltr-right">
                            <label>
                                <input type="radio" value="CURLPROXY_HTTP"
                                       name="proxy_type" <?php checked($proxy_type == 'CURLPROXY_HTTP' || $proxy_type == '' ? true : false) ?>> <?php _e('HTTP', 'telegram-bot') ?>
                            </label>
                            <label>
                                <input type="radio" value="CURLPROXY_SOCKS4"
                                       name="proxy_type" <?php checked($proxy_type, 'CURLPROXY_SOCKS4') ?>> <?php _e('SOCKS4', 'telegram-bot') ?>
                            </label>
                            <label>
                                <input type="radio" value="CURLPROXY_SOCKS4A"
                                       name="proxy_type" <?php checked($proxy_type, 'CURLPROXY_SOCKS4A') ?>> <?php _e('SOCKS4A', 'telegram-bot') ?>
                            </label>
                            <label>
                                <input type="radio" value="CURLPROXY_SOCKS5"
                                       name="proxy_type" <?php checked($proxy_type, 'CURLPROXY_SOCKS5') ?>> <?php _e('SOCKS5', 'telegram-bot') ?>
                            </label>
                            <label>
                                <input type="radio" value="CURLPROXY_SOCKS5_HOSTNAME"
                                       name="proxy_type" <?php checked($proxy_type, 'CURLPROXY_SOCKS5_HOSTNAME') ?>> <?php _e('SOCKS5_HOSTNAME', 'telegram-bot') ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="proxy_username"><?php _e('Username', 'telegram-bot') ?></label>
                    </td>
                    <td>
                        <input type="text" name="proxy_username" id="proxy_username"
                               value="<?php echo $this->get_option('proxy_username') ?>"
                               class="regular-text ltr">
                        <span class="description"> &nbsp;<?php _e('Leave empty if not required', 'telegram-bot') ?></span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="proxy_password"><?php _e('Password', 'telegram-bot') ?></label>
                    </td>
                    <td>
                        <input type="password" name="proxy_password" id="proxy_password"
                               value="<?php echo $this->get_option('proxy_password') ?>"
                               class="regular-text ltr">
                        <span class="description"> &nbsp;<?php _e('Leave empty if not required', 'telegram-bot') ?></span>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * @copyright Help base on @manzoorwanijk gist for WP Telegram plugin, https://gist.github.com/manzoorwanijk/ee9ed032caedf2bb0c83dea73bc9a28e
     */
    function helps_google_proxy()
    {
        ?>
        <div class="item">
            <button class="toggle" type="button"> <?php _e('Google Script', 'telegram-bot') ?></button>
            <div class="panel">
                <div>
                    <strong>
                        <?php _e('You can use this script to bypass the bans on Telegram API by different hosts. Simply send the request to this script instead of the Telegram Bot API after deploying it as a web app and allowing anonymous access.', 'telegram-bot'); ?>
                    </strong>
                    <br><br>
                    <strong><?php _e('How to Deploy', 'telegram-bot') ?></strong>
                    <ol>
                        <li><?php printf(__('Goto <a href="%s">script.google.com</a> and sign in if required'), 'https://script.google.com'); ?></li>
                        <li><?php _e('Create a new project and give it a name', 'telegram-bot'); ?></li>
                        <li><?php _e('It should open a file (Code.gs by default). Remove the contents of this file', 'telegram-bot'); ?></li>
                        <li><?php _e('Copy the contents of below code and paste into your project file (Code.gs)', 'telegram-bot'); ?></li>
                        <li><?php _e('Click File > Save or press Ctrl+S', 'telegram-bot'); ?></li>
                        <li><?php _e('Click "Publish" from the menu and select "Deploy as web app..."', 'telegram-bot'); ?></li>
                        <li><?php _e('If asked, Enter any name for the project and click "OK"', 'telegram-bot'); ?></li>
                        <li><?php _e('In "Execute the app as:", select "Me (your email)" [IMPORTANT]', 'telegram-bot'); ?></li>
                        <li><?php _e('In "Who has access to the app:", select "Anyone, even anonymous" [IMPORTANT]', 'telegram-bot'); ?></li>
                        <li><?php _e('Click "Deploy" to open the Authorization box', 'telegram-bot'); ?></li>
                        <li><?php _e('Click "Review Permissions" to authorize the script', 'telegram-bot'); ?></li>
                        <li><?php _e('In the popup window select your Google Account', 'telegram-bot'); ?></li>
                        <li><?php _e('On the next screen, click "Allow"', 'telegram-bot'); ?></li>
                        <li><?php _e('After redirection, you should see "This project is now deployed as a web app"', 'telegram-bot'); ?></li>
                        <li><?php _e('Copy the "Current web app URL" and paste it in plugin', 'telegram-bot'); ?></li>
                    </ol>
                    </span>
                    <textarea cols="30" class="ltr" rows="5"
                              onfocus="this.select();" onmouseup="return false;" readonly>function doGet(e) {
  if(typeof e !== 'undefined'){
    return requestHandler(e);
  }
}

function doPost(e) {
  if(typeof e !== 'undefined'){
    return requestHandler(e);
  }
}

function requestHandler(e){
  var res = handleRequest(e);
  return ContentService.createTextOutput(res);
}

function handleRequest(e) {
  if(typeof e.parameter.bot_token === 'undefined'){
    return 'Error! Bot token not provided';
  } else if(typeof e.parameter.method === 'undefined') {
    return 'Error! Method name not provided';
  }
  var bot_token = e.parameter.bot_token;
  var tg_method = e.parameter.method;
  
  var data = {
    "method": "post",
    "muteHttpExceptions": true
  }
  if(typeof e.parameter.args !== 'undefined'){
    var args = e.parameter.args;
    data.payload = JSON.parse(args);
  }
  var res = UrlFetchApp.fetch('https://api.telegram.org/bot' + bot_token + '/' + tg_method, data);
  return res.getContentText();
}</textarea>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Returns an instance of class
     * @return  ProxyWPTP
     */
    static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new ProxyWPTP();
        return self::$instance;
    }
}

$ProxyWPTP = ProxyWPTP::getInstance();