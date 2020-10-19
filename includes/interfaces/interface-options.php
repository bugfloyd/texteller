<?php

namespace Texteller\Interfaces;

use Texteller\Tags;

defined( 'ABSPATH' ) || exit;

interface Options {

	/**
	 * Initializes option hooks for the current module
	 * Called in modules' constructor
	 */
	public function init_option_hooks();

	/**
	 * Adds option tabs of the current module to plugin's option page.
	 *
	 * @param array $tabs Array of option tabs. Each option tab is an as associative array with
	 * a 'slug' and a 'title' keys and their values
	 *
	 * @return array Option tabs
	 */
	public static function add_option_pages( array $tabs ) : array;

	/**
	 * Makes aa new instance of Tags class with the tag types of the current module
	 * This tags would be used in notification trigger options of the module
	 *
	 * @return Tags
	 */
	public static function get_option_tags() : Tags;

	/**
	 * Registers current tab's sections and options
	 */
	public function register_module_options();

	/**
	 * Registers option sections of the current module
	 */
	public static function register_sections();
}