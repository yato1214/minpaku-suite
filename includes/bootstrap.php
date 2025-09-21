<?php

namespace MinpakuSuite;

if (!defined('ABSPATH')) {
    exit;
}

class Bootstrap
{
    public static function init()
    {
        self::init_hooks();
        self::init_rest_api();
        self::init_acf();
    }

    public static function activate()
    {
        // Activation logic will be implemented in future milestones
    }

    public static function deactivate()
    {
        // Deactivation logic will be implemented in future milestones
    }

    private static function init_hooks()
    {
        // Load CPT registration
        if (file_exists(MINPAKU_SUITE_PLUGIN_DIR . 'includes/cpt-property.php')) {
            require_once MINPAKU_SUITE_PLUGIN_DIR . 'includes/cpt-property.php';
        }
    }

    private static function init_rest_api()
    {
        // REST API initialization will be implemented in future milestones
    }

    private static function init_acf()
    {
        // Load ACF fields registration
        if (file_exists(MINPAKU_SUITE_PLUGIN_DIR . 'includes/Acf/RegisterFields.php')) {
            require_once MINPAKU_SUITE_PLUGIN_DIR . 'includes/Acf/RegisterFields.php';

            if (class_exists('MinpakuSuite\Acf\RegisterFields')) {
                add_action('acf/init', ['MinpakuSuite\Acf\RegisterFields', 'init']);
            }
        }
    }
}