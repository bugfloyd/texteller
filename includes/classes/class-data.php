<?php
namespace Texteller;
defined( 'ABSPATH' ) || exit;

abstract class Data {

	protected $id = 0;

	protected $data = [];

	protected $changes = [];

	/**
	 * Is object read from the DB
	 *
	 * @var bool
	 */
	protected $object_read = false;

	/**
	 * @var Interfaces\Object_Data_Store
	 */
	protected $data_store;

	/**
	 * Get the data store.
	 *
	 * @return object
	 */
	public function get_data_store()
	{
		return $this->data_store;
	}

	/**
	 * Returns the ID for this object.
	 *
	 * @return int
	 */
	public function get_id()
	{
		return $this->id;
	}

	/**
	 * Set ID.
	 *
	 * @param int $id ID.
	 */
	public function set_id( $id )
	{
		$this->id = absint( $id );
	}


	/**
	 * Returns all data for this object.
	 *
	 * @return array
	 */
	public function get_data()
	{
		return array_merge( [ 'id' => $this->get_id() ], $this->data );
	}


	/**
	 * Delete an object, set the ID to 0, and return result.
	 *
	 * @return bool result
	 */
	public function delete()
	{
		if ( $this->data_store ) {
			$deleted = $this->data_store->delete( $this );
			if ( $deleted ) {
				$this->set_id( 0 );
				return true;
			}
		}
		return false;
	}

	/**
	 * Save should create or update based on object existence.
	 *
	 * @return int | bool
	 */
	public function save()
	{
		if ( ! $this->data_store ) {
			return $this->get_id();
		}

		if ( $this->get_id() ) {
			$result = $this->data_store->update( $this );
		} else {
			$result = $this->data_store->create( $this );
		}

		return $result ? $this->get_id() : false;
	}

	/**
	 * Set a collection of props in one go, collect any errors, and return the result.
	 * Only sets using public methods.
	 *
	 * @param array  $props Key value pairs to set. Key is the prop and should map to a setter function name.
	 *
	 * @return bool|\WP_Error
	 */
	public function set_props( $props ) {
		$errors = false;

		foreach ( $props as $prop => $value ) {
			try {
				/**
				 * Checks if the prop being set is allowed, and the value is not null.
				 */
				if ( is_null( $value ) || in_array( $prop, array( 'prop', 'date_prop', 'meta_data' ), true ) ) {
					continue;
				}
				$setter = "set_$prop";

				if ( is_callable( array( $this, $setter ) ) ) {
					$this->{$setter}( $value );
				}
			} catch ( \Exception $e ) {
				if ( ! $errors ) {
					$errors = new \WP_Error();
				}
				$errors->add( $e->getCode(), $e->getMessage() );
			}
		}

		return count( $errors->get_error_codes() ) ? $errors : true;
	}

	/**
	 * Return data changes only.
	 *
	 * @return array
	 */
	public function get_changes() {
		return $this->changes;
	}

	/**
	 * Merge changes with data and clear.
	 */
	public function apply_changes()
	{
		$this->data    = array_replace_recursive( $this->data, $this->changes );
		$this->changes = [];
	}

	/**
	 * Gets a prop for a getter method.
	 * Gets the value from either current pending changes, or the data itself.
	 *
	 * @param  string $prop Name of prop to get.
	 * @return mixed
	 */
	protected function get_prop( $prop ) {
		$value = null;

		if ( array_key_exists( $prop, $this->data ) ) {
			$value = array_key_exists( $prop, $this->changes ) ? $this->changes[ $prop ] : $this->data[ $prop ];
		}

		return $value;
	}

	/**
	 * Sets a prop for a setter method.
	 *
	 * This stores changes in a special array so we can track what needs saving
	 * to the DB later.
	 *
	 * @param string $prop Name of prop to set.
	 * @param mixed  $value Value of the prop.
	 */
	public function set_prop( $prop, $value )
	{
		if ( array_key_exists( $prop, $this->data ) ) {
			if ( $this->get_object_read() ) {
				if ( $value !== $this->data[ $prop ] || array_key_exists( $prop, $this->changes ) ) {
					$this->changes[ $prop ] = $value;
				}
			} else {
				$this->data[ $prop ] = $value;
			}
		}
	}

	/**
	 * Set object read property.
	 *
	 * @param boolean $read Should read?.
	 */
	public function set_object_read( $read = true ) {
		$this->object_read = (bool) $read;
	}

	/**
	 * Get object read property.
	 *
	 * @return boolean
	 */
	public function get_object_read() {
		return $this->object_read;
	}
}