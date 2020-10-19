<?php

namespace Texteller\Core_Modules\Newsletter;

defined( 'ABSPATH' ) || exit;

/**
 * Class Base Base Initialize newsletter module
 * @package Texteller\Core_Modules\Newsletter
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
	 * Initializes newsletter options and notifications
	 */
	public function init()
	{
		new Options();
		new Notifications();
		Registration::init();
	}
}