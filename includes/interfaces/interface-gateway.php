<?php

namespace Texteller\Interfaces;

use Texteller\Message;

defined( 'ABSPATH' ) || exit;

interface Gateway {

	public function send( Message $message, array $action_gateway_data = [] );

	public function register_gateway_options();

	public static function add_options();

	public static function get_interfaces();

	public static function get_default_interface();

	public static function get_content_types();

	public static function is_interface_active( string $interface ) : bool;

	public static function get_interface_number( string $interface) : string;
}