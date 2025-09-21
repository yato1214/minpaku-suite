<?php
/**
 * Connector Bootstrap
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Connector;

if (!defined('ABSPATH')) {
    exit;
}

// Load Connector classes
require_once MCS_PATH . 'includes/Connector/ConnectorSettings.php';
require_once MCS_PATH . 'includes/Connector/ConnectorAuth.php';
require_once MCS_PATH . 'includes/Connector/ConnectorApiController.php';

// Initialize Connector components
ConnectorSettings::init();
ConnectorApiController::init();