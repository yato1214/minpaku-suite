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
        // WordPress hooks initialization will be implemented in future milestones
    }

    private static function init_rest_api()
    {
        // REST API initialization will be implemented in future milestones
    }

    private static function init_acf()
    {
        // ACF (Advanced Custom Fields) initialization will be implemented in future milestones
    }
}