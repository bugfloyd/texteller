<?php
namespace Texteller;
defined( 'ABSPATH' ) || exit;


class Member_Data_Store implements Interfaces\Object_Data_Store {

	private $db_table = '';
	/*
	|--------------------------------------------------------------------------
	| CRUD Methods
	|--------------------------------------------------------------------------
	*/

	public function __construct()
	{
		global $wpdb;
		$db_prefix = $wpdb->prefix;
		$this->db_table = $db_prefix . 'tlr_members';
	}

	/**
	 * @param Member $member
	 *
	 * @return bool|int
	 */
	public function create( &$member )
	{
		if ( $member->get_id() > 0 ) {
			return false;
		}

		if ( ! $member->get_registered_date() ) {
			$member->set_registered_date( current_time( 'mysql', true ) );
			$member->set_modified_date( current_time( 'mysql', true ) );
		}

		global $wpdb;

		if ( empty($member->get_status()) ) {
		    $member->set_status('registered');
        }

		$save = $wpdb->insert( $this->db_table, [
			'user_id'           =>  $member->get_user_id(),
			'mobile'            =>  $member->get_mobile(),
			'email'             =>  $member->get_email(),
			'registered_date'   =>  $member->get_registered_date(),
			'modified_date'     =>  $member->get_modified_date(),
			'status'            =>  $member->get_status(),
			'first_name'        =>  $member->get_first_name(),
			'last_name'         =>  $member->get_last_name(),
			'title'             =>  $member->get_title(),
			'reg_origin'        =>  $member->get_reg_origin(),
			'description'       =>  $member->get_description()
		], ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'] );

		$possible_new_member_id = $wpdb->insert_id;

		if ( 1 === $save && $possible_new_member_id > 0 ) {
			$member->set_id( $wpdb->insert_id );
			$this->update_member_group( $member, true );
			$member->apply_changes();
			$member->set_object_read(true );

			return $possible_new_member_id;
		} else {
			return false;
		}
	}

	/**
	 * @param Member $member
	 *
	 * @return bool
	 */
	public function read( &$member )
	{
		if ( ! $member->get_id() > 0 ) {
			return false;
		}

		$id = $member->get_id();

		global $wpdb;
		$member_data = $wpdb->get_results( "SELECT * FROM $this->db_table WHERE ID = $id", ARRAY_A  );

		if ( count($member_data) != 1 ) {
			return false;
		}

		$member_data = $member_data[0];

		$member->set_member_data( $member_data );

		$this->read_member_group( $member );

		$member->set_object_read( true );
		return true;
	}

	/**
	 * @param Member $member
	 *
	 * @return bool
	 */
	public function update( &$member )
	{
		$id = $member->get_id();
		$changes = $member->get_changes();

		if ( ! is_array($changes) || ! $id ) {
			return false;
		}

		if ( empty($changes) ) {
			return $id;
		}

		if ( tlr_get_mobile( $id, 'ID' ) !== $member->get_mobile() ) {
			$member->set_status('registered');
			$member->set_verification_key('');
		}

		global $wpdb;

		$member->set_modified_date( current_time( 'mysql', true ) );

		$update = $wpdb->update(
			$this->db_table,
			[
				'user_id'           =>  $member->get_user_id(),
				'mobile'            =>  $member->get_mobile(),
				'email'             =>  $member->get_email(),
				'modified_date'     =>  $member->get_modified_date(),
				'status'            =>  $member->get_status(),
				'first_name'        =>  $member->get_first_name(),
				'last_name'         =>  $member->get_last_name(),
				'title'             =>  $member->get_title(),
				'reg_origin'        =>  $member->get_reg_origin(),
				'description'       =>  $member->get_description(),
				'verification_key'  =>  $member->get_verification_key()
			],
			[
				'ID'    =>  $id
			],
			['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ],
			'%d'
		);

		if ( $update === 1 ) {

			$this->update_member_group( $member );
			$member->apply_changes();

			return true;
		} else {
			return false;
		}
	}

	/**
	 * @param Member $member*
	 *
	 * @return bool
	 */
	public function delete( &$member )
	{
		$id = $member->get_id();

		if ( ! $id ) {
			return false;
		}

		global $wpdb;

		$this->delete_usermeta( $member );

		$delete = $wpdb->delete(
			$this->db_table,
			[ 'ID'  =>  $id ],
			'%d'
		);

		if ( 1 === $delete) {

			// If the deleted member was an admin, remove it from the admin list
			$admins = (array) get_option( 'tlr_staff' );
			if( false !== ( $key = array_search( $id, $admins, false ) ) ) {
				unset( $admins[$key] );
			}
			update_option( 'tlr_staff', $admins );

			// Remove linked member groups
			$this->remove_member_terms($member, true );

			$member->set_id( 0 );
		} else {
			return false;
		}

		return true;
	}

	/*
	|--------------------------------------------------------------------------
	| Additional Methods
	|--------------------------------------------------------------------------
	*/

	protected function update_member_group( Member &$member, $force = false )
	{
		$changes = $member->get_changes();

		if ( $force || array_key_exists( 'member_group', $changes ) ) {
			wp_set_object_terms( $member->get_id(), $member->get_member_group(), 'member_group',false );
		}
	}

	protected function read_member_group( Member &$member )
	{
		$terms = wp_get_object_terms( $member->get_id(), 'member_group' );
		$term_names = is_array( $terms ) ? wp_list_pluck( $terms, 'slug' ) : [];
		$member->set_member_group( $term_names );
	}

	protected function remove_member_terms( Member &$member, $force = false )
	{
		$changes = $member->get_changes();

		if ( $force || ( array_key_exists('member_group', $changes) && empty($changes['member_group']) ) ) {
			wp_delete_object_term_relationships( $member->get_id(), 'member_group' );
		}
	}

	/**
	 * Link user to the member
	 *
	 * Returns true on success
	 * Returns 0 if user is already linked to this member
	 * Returns false if user is already linked to another member
	 * Returns false if member_id or user_id doesn't exist or something goes wrong
	 *
	 * @param   Member  $member
	 * @param   int     $user_id
	 *
	 * @return  bool
	 */
	public function link_user( Member &$member, int $user_id )
	{
		global $wpdb;
		$member_id  =   $member->get_id();

		if ( !$member_id || !$user_id ) {
			return false;
		}

		$user = get_user_by('id', $user_id);

		if ( ! $user ) {
			return false;
		}

		$maybe_member_id    =   tlr_get_member_id( $user_id,'user_id' );

		if ( !$maybe_member_id ) {

			$updated_member = $wpdb->update(
				$this->db_table,
				[ 'user_id'     =>    $user_id ],
				[ 'ID'   =>    $member_id ],
				'%d',
				'%d'
			);

			if ( 1 === $updated_member ) {
				$member->set_user_id( $user_id );

				$this->update_usermeta( $user_id, $member_id, $member->get_mobile() );

				return true;    //the relationship was successfully updated
			} else {
				return false;   //something went wrong
			}

		} elseif ( $maybe_member_id !==  $member_id ) {
			return null;  //user is already linked to another member
		} elseif ( $maybe_member_id ===  $member_id) {
			return 0;   //user is already linked to this member
		} else {
			return false;   //something went wrong
		}
	}

	public function unlink_user( Member &$member )
	{
		global $wpdb;
		$member_id  =   $member->get_id();
		$user_id    =   $member->get_user_id();

		if ( !$member_id || !$user_id ) {
			return;
		}

		$updated_relationship = $wpdb->update(
			$this->db_table,
			[ 'user_id' =>  0 ],
			[ 'ID'      =>  $member_id ],
			'%d',
			'%d'
		);

		if ( 1 === $updated_relationship ) {

			$this->delete_usermeta( $member );

			$member->set_user_id(0);
		}
	}

	public function delete_usermeta( Member &$member )
	{
		$user_id = $member->get_user_id();

		if ( $user_id ) {
			delete_user_meta( $user_id, 'tlr_member_id' );
			delete_user_meta( $user_id, 'tlr_mobile' );
		}
	}

	public function update_usermeta( $user_id , $member_id, $mobile )
	{
		update_user_meta( $user_id, 'tlr_member_id', $member_id );
		update_user_meta( $user_id, 'tlr_mobile', $mobile );
	}

	public function read_verification_key( Member &$member )
	{
		if( ! $member->get_id() ) {
			return false;
		}

		global $wpdb;
		$verification_key = $wpdb->get_var( $wpdb->prepare(
			"SELECT verification_key FROM $this->db_table WHERE `ID` = %s",
			$member->get_id()
		));

		return $verification_key ? $verification_key : false;
	}

	public function update_verification_key( Member &$member )
	{
		if( ! $member->get_id() ) {
			return false;
		}
		global $wpdb;

		$update = $wpdb->update(
			$this->db_table,
			[ 'verification_key' => $member->get_verification_key() ],
			[ 'ID' => $member->get_id() ],
			'%s',
			'%d'
		);

		if ( $update === 1 ) {
			$member->apply_changes();
			return true;
		} else {
			return false;
		}
	}

}