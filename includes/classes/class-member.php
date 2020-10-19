<?php
namespace Texteller;
defined( 'ABSPATH' ) || exit;

class Member extends Data
{
	/**
	 * Stores member data.
	 *
	 * @var array
	 */
	protected $data = [
		'user_id'           =>  0,
		'mobile'            =>  '',
		'email'             =>  '',
		'registered_date'   =>  null,
		'modified_date'     =>  null,
		'status'            =>  '',
		'first_name'        =>  '',
		'last_name'         =>  '',
		'title'             =>  '',
		'reg_origin'        =>  '',
		'description'       =>  '',
		'member_group'      =>  [],
		'verification_key'  =>  ''
	];

	public function __construct( $member_id = 0 )
	{
		$this->set_id( intval( $member_id ) );

		$this->data_store = new Member_Data_Store();
		if ( $this->get_id() > 0 ) {
			$read = $this->data_store->read( $this );
			if ( ! $read ) {
				$this->set_id(0);
			}
		}
	}

	/**
	 * Set member data array to the instance.
	 *
	 * @param array $member_array
	 */
	public function set_member_data( array $member_array )
	{
		$member_array = array_replace_recursive( $this->get_data(), $member_array );

		$this->set_id( isset($member_array['id']) ? $member_array['id'] : 0 );

		$this->set_user_id( $member_array['user_id'] );
		$this->set_mobile( $member_array['mobile'] );
		$this->set_email( $member_array['email'] );
		$this->set_registered_date( $member_array['registered_date'] );
		$this->set_modified_date(  $member_array['modified_date'] );
		$this->set_status( $member_array['status'] );
		$this->set_reg_origin( $member_array['reg_origin'] );
		$this->set_first_name( $member_array['first_name'] );
		$this->set_last_name( $member_array['last_name'] );
		$this->set_title( $member_array['title'] );
		$this->set_verification_key( isset( $member_array['verification_key'] ) ? $member_array['verification_key'] : '' );
		$this->set_description( $member_array['description'] );

		$this->set_member_group( isset( $member_array['member_group'] ) ? $member_array['member_group'] : [] );

	}

	public function unlink_user()
	{
		$this->data_store->unlink_user( $this );
	}

	public function update_verification_key()
	{
		return $this->data_store->update_verification_key( $this );
	}

	public function read_verification_key()
	{
		return $this->data_store->read_verification_key( $this );
	}

	/**
	 * @return int
	 */
	public function get_user_id()
	{
		return $this->get_prop( 'user_id' );
	}

	/**
	 * @param int $user_id
	 */
	public function set_user_id( $user_id )
	{
		$this->set_prop( 'user_id', (int) $user_id );
	}

	/**
	 * @return string
	 */
	public function get_mobile()
	{
		return $this->get_prop( 'mobile' );
	}

	/**
	 * @param string $mobile
	 */
	public function set_mobile( string $mobile )
	{
		$this->set_prop( 'mobile', (string) $mobile);
	}

	/**
	 * @return string
	 */
	public function get_email()
	{
		return $this->get_prop( 'email' );
	}

	/**
	 * @param string $email
	 */
	public function set_email( $email )
	{
		$this->set_prop( 'email', (string) $email );
	}

	/**
	 * @return string
	 */
	public function get_registered_date()
	{
		return $this->get_prop( 'registered_date' );
	}

	/**
	 * @param string $registered_date
	 */
	public function set_registered_date( $registered_date )
	{
		$this->set_prop( 'registered_date', $registered_date );
	}

	/**
	 * @return mixed
	 */
	public function get_modified_date()
	{
		return $this->get_prop( 'modified_date' );
	}

	/**
	 * @param mixed $modified_date
	 */
	public function set_modified_date( $modified_date )
	{
		$this->set_prop( 'modified_date', $modified_date );
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
	public function set_status( string $status )
	{
		$this->set_prop( 'status', !empty($status) ? (string) $status : 'registered' );
	}

	/**
	 * @return string
	 */
	public function get_reg_origin()
	{
		return $this->get_prop( 'reg_origin' );
	}

	/**
	 * @param string $reg_origin
	 */
	public function set_reg_origin( $reg_origin )
	{
		$this->set_prop( 'reg_origin', (string) $reg_origin );
	}

	/**
	 * @return string
	 */
	public function get_first_name()
	{
		return $this->get_prop( 'first_name' );
	}

	/**
	 * @param string $first_name
	 */
	public function set_first_name( $first_name )
	{
		$this->set_prop( 'first_name', (string) $first_name );
	}

	/**
	 * @return string
	 */
	public function get_last_name()
	{
		return $this->get_prop( 'last_name' );
	}

	/**
	 * @param string $last_name
	 */
	public function set_last_name( $last_name )
	{
		$this->set_prop( 'last_name', (string) $last_name );
	}

	/**
	 * @return string
	 */
	public function get_title()
	{
		return $this->get_prop( 'title' );
	}

	/**
	 * @param string $title
	 */
	public function set_title( $title )
	{
		$this->set_prop( 'title', (string) $title );
	}

	/**
	 * @return string
	 */
	public function get_verification_key()
	{
		return $this->get_prop( 'verification_key' );
	}

	/**
	 * @param string $verification_key
	 */
	public function set_verification_key( $verification_key )
	{
		$this->set_prop( 'verification_key', (string) $verification_key );
	}

	/**
	 * @return string
	 */
	public function get_description()
	{
		return $this->get_prop( 'description' );
	}

	/**
	 * @param string $description
	 */
	public function set_description( $description )
	{
		$this->set_prop( 'description', $description );
	}

	/**
	 * @return array
	 */
	public function get_member_group()
	{
		return $this->get_prop( 'member_group' );
	}

	/**
	 * @param array $member_group
	 *
	 * @return void
	 */
	public function set_member_group( $member_group )
	{
		$this->set_prop( 'member_group', (array) $member_group );
	}

	/**
	 * @return string
	 */
	public function get_name()
	{
		if ( $this->get_first_name() && $this->get_last_name() ) {
			return $this->get_first_name() . ' ' . $this->get_last_name();
		} elseif ( $this->get_last_name() ) {
			return $this->get_last_name();
		} elseif ( $this->get_first_name() ) {
			return $this->get_first_name();
		} else {
			return $this->get_mobile();
		}
	}

	public function is_canceled()
	{
		return 'canceled' === $this->get_status();
	}

	public function is_verified()
	{
		return 'verified' === $this->get_status();
	}

	public function verify()
	{
		if ( 'canceled' !== $this->get_status() ) {
			$this->set_status('verified');
			$this->set_verification_key('');
			$this->save();
		}
	}

	public function unverify()
	{
		if ( 'canceled' !== $this->get_status() ) {
			$this->set_status('registered');
			$this->set_verification_key('');
			$this->save();
		}
	}

	public function cancel()
	{
		$this->set_status('canceled');
		$this->set_verification_key('');
		$this->save();
	}

	public function uncancel()
	{
		$this->set_status('registered');
		$this->set_verification_key('');
		$this->save();
	}
}