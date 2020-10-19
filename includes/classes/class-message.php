<?php

namespace Texteller;

defined( 'ABSPATH' ) || exit;

class Message extends Data {

	/**
	 * Stores member data.
	 *
	 * @var array
	 */
	protected $data = [
		'trigger'           =>  '',
		'gateway'           =>  '',
		'interface'         =>  '',
		'interface_number'  =>  '',
		'recipient'         =>  '',
		'date'              =>  null,
		'content'           =>  '',
		'status'            =>  '',
		'gateway_data'      =>  [],
		'member_id'         =>  null
	];

	public function __construct( $message_id = 0 )
	{
		$this->set_id( intval( $message_id ) );

		$this->data_store = new Message_Data_Store();
		if ( $this->get_id() > 0 ) {
			$read = $this->data_store->read( $this );
			if ( ! $read ) {
				$this->set_id(0);
			}
		}
	}

	/**
	 * Set message data array to the instance.
	 *
	 * @param array $message_array
	 */
	public function set_message_data( array $message_array )
	{
		$message_array = array_replace_recursive( $this->get_data(), $message_array );

		$this->set_id( isset($message_array['ID']) ? $message_array['ID'] : 0 );

		$this->set_trigger( $message_array['message_trigger'] );
		$this->set_gateway( $message_array['message_gateway'] );
		$this->set_interface( $message_array['message_interface'] );
		$this->set_interface_number( $message_array['message_interface_number'] );
		$this->set_recipient( $message_array['message_recipient'] );
		$this->set_date( isset($message_array['message_date']) ? $message_array['message_date'] : '' );
		$this->set_content( $message_array['message_content'] );
		$this->set_status( $message_array['message_status'] );
		$this->set_gateway_data( $message_array['message_gateway_data'] );
		$this->set_member_id( $message_array['message_member_id'] );
	}

	/**
	 * @return string
	 */
	public function get_trigger()
	{
		return $this->get_prop( 'trigger' );
	}

	/**
	 * @param string $trigger
	 */
	public function set_trigger( $trigger )
	{
		$this->set_prop( 'trigger', (string) $trigger );
	}

	/**
	 * @return string
	 */
	public function get_recipient()
	{
		return $this->get_prop( 'recipient' );
	}

	/**
	 * @param string $recipient
	 */
	public function set_recipient( $recipient )
	{
		$this->set_prop( 'recipient', (string) $recipient );
	}

	/**
	 * @return string
	 */
	public function get_interface()
	{
		return $this->get_prop( 'interface' );
	}

	/**
	 * @param string $interface
	 */
	public function set_interface( $interface )
	{
		$this->set_prop( 'interface', (string) $interface );
	}

	/**
	 * @return string
	 */
	public function get_interface_number()
	{
		return $this->get_prop( 'interface_number' );
	}

	/**
	 * @param string $interface_number
	 */
	public function set_interface_number( $interface_number )
	{
		$this->set_prop( 'interface_number', (string) $interface_number );
	}

	/**
	 * @return string
	 */
	public function get_date()
	{
		return $this->get_prop( 'date' );
	}

	/**
	 * @param string $date
	 */
	public function set_date( $date )
	{
		$this->set_prop( 'date', $date );
	}

	/**
	 * @return string
	 */
	public function get_status()
	{
		return $this->get_prop( 'status' );
	}

	/**
	 * @param string $status
	 */
	public function set_status( $status )
	{
		$this->set_prop( 'status', $status );
	}

	/**
	 * @return string
	 */
	public function get_gateway()
	{
		return $this->get_prop( 'gateway' );
	}

	/**
	 * @param mixed $gateway
	 */
	public function set_gateway( $gateway )
	{
		$this->set_prop( 'gateway', (string) $gateway );
	}


	/**
	 * @return mixed string or array
	 */
	public function get_content()
	{
		return $this->get_prop( 'content' );
	}

	/**
	 * @param mixed $content
	 */
	public function set_content( $content )
	{
		$this->set_prop( 'content', $content );
	}

	/**
	 * @return array
	 */
	public function get_gateway_data() {
		return $this->get_prop( 'gateway_data' );
	}

	/**
	 * @param array $gateway_data
	 */
	public function set_gateway_data( array $gateway_data ) {
		$this->set_prop( 'gateway_data', $gateway_data );
	}

	/**
	 * @return int
	 */
	public function get_member_id() {
		return $this->get_prop( 'member_id' );
	}

	/**
	 * @param int $member_id
	 */
	public function set_member_id( $member_id ) {
		$this->set_prop( 'member_id', (int) $member_id );
	}
}