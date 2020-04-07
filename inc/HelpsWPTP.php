<?php
namespace tgrambot;

class HelpsWPTP extends TgramBot
{
    public static $instance = null;
    protected $page_key, $page_title;

    public function __construct()
    {
        parent::__construct();
        $this->page_key = 'telegram-bot' . '-helps';
        $this->page_title = __('Helps', 'telegram-bot');
        add_action('admin_menu', array($this, 'menu'), 999998);
    }

    function menu()
    {
        add_submenu_page('telegram-bot', $this->plugin_name . $this->page_title_divider . $this->page_title, $this->page_title, 'manage_options', $this->page_key, array($this, 'pageContent'));
    }

    function pageContent()
    {
        ?>
        <div class="wrap wptp-wrap">
            <h1 class="wp-heading-inline"><?php echo $this->plugin_name . $this->page_title_divider . $this->page_title ?></h1>
            <div class="accordion-wptp">
                <?php do_action('tgrambot_helps_content'); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Returns an instance of class
     * @return  HelpsWPTP
     */
    static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new HelpsWPTP();
        return self::$instance;
    }
}

$HelpsWPTP = HelpsWPTP::getInstance();