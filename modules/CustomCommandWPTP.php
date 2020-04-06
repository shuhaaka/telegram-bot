<?php

namespace telegrambot;
if (!defined('ABSPATH')) exit;

class CustomCommandWPT extends TelegramBot
{
    public static $instance = null;

    public function __construct()
    {
        parent::__construct();
    }

    function settings()
    {

    }

    /**
     * Returns an instance of class
     * @return CustomCommandWPT
     */
    static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new CustomCommandWPT();
        return self::$instance;
    }
}