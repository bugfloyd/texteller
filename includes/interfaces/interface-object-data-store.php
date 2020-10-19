<?php

namespace Texteller\Interfaces;
use Texteller;

defined( 'ABSPATH' ) || exit;

interface Object_Data_Store {

	/**
	 * Method to create a new record of a TLR_Data based object.
	 *
	 * @param Texteller\Data $data Data object.
	 *
	 * @return bool
	 */
	public function create( &$data );

	/**
	 * Method to read a record. Creates a new TLR_Data based object.
	 *
	 * @param Texteller\Data $data Data object.
	 *
	 * @return bool
	 */
	public function read( &$data );

	/**
	 * Updates a record in the database.
	 *
	 * @param Texteller\Data $data Data object.
	 *
	 * @return bool
	 */
	public function update( &$data );

	/**
	 * Deletes a record from the database.
	 *
	 * @param  Texteller\Data  $data Data object.
	 *
	 * @return bool     result
	 */
	public function delete( &$data );
}