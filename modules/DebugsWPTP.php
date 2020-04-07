<?php
namespace tgrambot;

class DebugsWPTP extends TgramBot
{
    public static $instance = null;
    protected $page_key, $page_title, $telegramFilterCountry = ['IR', 'CN', 'RU'], $url;

    public function __construct()
    {
        parent::__construct();
        $this->page_key = 'telegram-bot' . '-debugs';
        $this->page_title = __('Debugs', 'telegram-bot');
        $this->url = get_bloginfo('url');
        add_action('admin_menu', array($this, 'menu'), 999999);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);

        add_filter('tgrambot_debugs_info', [$this, 'wptp_info'], 1);
        add_filter('tgrambot_debugs_info', [$this, 'php_info']);
        add_filter('tgrambot_debugs_info', [$this, 'wp_info']);
        add_filter('tgrambot_debugs_info', [$this, 'host_info']);
        add_filter('tgrambot_debugs_info', [$this, 'ssl_info']);
    }

    function admin_enqueue_scripts()
    {
        global $pagenow;
        if ($pagenow == 'admin.php' && isset($_GET['page']) && $_GET['page'] == 'wp-telegram-pro-debugs') {
            wp_enqueue_style('thickbox');
            wp_enqueue_script('thickbox');
        }
    }

    /**
     * Add menu to Telegram Bot main menu
     */
    function menu()
    {
        add_submenu_page('telegram-bot', $this->plugin_name . $this->page_title_divider . $this->page_title, $this->page_title, 'manage_options', $this->page_key, array($this, 'pageContent'));
    }

    /**
     * Add host info to debugs
     * @param $debugs string Debugs Info Array
     * @return string Debugs Info
     */
    function host_info($debugs)
    {
        // Host Info
        $domainInfo = new DomainInfoWPTP($this->url);
        $domainIP = $domainInfo->getIPAddress();
        if ($domainIP) {
            $domainCountry = $domainInfo->getLocation($domainIP);
            $countryCode = strtolower($domainCountry['countryCode']);
            $hostInfo = array(
                'IP' => $domainIP,
                __('Host Location', 'telegram-bot') => "<span class='ltr-right flex'><img src='https://www.countryflags.io/{$countryCode}/flat/16.png' alt='{$domainCountry['countryName']} Flag'> &nbsp;" . $domainCountry['countryCode'] . ' - ' . $domainCountry['countryName'] . '</span>'
            );
            if (in_array($domainCountry['countryCode'], $this->telegramFilterCountry))
                $hostInfo[__('Tip', 'telegram-bot')] = __('Your website host location on the list of countries that have filtered the telegram. For this reason, the plugin may not work well. My suggestion is to use a host of another countries.', 'telegram-bot');
            $debugs[__('Host', 'telegram-bot')] = $hostInfo;
        }
        return $debugs;
    }

    /**
     * Add SSL info to debugs
     * @param $debugs string Debugs Info Array
     * @return string Debugs Info
     */
    function ssl_info($debugs)
    {
        $ssl = is_ssl() ? $this->words['active'] : $this->words['inactive'];

        $debugs['SSL'] = array(
            __('Status', 'telegram-bot') => $ssl,
        );

        // SSL Info
        if (is_ssl()) {
            $ssl_info = array();
            $info = $this->checkSSLCertificate($this->url);

            if (is_array($info)) {
                $ssl_info[__('Issuer', 'telegram-bot')] = $info['issuer'];
                $ssl_info[__('Valid', 'telegram-bot')] = $info['isValid'] ? __('Yes', 'telegram-bot') : __('No', 'telegram-bot');

                $validFromDate = HelpersWPTP::localeDate($info['validFromDate']);
                $ssl_info[__('Valid from', 'telegram-bot')] = "<span class='ltr-right'>" . $info['validFromDate'] . ($info['validFromDate'] != $validFromDate ? " / {$validFromDate}" : '') . "</span>";

                $expirationDate = HelpersWPTP::localeDate($info['expirationDate']);
                $ssl_info[__('Valid until', 'telegram-bot')] = "<span class='ltr-right'>" . $info['expirationDate'] . ($info['expirationDate'] != $expirationDate ? " / {$expirationDate}" : '') . "</span>";

                $ssl_info[__('Is expired', 'telegram-bot')] = $info['isExpired'] ? __('Yes', 'telegram-bot') : __('No', 'telegram-bot');
                $ssl_info[__('Remaining days to expiration', 'telegram-bot')] = $info['daysUntilExpirationDate'];
                $ssl_info[__('Key', 'telegram-bot')] = $info['signatureAlgorithm'];
            } elseif (is_string($info))
                $ssl_info[__('SSL Info', 'telegram-bot')] = $info;

            $debugs['SSL'] = array_merge($debugs['SSL'], $ssl_info);
        } else {
            $debugs['SSL'][__('Tip', 'telegram-bot')] = $this->words['ssl_error'];
        }
        return $debugs;
    }

    /**
     * Add WordPress info to debugs
     * @param $debugs string Debugs Info Array
     * @return string Debugs Info
     */
    function wp_info($debugs)
    {
        global $wp_version;
        $debug = defined('WP_DEBUG') ? WP_DEBUG : false;
        $debugMode = $debug ? $this->words['active'] : $this->words['inactive'];
        $language = get_bloginfo('language');
        $charset = get_bloginfo('charset');
        $text_direction = is_rtl() ? 'RTL' : 'LTR';
        $debugs[__('WordPress')] = array(
            __('Version', 'telegram-bot') => $wp_version,
            __('Debugging Mode', 'telegram-bot') => $debugMode,
            __('Address', 'telegram-bot') => get_bloginfo('url'),
            __('Language', 'telegram-bot') => $language,
            __('Character encoding', 'telegram-bot') => $charset,
            __('Text Direction', 'telegram-bot') => $text_direction
        );
        if (version_compare($wp_version, '5.2', '>='))
            $debugs[__('WordPress')][__('Site Health', 'telegram-bot')] = '<a href="' . admin_url('site-health.php') . '">' . __('Check site health page', 'telegram-bot') . '</a>';
        return $debugs;
    }

    /**
     * Add PHP info to debugs
     * @param $debugs string Debugs Info Array
     * @return string Debugs Info
     */
    function php_info($debugs)
    {
        $phpversion = phpversion();
        $curl = function_exists('curl_version') ? curl_version()['version'] : $this->words['inactive'];
        $debugs['PHP'] = array(
            __('PHP Version', 'telegram-bot') => $phpversion,
            __('PHP CURL', 'telegram-bot') => $curl
        );
        return $debugs;
    }

    /**
     * Add WP Telegram Pro info to debugs
     * @param $debugs string Debugs Info Array
     * @return string Debugs Info
     */
    function wptp_info($debugs)
    {
        global $wpdb;
        $checkDBTable = $wpdb->get_var("show tables like '$this->db_users_table'") === $this->db_users_table;
        $checkDBTable = $checkDBTable ? $this->words['yes'] : $this->words['no'];
        $debugs[$this->plugin_name] = array(
            __('Plugin Version', 'telegram-bot') => WPTELEGRAMPRO_VERSION,
            __('Plugin DB Table Created', 'telegram-bot') => $checkDBTable
        );

        if ($updateInfo = $this->check_update_plugin())
            $debugs[$this->plugin_name][__('Update', 'telegram-bot')] = '<a href="' . $updateInfo['updateDetailURL'] . '" class="thickbox open-plugin-details-modal">' . __('Update to the new version', 'telegram-bot') . ' ' . $updateInfo['newVersion'] . '</a>';

        return $debugs;
    }

    protected function check_update_plugin($file = null)
    {
        if ($file == null) $file = 'wp-telegram-pro/WPTelegramPro.php';
        $plugin_updates = get_plugin_updates();
        if (in_array($file, array_keys($plugin_updates)))
            return array(
                'name' => $plugin_updates[$file]->Name,
                'newVersion' => $plugin_updates[$file]->update->new_version,
                'updateURL' => wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin=') . $file, 'upgrade-plugin_' . $file),
                'updateDetailURL' => self_admin_url('plugin-install.php?tab=plugin-information&plugin=' . $plugin_updates[$file]->update->slug . '&section=changelog&TB_iframe=true&width=772&height=367')
            );
        return false;
    }

    /**
     * Debugs page content
     */
    function pageContent()
    {
        $debugs = apply_filters('tgrambot_debugs_info', []);
        ?>
        <div class="wrap wptp-wrap">
            <h1 class="wp-heading-inline"><?php echo $this->plugin_name . $this->page_title_divider . $this->page_title ?></h1>
            <table class="table table-light table-th-bold table-bordered">
                <tbody>
                <?php
                foreach ($debugs as $key => $debug) {
                    echo '<tr><th colspan="2">' . $key . '</th></tr>';
                    foreach ($debug as $title => $value) {
                        echo '<tr><td>' . $title . '</td><td>' . $value . '</td></tr>';
                    }
                }
                ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Check SSL Certificate
     * @param $host string URL
     * @return boolean|array
     */
    function checkSSLCertificate($host)
    {
        if (!is_ssl() || !class_exists('tgrambot\SSLCertificateWPTP')) return false;
        try {
            $SSLCertificate = new SSLCertificateWPTP($host);
            return $SSLCertificate->request()->response();
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Returns an instance of class
     * @return  DebugsWPTP
     */
    static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new DebugsWPTP();
        return self::$instance;
    }
}

$DebugsWPTP = DebugsWPTP::getInstance();