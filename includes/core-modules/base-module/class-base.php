<?php

namespace Texteller\Core_Modules\Base_Module;

defined( 'ABSPATH' ) || exit;

/**
 * Class Base
 * Base class to implement cross-module options and notifications
 * @package Texteller\Core_Modules\Base_Module
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
	 * Initializes base options and notifications
	 */
	public function init()
	{
		new Options();
		new Notifications();
	}
}