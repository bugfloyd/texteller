<?php

namespace Texteller\Core_Modules\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Class Base Initializes WordPress module
 * @package Texteller\Core_Modules\WordPress
 */
class Base {

	/**
	 * Base constructor.
	 */
	public function __construct()
	{
		$this->init();
	}

	/**
	 * Initializes WordPress options and notifications
	 */
	public function init()
	{
		new Options();
		new Notifications();
		Registration::init();
	}
}