<?php
namespace Texteller;
defined( 'ABSPATH' ) || exit;


class Message_Data_Store implements Interfaces\Object_Data_Store {

	/*
	|--------------------------------------------------------------------------
	| CRUD Methods
	|--------------------------------------------------------------------------
	*/

	/**
	 * @param Message $message
	 *
	 * @return bool|int
	 */
	public function create( &$message )
	{
		if ( $message->get_id() > 0 ) {
			return false;
		}

		if (
			! $message->get_trigger()
			|| ! $message->get_gateway()
			|| ! $message->get_interface()
			|| ! $message->get_recipient()
			|| ! $message->get_content()
		) {
			return false;
		}

		if ( ! $message->get_date() ) {
			$message->set_date( current_time( 'mysql', true ) );
		}

		global $wpdb;
		$db_prefix = $wpdb->prefix;

		$save = $wpdb->insert(
			$db_prefix . 'tlr_messages',
			[
				'message_trigger'           =>  $message->get_trigger(),
				'message_gateway'           =>  $message->get_gateway(),
				'message_interface'         =>  $message->get_interface(),
				'message_interface_number'  =>  $message->get_interface_number(),
				'message_recipient'         =>  $message->get_recipient(),
				'message_date'              =>  $message->get_date(),
				'message_content'           =>  is_array( $message->get_content() ) ? serialize( $message->get_content() ) : $message->get_content(),
				'message_status'            =>  $message->get_status(),
				'message_gateway_data'      =>  serialize($message->get_gateway_data()),
				'message_member_id'         =>  $message->get_member_id()
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' ]
		);

		$possible_new_message_id = $wpdb->insert_id;

		if ( 1 === $save && $possible_new_message_id > 0 ) {
			$message->set_id( $wpdb->insert_id );
			$message->apply_changes();
			$message->set_object_read(true);

			return $possible_new_message_id;
		} else {
			return false;
		}
	}

	/**
	 * @param Message $message
	 *
	 * @return bool
	 */
	public function read( &$message )
	{
		global $wpdb;
		$id = $message->get_id();

		if ( ! $id > 0 ) {
			return false;
		}

		$table_name = $wpdb->prefix . 'tlr_messages';
		$message_data = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE ID = %d", $id ), ARRAY_A  );

		if ( count($message_data) != 1 ) {
			return false;
		}

		$message_data = $message_data[0];
		$message_data['message_gateway_data'] = unserialize( $message_data['message_gateway_data'] );
		$message->set_message_data( $message_data );
		$message->set_object_read( true );

		return true;
	}

	/**
	 * @param Message $message
	 *
	 * @return bool
	 */
	public function update( &$message )
	{
		global $wpdb;
		$db_prefix = $wpdb->prefix;
		$id = $message->get_id();
		$changes = $message->get_changes();

		if ( ! is_array($changes) || ! $id ) {
			return false;
		}
		if ( empty($changes) ) {
			return $id;
		}

		$update = $wpdb->update(
			$db_prefix . 'tlr_messages',
			[
				'message_status'            => $message->get_status(),
				'message_gateway_data'      => serialize( $message->get_gateway_data() ),
				'message_interface_number'  =>  $message->get_interface_number()
			],
			[
				'ID'    =>  $id
			],
			'%s',
			'%d'
		);

		if ( $update === 1 ) {
			$message->apply_changes();
			return true;
		} else {
			return false;
		}
	}

	/**
	 * @param Message $message*
	 *
	 * @return bool
	 */
	public function delete( &$message )
	{
		global $wpdb;
		$table_name =  $wpdb->prefix . 'tlr_messages';
		$id = $message->get_id();

		if ( ! $id ) {
			return false;
		}

		$delete = $wpdb->delete(
			$table_name,
			[ 'ID'  =>  $id ],
			'%d'
		);

		if ( 1 === $delete) {
			$message->set_id( 0 );
		} else {
			return false;
		}

		return true;
	}
}