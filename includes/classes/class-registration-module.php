<?php
namespace Texteller;
defined( 'ABSPATH' ) || exit;

class Registration_Module
{
	private static $instance = null;

	/**
	 * @var Member|null
	 */
	private $member = null;

	public $email_or_mobile = [];
	private $display_error_title;
	/**
	 * @var Member|null
	 */
	private $old_member = null;

	private $matched_data = [];

	public $is_reg_started = false;

	private function __construct(  int $member_id = 0 )
	{
		$this->set_member( new Member($member_id) );
	}

	public function init( array $registration_fields, \WP_Error &$errors, array $ignore_list = [], string $mode = 'user_reg', array $custom_names = [] )
	{
		$this->is_reg_started = true;

		if( in_array( $mode, [ 'user_reg', 'admin_edit', 'tlr_nl' ] ) ) {
			$this->display_error_title = true;
		} else {
			$this->display_error_title = false;
		}
		foreach ( $registration_fields as $field_id => $field_data ) {

			if ( !empty($ignore_list) && in_array($field_id, $ignore_list) ) {
				continue;
			}

			// Check if field is enabled.
			if ( isset( $field_data['enabled'] ) && $field_data['enabled'] === 'yes' ) {

				// Get field value from $POST
				$field_value = self::get_posted_data( $field_id, $custom_names );

				// Sanitize and validate the field value
				$field_value = tlr_sanitize_input( $field_value, $field_id, $mode );
				$this->validate_field( $field_id, $field_data, $field_value, $errors, $mode );

				// Set the value in to the current member instance, if no error.
				if ( ! $errors->get_error_message( 'tlr_error_invalid_' . $field_id )
				     && ! $errors->get_error_message( 'tlr_error_exists_' . $field_id )
				) {
					$this->set_member_field_value( $field_id, $field_value );

					if( in_array( $field_id, [ 'mobile', 'email'] ) && !empty($field_value) ) {
						$this->email_or_mobile[$field_id] = $field_value;
					}
				}
			}
		}

		// Add an error if both email and mobile number fields are optional and left empty
		if( empty( $this->email_or_mobile )
		    && empty( $errors->get_error_message( 'tlr_error_exists_email' ) )
		    && empty( $errors->get_error_message( 'tlr_error_invalid_email' ) )
		    && empty( $errors->get_error_message( 'tlr_error_exists_mobile' ) )
		    && empty( $errors->get_error_message( 'tlr_error_invalid_mobile' ) )
		) {
			tlr_add_notice(
				$errors,
				'tlr_error_invalid_required',
				__( 'You should provide a valid mobile number or email address.', 'texteller' ),
				$this->display_error_title
			);
		}
	}

	public static function get_posted_data( $field_id, $custom_names )
	{
		if ( isset( $custom_names[ $field_id ] ) ) {

			if ( ! is_array( $custom_names[$field_id] ) ) {
				$field_name = $custom_names[$field_id] ;
				$field_value = isset( $_POST[$field_name] ) ? $_POST[$field_name] : '';
			} else {
				$field_name = array_key_first( $custom_names[$field_id] ) ;
				$sub_key = $custom_names[$field_id][$field_name];

				//$field_value = isset( $_POST[$custom_names[$field_id]] ) ? $_POST[$custom_names[$field_id]] : '';

				$field_value = isset( $_POST[$field_name][$sub_key] ) ? $_POST[$field_name][$sub_key] : '';
			}

		} else {
			$field_name = 'tlr_' . $field_id;
			$field_value = isset( $_POST[$field_name] ) ? $_POST[$field_name] : '';
		}

		return $field_value;
	}

	public static function get_html_esc_posted_data( $field_id, $custom_names = [] )
	{
		$posted_value = self::get_posted_data( $field_id, $custom_names );
		return esc_html( $posted_value );
	}

	public function get_form_data( $field_id, $custom_names = [] )
	{
		$value_exists = $this->get_member_field_value( $field_id );
		$value_posted = self::get_html_esc_posted_data( $field_id, $custom_names );

		if ( ! empty( $value_posted ) ) {
			return $value_posted;
		} else {
			return $value_exists;
		}
	}

	/**
	 * @param \WP_Error $errors
	 * @param string $field_id
	 * @param array $field_data
	 * @param $field_value
	 * @param string $mode
	 *
	 * @return void
	 */
	protected function validate_field( string $field_id, array $field_data, $field_value, \WP_Error &$errors, $mode = 'user_reg' )
	{
		// If field value is validated
		if ( self::is_field_value_valid( $field_id, $field_value ) ) {

			// Admin member add/edit
			if ( 'user_id' === $field_id && $field_value ) {
				$existing_member_id = tlr_get_member_id( $field_value, $field_id );
				if ( $existing_member_id > 0 && $existing_member_id !== $this->get_member_field_value('id' ) ) {
					$error_message = __( "This user is already assigned to an existing member.", 'texteller' );
					$error_id = 'tlr_error_exists_user_id';
				}
			}

			// If it's a mobile number or email field, checks if there is an existing member with the field value
			if ( in_array( $field_id, [ 'mobile', 'email' ] ) && $field_value ) {

				$existing_member_id = tlr_get_member_id( $field_value, $field_id );
				$existing_user_id = tlr_get_user_id( $existing_member_id, 'ID' );
				$field_title = strtolower( $field_data['title'] );
				$error_id = 'tlr_error_exists_' . $field_data['id'];
				$admin_error_message = __( "This $field_title is already assigned to an existing member.", 'texteller' ); // todo: add edit link to error message

				// If it's NOT a user edit process for this current member
				if ( $existing_member_id !== $this->get_member_field_value('id' ) ) {


					// Adds  an error if there is an existing member with this field value, which has a user linked to it
					if( $existing_member_id > 0 && ( $existing_user_id > 0 || $this->get_member_field_value('user_id') > 0 ) ) {

						switch ( $mode ) {

							case 'admin_edit':
								$error_message = $admin_error_message;
								break;

							case 'nl_form':
								// translators: %s : mobile, email
								$error_message = sprintf( __( "This %s is already subscribed.", 'texteller' ), $field_title );
								break;

							case 'user_reg':
								default:
									$error_message = sprintf(
										__( "This $field_title is already assigned to an existing user. Please login or %s", 'texteller' ),
										sprintf(
											sprintf(
												'<a href="' . wp_lostpassword_url() . '">%s</a>',
												__( 'retrieve your password', 'texteller' )
											)
										)
									);
						}
					}

					// If there is an existing member with this field value, which does NOT have a user linked to it
					elseif( $existing_member_id > 0 && !$existing_user_id ) {

						// If it's not an admin edit, try to add the existing member to the this instance
						if ( !in_array( $mode, ['admin_edit', 'nl_form'] ) ) {

							// If there isn't any duplicate email/mobile conflict yet, initiates old member property to be saved later in the process.
							if ( is_null( $this->old_member ) ) {
								$this->matched_data[$field_id] = $field_value;
								$this->old_member = new Member( $existing_member_id );
							}

							// Adds an error, if we already have a duplicate mobile/email conflict
							elseif( $this->old_member->get_id() ) {
								$error_message = __('Error while registering with the provided email and mobile number.', 'texteller');
							}

						}
						elseif( 'nl_form' === $mode ) {
							// translators: %s : mobile, email
							$error_message = sprintf( __( "This %s is already subscribed.", 'texteller' ), $field_title );
						}
						// If it's an admin edit, add an error.
						else {
							$error_message = $admin_error_message;
						}
					}
				}
			}
		}

		// If field value is not valid
		else {

			// not-valid field value or empty required field
			// For most of the cases, this won't happen, we have a sanitized field value and for most cases it's valid or empty. e.g. wrongly formatted email
			if ( ! empty($field_value) || ( isset($field_data['required']) && 1 == $field_data['required'] ) ) {

				$error_id = 'tlr_error_invalid_' . $field_data['id'];

				// empty required select inputs
				if ( empty($field_value) && ( in_array( $field_id, [ 'member_group', 'title' ] ) ) ) {
					$error_message = sprintf( __( 'Please select a %s', 'texteller' ), strtolower($field_data['title']) );
					// @translators: %s = 'title', 'member groups'

				}

				// not-valid field value or empty required field (non-select inputs)
				else {
					$error_message = sprintf( __( 'Please provide a valid %s', 'texteller' ), strtolower( $field_data['title'] ) );
					// @translators: %s = 'first name', 'last name', 'mobile', 'email'
				}
			}
		}

		if( isset( $error_message, $error_id ) ) {
			tlr_add_notice(
				$errors,
				$error_id,
				$error_message,
				$this->display_error_title
			);
		}
	}

	protected static function is_field_value_valid( string $field_id, $field_value) : bool
	{
		$special_chars = "/[!@#$%^&*()_+\-=\[\]{};':\"\\|,.<>\/?]/";
		$numbers = "/[۰۱۲۳۴۵۶۷۸۹٩٨٧٦٥٤٣٢١٠0123456789]/u";

		switch ($field_id) {
			//case 'id':
			case 'user_id':
				return $field_value ? (bool) get_userdata( (int) $field_value ) : true;

			case 'mobile':
				return (bool) tlr_is_mobile_valid($field_value);

			case 'status':
				return in_array( $field_value, ['registered', 'verified', 'cancelled'], true );

			case 'first_name':
				return mb_strlen($field_value, 'UTF-8') > 1 && !preg_match($special_chars, $field_value)
				       && !preg_match($numbers, $field_value);

			case 'last_name':
				return mb_strlen($field_value, 'UTF-8') > 1 && !preg_match($special_chars, $field_value)
				       && !preg_match($numbers, $field_value);

			case 'email':
				return (bool) is_email( $field_value );

			case 'title':
				return in_array($field_value, ['mr', 'mrs', 'miss', 'ms']) || empty($field_value);

			case 'member_group':

				if ( empty( $field_value ) || ! is_array( $field_value ) ) {
					return false;
				}

				foreach ( $field_value as $member_group ) {

					$term = get_term_by('slug', $member_group, 'member_group');
					if ( ! $term || ! $term->term_id > 0 ) {
						return false;
					}
				}

				return true;

			case 'description':
				return true;
			default:
				return false;
		}
	}

	public function generate_username( string $generator_method = 'int_mobile', $generator_option_name = '' )
	{
		if ( !$generator_method && $generator_option_name ) {
			$generator_method = get_option( $generator_option_name, 'int_mobile' );
		}

		switch ( $generator_method ) {

			case 'texteller':
				$username = $this->default_username_generator();
				break;

			case 'int_mobile':
				$mobile = $this->member->get_mobile();
				$username = str_replace( '+', '', $mobile );
				break;

			case 'national_mobile':
				$format = get_option( $generator_option_name . '_national_mobile', 'leading-zero' );
				$mobile = $this->member->get_mobile();
				if ( $mobile ) {
					$phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
					try {
						$NumberProto = $phoneUtil->parse($mobile);
						$username = $phoneUtil->getNationalSignificantNumber($NumberProto);
						if ( 'leading-zero' === $format ) {
							$username = '0' . $username;
						}
					} catch (\libphonenumber\NumberParseException $e) {
						tlr_write_log( "libphonenumber error for $mobile: " . $e->getMessage() );
					}
				}
				break;

			case 'rand_numbers':
				$username = 'user_' . zeroise( wp_rand( 0, 999999 ), 6 );
				$i = 2;
				while ( username_exists( $username ) ) {
					$username = $username . $i;
					$i ++;
				}
				break;

			default:
				$username = '';
				break;
		}

		if ( empty($username) ) {
			$username = $this->generate_username( 'rand_numbers' );
		}

		$username = sanitize_user( $username );
		return $username;
	}

	protected function default_username_generator( $suffix = '' )
	{
		$username_parts = array();

		if ( !empty( $this->member->get_first_name() ) ) {
			$username_parts[] = sanitize_user( $this->member->get_first_name(), true );
		}

		if ( !empty( $this->member->get_last_name() ) ) {
			$username_parts[] = sanitize_user( $this->member->get_last_name(), true );
		}

		// Remove empty parts.
		$username_parts = array_filter( $username_parts );

		// If there are no parts, e.g. name had unicode chars, or was not provided, fallback to email.
		if ( empty( $username_parts ) ) {
			$email_parts    = explode( '@', $this->member->get_email() );
			$email_username = $email_parts[0];

			// Exclude common prefixes.
			if ( in_array( $email_username, [ 'sales', 'hello', 'mail', 'contact', 'info'],true ) ) {
				// Get the domain part.
				$email_username = $email_parts[1];
			}

			$username_parts[] = sanitize_user( $email_username, true );
		}

		$username = tlr_strtolower( implode( '.', $username_parts ) );

		if ( $suffix ) {
			$username .= $suffix;
		}

		if ( username_exists( $username ) ) {
			// Generate something unique to append to the username in case of a conflict with another user.
			$suffix = '-' . zeroise( wp_rand( 0, 9999 ), 4 );
			return $this->default_username_generator( $suffix );
		}

		return $username;
	}

	public function register_new_user_member( int $user_id, string $origin )
	{
		// Check if registration has been initiated
		if ( false === $this->is_reg_started ) {
			return false;
		}

		$this->member->set_user_id( $user_id );
		$this->member->set_status( 'registered' );
		$this->member->set_reg_origin( $origin );


		if( !is_null( $this->old_member ) ) {

			if( empty( $this->matched_data ) || empty( $this->old_member->get_id() ) ) {
				tlr_write_log("Texteller: Filed to update the linked member to user $user_id");
				return false;
			}

			$new_data = $this->member->get_data();

			if( isset( $new_data['reg_origin'] ) ) {
				unset( $new_data['reg_origin'] );
			}

			foreach ( $new_data as $field_id => $value ) {


				// todo: status (verified?)

				if ( 'id' === $field_id || isset( $this->matched_data[$field_id] ) ) {
					continue;
				}

				$setter_method = "set_$field_id";

				if( method_exists( $this->old_member, $setter_method ) ) {
					$this->old_member->$setter_method( $value );
				}
				// todo: add admin note, log, something, about the changes
				// todo: option to do this after verification
			}

			$member_id = $this->old_member->save();
			$this->member = $this->old_member;
		} else {
			// Save as a new member
			$member_id = $this->save_member();
		}

		if ( $member_id > 0 ) {
			do_action( 'tlr_new_user_member_registered', $user_id, $origin, $this->member );
			return $member_id;
		} else {
			tlr_write_log("Texteller: Filed to create new member with user_id $user_id via $origin");
			return false;
		}
	}

	public function save_member()
	{
		return $this->member->save();
	}

	public function get_member_field_value( string $field_id )
	{
		$getter_method = "get_$field_id";

		return method_exists( $this->member, $getter_method ) ? $this->member->$getter_method() : null;
	}

	/**
	 * @param string $field_id
	 * @param $field_value
	 */
	public function set_member_field_value( string $field_id, $field_value )
	{
		$setter_method = "set_$field_id";

		if ( method_exists( $this->member, $setter_method ) ) {
			$this->member->$setter_method( $field_value );
		}
	}

	/**
	 * @return Member|null
	 */
	public function get_member()
	{
		return $this->member;
	}

	/**
	 * @param Member $member
	 */
	public function set_member( Member $member )
	{
		$this->member = $member;
	}

	public function verify_member()
	{
		if ( current_user_can('manage_options') ) {
			$this->member->verify();
		}
	}

	public function unverify_member()
	{
		if ( current_user_can('manage_options') ) {
			$this->member->unverify();
		}
	}

	public function cancel_member()
	{
		if ( current_user_can('manage_options') ) {
			$this->member->cancel();
		}
	}

	public function uncancel_member()
	{
		if ( current_user_can('manage_options') ) {
			$this->member->uncancel();
		}
	}

	public static function get_instance( $member_id = 0 ) : Registration_Module
	{
		if ( null === self::$instance ) {
			self::$instance = new self( $member_id );
		}
		return self::$instance;
	}
}