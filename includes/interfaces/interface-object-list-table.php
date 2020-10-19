<?php

namespace Texteller\Interfaces;
defined( 'ABSPATH' ) || exit;

interface Object_List_Table {


	/**
	 * Note: Table bulk_actions can be identified by checking $_REQUEST['action'] and $_REQUEST['action2']
	 * action - is set if checkbox from top-most select-all is set, otherwise returns -1
	 * action2 - is set if checkbox the bottom-most select-all checkbox is set, otherwise returns -1
	 *
	 * @param string $sendback
	 * @return null|string
	 */
	public function handle_table_actions( string $sendback );
	public function display_header();
	public function display_table();
}