<?php
/*
 * Plugin Name: Texteller
 * Plugin URI: https://www.texteller.com/
 Description: An all-in-one text message integration solution for WordPress and popular third-party plugins, supporting multiple SMS and messaging gateways.
 * Version: 0.1.3
 * Author: Yashar Hosseinpour
 * Text Domain: texteller
 * Requires at least: 5.3
 * Tested up to: 5.4.1
 * Requires PHP: 7.1
 * WC requires at least: 3.7
 * WC tested up to: 4.2.0
 */

namespace Texteller;

if (!defined("ABSPATH")) {
    exit();
} // Exit if accessed directly

// Define TLR_PLUGIN_FILE.
if (!defined("TLR_PLUGIN_FILE")) {
    define("TLR_PLUGIN_FILE", __FILE__);
}
if (!class_exists("Texteller\Texteller")) {
    require_once dirname(__FILE__) . "/includes/class-texteller.php";
}

function TLR(): void
{
    Texteller::getInstance();
}
TLR();
