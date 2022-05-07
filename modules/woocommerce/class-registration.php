<?php

namespace Texteller\Modules\WooCommerce;
use exception;
use Texteller as TLR;
use WC_Customer;
use WP_Error;
use function Texteller\tlr_write_log;
use Texteller\Registration_Module;

defined( 'ABSPATH' ) || exit;

final class Registration
{
    private static $reg_origin = '';
    private static $is_enabled = null;

	private function __construct() {}

	public static function init()
	{
		self::override_wc_stuff();
		self::init_hooks();
	}

	private static function init_hooks()
	{
		// My-Account and Checkout registration
		add_filter( 'woocommerce_process_registration_errors', [ self::class, 'init_account_registration' ] );
		add_action( 'woocommerce_after_checkout_validation', [ self::class, 'init_checkout_registration' ], 10, 2);
		add_action( 'woocommerce_created_customer', [ self::class, 'account_checkout_new_customer_registration' ], 10, 2 );
		add_action( 'woocommerce_checkout_update_user_meta', [ self::class, 'checkout_old_customer_registration' ], 10, 2 );

		// My-Account edit end-point
		add_action( 'woocommerce_save_account_details_errors',  [self::class, 'init_account_edit_registration' ], 10, 2 );
		add_action( 'woocommerce_save_account_details', [ self::class, 'edit_account_member_registration' ], 10 );
		add_filter( 'woocommerce_save_account_details_required_fields', [ self::class, 'handle_account_edit_email_requirement' ], 10, 1 );

		// Checkout specific fields and process
		add_filter( 'woocommerce_checkout_fields', [ self::class, 'deregister_checkout_fields' ], 10, 1 );
		add_filter( 'woocommerce_checkout_posted_data', [ self::class, 'define_user_email_on_checkout' ], 10, 1 );
		add_action( 'woocommerce_checkout_update_user_meta', [ self::class, 'checkout_update_member_names' ], 11, 2 );

		// Checkout: number verification
		add_action( 'woocommerce_checkout_order_processed', [self::class, 'checkout_check_number_verification'], 10, 3 );
		add_action( 'woocommerce_before_checkout_form', [ self::class, 'checkout_render_number_verification' ] );
		add_action( 'wp_ajax_tlr_wc_checkout_init_verify', [ self::class, 'checkout_init_verification' ] );
		add_action( 'wp_ajax_nopriv_tlr_wc_checkout_init_verify', [ self::class, 'checkout_init_verification' ] );
		add_action( 'woocommerce_thankyou', [ self::class, 'thankyou_init_verify'] );

		// MISC
		add_action( 'woocommerce_email', [ self::class, 'control_new_customer_email' ] );
		add_filter( 'woocommerce_email_enabled_customer_reset_password', [ self::class, 'control_lost_customer_email' ] );
		add_filter( 'tlr_registration_origins', [self::class, 'add_registration_origin' ], 15, 1 );
		add_action( 'wp_enqueue_scripts', [self::class, 'enqueue_assets'] );
	}

	public static function add_registration_origin( array $origins )
	{
		$wc_origins = [
			'wc-account'        =>  'WooCommerce: My-Account',
			'wc-checkout'       =>  'WooCommerce: Checkout',
			'wc-account-edit'   =>  'WooCommerce: My-Account edit'
		];

		return array_merge( $origins, $wc_origins );
	}

	/**
     * Un-sets billing email and phone.
     *
     * @see \Texteller\Modules\WooCommerce\Registration::render_checkout_registration_fields()
     *
	 * @param $checkout_fields
	 *
	 * @return mixed
	 */
	public static function deregister_checkout_fields( $checkout_fields )
	{
		if ( ! self::is_registration_enabled() ) {
			return $checkout_fields;
		}

		$registration_fields  = TLR\get_form_fields('tlr_wc_registration_form_fields', Options::class);
		if ( empty($registration_fields['email']['required']) ) {
			$checkout_fields['billing']['billing_email']['required'] = false;
        }
		if ( isset( $checkout_fields['billing']['billing_phone'] ) ) {
			unset( $checkout_fields['billing']['billing_phone'] );
		}

		return $checkout_fields;
	}

	/**
     * To ignore warnings caused by an empty optional email while registering the new user using wc_create_new_customer() function
     *
	 * @param $data
	 *
	 * @return mixed
	 */
	public static function define_user_email_on_checkout( $data )
	{
		if ( ! self::is_registration_enabled() ) {
			return $data;
		}

		if ( ! is_user_logged_in() ) {
		    $data['billing_email'] = isset( $data['billing_email'] ) ? $data['billing_email'] : '';
	    }
		return $data;
	}

	/**
     * Initiates the registration module and validates posted data on My-Account login/sign-up end-point
     *
	 * @param WP_Error $errors
	 *
	 * @return WP_Error
	 */
	public static function init_account_registration( WP_Error $errors )
	{
	    if ( ! self::is_registration_enabled() ) {
	        return $errors;
        }

		$registration_fields  = TLR\get_form_fields('tlr_wc_registration_form_fields', Options::class);

		$registration_module = Registration_Module::get_instance();
		$registration_module->init(
		        $registration_fields,
                $errors,
                [],
                'user_reg',
                ['email' => 'email' ]
        );

		return $errors;
	}

	public static function init_account_edit_registration( &$errors, &$user )
    {
	    if ( ! self::is_registration_enabled() ) {
		    return;
	    }

	    $registration_fields  = TLR\get_form_fields('tlr_wc_registration_form_fields', Options::class);

	    $member_id = TLR\tlr_get_member_id( $user->ID, 'user_id');
	    $ignore_list = $member_id && 'yes' !== get_option( 'tlr_wc_enable_number_edit' ) ? [ 'mobile' ] : [];

	    $registration_module = Registration_Module::get_instance( $member_id );

	    // If mobile number is empty, allow the customer to edit it, even if mobile number update is disabled.
	    if( empty( $registration_module->get_member_field_value('mobile') )
            && in_array( 'mobile', $ignore_list, true )
        ) {
		    unset( $ignore_list['mobile'] );
        }

	    $registration_module->init(
	            $registration_fields,
                $errors,
                array_merge( ['first_name', 'last_name'], $ignore_list ),
                'user_reg',
                ['email' => 'account_email' ]
        );

	    self::$reg_origin = 'account-edit';

	    // This is just for avoiding undefined property warning if email field is left empty.
	    if ( !isset( $user->user_email ) ) {
	        $user->user_email = '';
        }
    }

	/**
     * Initiates the registration module and validates posted data on checkout
     *
	 * @param $posted_data
	 * @param $errors
	 */
	public static function init_checkout_registration( $posted_data, $errors )
    {
	    if ( ! self::is_registration_enabled() ) {
		    return;
	    }
		$texteller_fields = TLR\get_form_fields('tlr_wc_registration_form_fields', Options::class);

		$is_registration_required = apply_filters( 'woocommerce_checkout_registration_required', 'yes' !== get_option( 'woocommerce_enable_guest_checkout' ) );
		$is_registration_enabled  = apply_filters( 'woocommerce_checkout_registration_enabled', 'yes' === get_option( 'woocommerce_enable_signup_and_login_from_checkout' ) );

		if ( ! is_user_logged_in() && ( $is_registration_required || $is_registration_enabled ) ) {
			$registration_module = Registration_Module::get_instance();

			$registration_module->init(
			        $texteller_fields,
                    $errors,
                    [ 'first_name', 'last_name', 'email' ],
                    'user_reg'
            );

			if ( ! empty($posted_data['billing_email']) ) {
				$registration_module->set_member_field_value( 'email', $posted_data['billing_email'] );
            }
			if ( ! empty($posted_data['billing_first_name']) ) {
				$registration_module->set_member_field_value( 'first_name', $posted_data['billing_first_name'] );
			}
			if ( ! empty($posted_data['billing_last_name']) ) {
				$registration_module->set_member_field_value( 'last_name', $posted_data['billing_last_name'] );
			}

			self::$reg_origin = 'checkout_new_customer';
		}

		if ( is_user_logged_in() ) {

			if ( 'yes' === get_option( 'tlr_wc_checkout_old_customer_registration', 'yes' ) ) {

				if ( get_current_user_id() && ! TLR\tlr_get_member_id( get_current_user_id(), 'user_id' ) ) {

					$registration_module = Registration_Module::get_instance();

					$user = wp_get_current_user();
					$email = isset($user->user_email) ? $user->user_email : '';
					if( $email ) {
						$registration_module->set_member_field_value('email', $email );
						$registration_module->email_or_mobile['email'] = $email;
                    }

					$registration_module->init(
					        $texteller_fields,
                            $errors,
                            [ 'email' ],
                            'user_reg'
                    );
					self::$reg_origin = 'checkout-old-customer';
				}
            }
		}
	}

	/**
	 * @param int       $order_id
	 * @param array     $posted_data
	 * @param \WC_Order $order
	 *
	 * @throws Exception checkout error
	 */
	public static function checkout_check_number_verification( $order_id, $posted_data, $order )
    {
	    if ( ! self::is_registration_enabled() ) {
		    return;
	    }

	    if ( 'yes' === get_option( 'tlr_wc_checkout_force_verify' ) ) {
	        if ( $user_id = $order->get_customer_id() ) {
		        $member_id = TLR\tlr_get_member_id( $user_id, 'user_id' );

		        if ( $member_id > 0 ) {
			        $member = new TLR\Member( $member_id );
			        if ( ! $member->is_verified() ) {
			            // Note: The message should be the same with the one used in enqueue_assets()
				        throw new Exception( __( 'Please verify your mobile number.' , 'texteller' ) . '<input style="display:none;" hidden id="needs-verification" value="1">' );
			        }
                }
            }
        }
    }

    public static function checkout_render_number_verification()
    {
	    if ( ! self::is_registration_enabled() ) {
		    return;
	    }

	    if ( 'yes' === get_option( 'tlr_wc_checkout_force_verify' ) ) {

		    if ( $user_id = get_current_user_id() ) {

			    $member_id = TLR\tlr_get_member_id( $user_id, 'user_id' );

			    if ( $member_id > 0 ) {
				    $member = new TLR\Member( $member_id );

				    if( !$member->is_verified() ) {
					    $number_verification = new TLR\Verify_Number();
					    $number_verification->set_member($member);
					    echo '<div style="margin-bottom: 20px;">';
					    echo $number_verification->get_html();
					    echo '</div>';
                    }
			    }
		    }

	    }
    }

    public static function checkout_init_verification()
    {
	    if (
                ! isset( $_REQUEST['tlr_mobile'] )
             || ! isset( $_REQUEST['tlr_security'] )
             || ! check_ajax_referer( 'tlr-checkout-init-verify', 'tlr_security' )
        ) {
	        wp_send_json_error( 'Invalid request sent.' );
        }

	    $mobile =  TLR\tlr_sanitize_mobile( $_REQUEST['tlr_mobile'] );

        $member_id = TLR\tlr_get_member_id( $mobile, 'mobile' );

        if ( $member_id > 0 ) {
	        $member = new TLR\Member( $member_id );

	        $verification_module = new TLR\Verify_Number();
	        $verification_module->set_member( $member );
            $html = $verification_module->get_html();

	        echo json_encode( [
	                'html'  =>  $html
            ] );
	        wp_die();
        }
    }

    public static function thankyou_init_verify( $order_id )
    {
	    if ( ! self::is_registration_enabled() ) {
		    return;
	    }

	    if ( 'yes' === get_option('tlr_wc_checkout_thank_you_verify') ) {
	        $order = wc_get_order( $order_id );
	        $user_id = $order->get_user_id();
	        $member_id = TLR\tlr_get_member_id( $user_id, 'user_id' );

	        if ( $member_id ) {
		        $member = new TLR\Member( $member_id );

		        if ( ! $member->is_verified() ) {
			        $verification_module = new TLR\Verify_Number();
			        $verification_module->set_member( $member );
			        echo $verification_module->get_html();
                }
	        }
        }
    }

	public static function control_new_customer_email( $email_class )
	{
		if ( ! self::is_registration_enabled() ) {
			return;
		}

		if ( 'texteller' !== get_option('tlr_wc_registration_new_customer_notification_base_gateway', 'wc_default') ) {
			return;
		} else {
			remove_action( 'woocommerce_created_customer_notification', [$email_class, 'customer_new_account'], 10, 3 );
		}
	}

	public static function control_lost_customer_email( $enabled )
	{
		if ( ! self::is_registration_enabled() ) {
			return $enabled;
		}

		$rp_notification = get_option( 'tlr_wc_lost_password_base_gateway', 'both' );

		if ( 'texteller' === $rp_notification ) {
			return false;
		} elseif ( 'user_choice' === $rp_notification ) {
			if ( isset( $_POST['tlr_lost_pw_user_choice'] ) ) {
				$user_choice = in_array( $_POST['tlr_lost_pw_user_choice'], [ 'email', 'message', 'both' ], true ) ? $_POST['tlr_lost_pw_user_choice'] : 'both';
			} else {
				$user_choice = 'both';
			}
			if ( 'message' === $user_choice ) {
				return false;
			}
		}

		return $enabled;
	}

	/**
     * Add member when NEW customer is registered via Checkout or My-Account pages
     *
	 * @param int $customer_id User ID of the registered customer
	 * @param array $new_customer_data Customer data array
	 */
	public static function account_checkout_new_customer_registration( $customer_id, $new_customer_data  )
	{
		if ( ! self::is_registration_enabled() ) {
			return;
		}

		$registration_module = Registration_Module::get_instance();

		if ( 'account_registration' === self::$reg_origin ) {
			$reg_origin = 'wc-account';
		} elseif ( 'checkout_new_customer' === self::$reg_origin ) {
			$reg_origin = 'wc-checkout';
		} else {
		    return;
        }

		// Save new member
		$member_id = $registration_module->register_new_user_member( $customer_id, $reg_origin );

		if ( $member_id > 0 ) {
			try {
				$customer = new WC_Customer( $customer_id );

				if ( defined('WOOCOMMERCE_CHECKOUT') || is_checkout() ) {
					self::update_empty_member_names( $registration_module, $customer );
				}

				if ( 'account_registration' === self::$reg_origin && 'yes' === get_option('tlr_wc_registration_update_customer_names', 'yes')  ) {
					self::update_empty_customer_names( $registration_module, $customer );
				}

				self::update_customer_billing_phone( $registration_module, $customer );

				$registration_module->save_member();
				$customer->save();

			} catch ( exception $e) {
				tlr_write_log( 'TLR Error: Failed to update customer/member after new customer registration - ' . $e->getMessage());
			}

			/**
			 * Fires after a new customer is registered via WC My-Account or Checkout page
             *
             * @see \Texteller\Modules\WooCommerce\Notifications::new_customer_registered()
             *
             * @param \Texteller\Member Registered member
             * @param int $customer_id User ID of the registered customer
             * @param array $new_customer_data Customer data array
			 */
			do_action( 'tlr_wc_new_customer_registered', $registration_module->get_member(), $customer_id, $new_customer_data );
		}
	}

	public static function checkout_old_customer_registration( $customer_id, $data )
    {
	    if ( ! self::is_registration_enabled() ) {
		    return;
	    }

        if ( 'checkout-old-customer' !== self::$reg_origin ) {
            return;
        }

	    $registration_module = Registration_Module::get_instance();
	    $reg_origin = 'wc-checkout';

	    // Email field should be set on the validation process from the logged-in user email. If it wasn't set, set it now from the user id
	    if( empty( $registration_module->get_member_field_value('email') ) ) {
		    $user = get_userdata( $customer_id );
		    $email = $user ? $user->user_email : '';
		    $registration_module->set_member_field_value('email', $email );
        }


	    if ( ! empty( $data['billing_first_name'] ) ) {
		    $registration_module->set_member_field_value( 'first_name', $data['billing_first_name'] );
        }
	    if ( ! empty( $data['billing_last_name'] ) ) {
		    $registration_module->set_member_field_value( 'last_name', $data['billing_last_name'] );
	    }

	    // Save new member
	    $member_id = $registration_module->register_new_user_member( $customer_id, $reg_origin );

	    if ( (int) $member_id > 0 ) {
		    try {
			    $customer = new WC_Customer( $customer_id );

			    self::update_customer_billing_phone( $registration_module, $customer );

			    $customer->save();

		    } catch ( exception $e) {
			    tlr_write_log( 'TLR Error: Failed to update customer billing phone for old customer checkout registration - ' . $e->getMessage());
		    }

		    /**
		     * Fires after after an existing customer registers via checkout
             *
             * @see \Texteller\Modules\WooCommerce\Notifications::checkout_old_customer_registered()
             *
             * @param \Texteller\Member $member
             * @param int $customer_id Linked user ID of the registered member
		     */
		    do_action( 'tlr_wc_checkout_old_customer_registered', $registration_module->get_member(), $customer_id );
	    }
    }

    public static function edit_account_member_registration( $user_id )
    {
	    if ( ! self::is_registration_enabled() ) {
		    return;
	    }

        if ( 'account-edit' !== self::$reg_origin ) {
            return;
        }

        $register_member = Registration_Module::get_instance();

        $member_id = $register_member->get_member_field_value('id' );

        if ( $member_id ) {

	        try {
		        $customer = new WC_Customer( $user_id );

		        self::update_customer_billing_phone( $register_member, $customer );

		        if ( $customer->get_first_name() !== $register_member->get_member_field_value('first_name') ) {
			        $register_member->set_member_field_value('first_name', $customer->get_first_name() );
		        }
		        if ( $customer->get_last_name() !== $register_member->get_member_field_value('last_name') ) {
			        $register_member->set_member_field_value('last_name', $customer->get_last_name() );
		        }

		        $register_member->save_member();
		        $customer->save();

		        /**
		         * Fires after a customer with a linked member updates their data on WC My-Account page
                 *
                 * @see \Texteller\Modules\WooCommerce\Notifications::account_updated()
                 *
                 * @param \Texteller\Member
                 * @param int $user_id
		         */
		        do_action( 'tlr_wc_account_member_updated', $register_member->get_member(), $user_id );


	        } catch ( exception $e) {
		        tlr_write_log( 'TLR Error: Failed to update customer billing phone for old customer account edit registration - ' . $e->getMessage());
	        }

        } else {

            // Register new member
	        $member_id = $register_member->register_new_user_member( $user_id, 'wc-account-edit' );

	        if ( $member_id > 0 ) {
		        try {
			        $customer = new WC_Customer( $user_id );

			        self::update_customer_billing_phone( $register_member, $customer );

			        if ( !empty ( $customer->get_first_name() ) ) {
				        $register_member->set_member_field_value('first_name', $customer->get_first_name() );
			        }
			        if ( !empty ( $customer->get_last_name() ) ) {
				        $register_member->set_member_field_value('last_name', $customer->get_last_name() );
			        }

			        $register_member->save_member();
			        $customer->save();

		        } catch ( exception $e) {
			        tlr_write_log( 'TLR Error: Failed to update customer billing phone for old customer account edit registration - ' . $e->getMessage());
		        }

		        /**
		         * Fires after an existing customer registers via My-Account page
		         *
		         * @see \Texteller\Modules\WooCommerce\Notifications::existing_customer_account_registration_notifications()
		         *
		         * @param \Texteller\Member $member
		         * @param int $user_id Linked user ID of the registered member
		         */
		        do_action( 'tlr_wc_account_existing_customer_registered', $register_member->get_member(), $user_id );
	        }
        }
    }

	/**
     * Update member first and last names, if it's empty and customer has these names.
     * e.g. new customer registered via checkout as a new member with no names.
     *
	 * @param Registration_Module $registration_module
	 * @param WC_Customer $customer
	 */
	private static function update_empty_member_names( Registration_Module &$registration_module, WC_Customer $customer  )
    {
	    $first_name = $registration_module->get_member_field_value('first_name');
	    if ( empty($first_name) && ! empty( $customer->get_first_name() ) ) {
		    $registration_module->set_member_field_value('first_name', $customer->get_first_name() );
	    }

	    $last_name = $registration_module->get_member_field_value('last_name');
	    if ( empty($last_name) && ! empty( $customer->get_last_name() ) ) {
		    $registration_module->set_member_field_value('last_name', $customer->get_last_name() );
	    }
    }

	/**
     * If customer has no names, update them using member data.
     * e.g. registering via My-Account sign-up form
     *
	 * @param Registration_Module $registration_module
	 * @param WC_Customer $customer
	 */
	private static function update_empty_customer_names( Registration_Module $registration_module, WC_Customer &$customer)
    {
	    $first_name = $registration_module->get_member_field_value('first_name');
	    $last_name = $registration_module->get_member_field_value('last_name');

        if ( ! empty( $first_name ) && empty( $customer->get_first_name() ) ) {
            $customer->set_first_name( $first_name );
            $customer->set_billing_first_name( $first_name );
        }
        if ( ! empty( $last_name ) && '' === $customer->get_last_name() ) {
            $customer->set_last_name( $last_name );
            $customer->set_billing_last_name( $last_name );
        }

        // If the display name is email, or mobile number or random username, update to the user's full name.
        if (
            is_email( $customer->get_display_name() )
            || ( 'yes' === get_option('woocommerce_registration_generate_username') && in_array( get_option('tlr_wc_registration_username_generator'), ['national_mobile', 'int_mobile', 'rand_numbers'], true ) )
            || 0 === strpos( $customer->get_display_name(), 'tlr_' )
        ) {
            $names = array_filter([ $first_name,$last_name ]);
            $customer->set_display_name( implode( ' ', $names ) );
        }
    }

	/**
     * Update customer billing phone
     *
	 * @param Registration_Module $registration_module
	 * @param WC_Customer $customer
	 */
	private static function update_customer_billing_phone( Registration_Module $registration_module, WC_Customer &$customer )
    {
	    if ( get_option('tlr_wc_registration_update_billing_phone', 'yes') ) {
		    // Update customer billing phone
		    $mobile = $registration_module->get_member_field_value('mobile');
		    $customer->set_billing_phone( $mobile );
	    }
    }

	public static function checkout_update_member_names( $customer_id, $data )
	{
		if ( ! self::is_registration_enabled() ) {
			return;
		}

		if (  ! $customer_id || in_array( self::$reg_origin, ['checkout-old-customer', 'checkout_new_customer'], true ) ) {
			return;
		}

	    if ( 'yes' !== get_option( 'tlr_wc_checkout_registration_update_member_names', 'yes' ) ) {
	        return;
        }

        $member_id = TLR\tlr_get_member_id( $customer_id, 'user_id' );

        if ( ! $member_id ) {
            return;
        }

        $member = new TLR\Member( $member_id );
        if ( $data['billing_first_name'] !== $member->get_first_name() ) {
            $member->set_first_name( $data['billing_first_name'] );
        }

		if ( $data['billing_last_name'] !== $member->get_last_name() ) {
			$member->set_last_name( $data['billing_last_name'] );
		}

		$member->save();
	}

	public static function handle_account_edit_email_requirement( $required_fields )
    {
	    if ( ! self::is_registration_enabled() ) {
		    return $required_fields;
	    }

	    $tlr_fields = TLR\get_form_fields('tlr_wc_registration_form_fields', Options::class);

	    if ( is_array($tlr_fields) && 1 != TLR\tlr_get_var( $tlr_fields['email']['required'] ) ) {
	        if ( isset( $required_fields['account_email'] ) ) {
		        unset( $required_fields['account_email'] );
	        }
        }

        return $required_fields;
    }

	/**
	 * Override WooCommerce functions, methods and templates
	 */
	private static function override_wc_stuff()
	{
		add_action( 'init', function() {
			remove_action( 'wp_loaded', [ 'WC_Form_Handler', 'process_registration' ], 20 );
			remove_action( 'wp_loaded', [ 'WC_Form_Handler', 'process_login' ], 20 );
			remove_action( 'wp_loaded', [ 'WC_Form_Handler', 'process_lost_password' ], 20 );
		} );

		add_action( 'wp_loaded', [self::class, 'override_process_registration'], 20 );
		add_action( 'wp_loaded', [self::class, 'override_process_login'], 20 );
		add_action( 'wp_loaded', [self::class, 'override_process_lost_password'], 20 );

		add_filter( 'woocommerce_locate_template', [ self::class, 'override_wc_templates' ], 10, 2 );
	}

	public static function override_wc_templates( $template, $template_name )
	{
		if ( 'global/form-login.php' === $template_name ) {
			$template = TLR_WC_PATH . '/templates/global/form-login.php';
		} elseif ( 'myaccount/form-login.php' === $template_name ) {
			$template = TLR_WC_PATH . '/templates/myaccount/form-login.php';
		} elseif( 'myaccount/form-lost-password.php' === $template_name ) {
			$template = TLR_WC_PATH . '/templates/myaccount/form-lost-password.php';
		} elseif( 'myaccount/lost-password-confirmation.php' === $template_name ) {
			$template = TLR_WC_PATH . '/templates/myaccount/lost-password-confirmation.php';
		} elseif( 'myaccount/form-edit-account.php' === $template_name ) {
			$template = TLR_WC_PATH . '/templates/myaccount/form-edit-account.php';
		} elseif( 'checkout/form-billing.php' === $template_name ) {
			$template = TLR_WC_PATH . '/templates/checkout/form-billing.php';
		}

		return $template;
	}

	/**
	 * Process the registration form ("My Account" page)
	 * We override the original WC method
	 * Modified: Sets empty email, if it's null & Sets registration origin
     *
     * @see \WC_Form_Handler::process_registration()
	 *
	 * @version 3.7.1
	 * @throws Exception On registration error.
	 */
	public static function override_process_registration()
	{
		$nonce_value = isset( $_POST['_wpnonce'] ) ? wp_unslash( $_POST['_wpnonce'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$nonce_value = isset( $_POST['woocommerce-register-nonce'] ) ? wp_unslash( $_POST['woocommerce-register-nonce'] ) : $nonce_value; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// <Modified>
        if ( ! empty( $_POST ) && wp_verify_nonce( $nonce_value, 'woocommerce-register' ) ) {
	        $_POST['email'] = isset($_POST['email']) ? $_POST['email'] : '';
        }
        // </Modified>

		if ( isset( $_POST['register'], $_POST['email'] ) && wp_verify_nonce( $nonce_value, 'woocommerce-register' ) ) {
			$username = 'no' === get_option( 'woocommerce_registration_generate_username' ) && isset( $_POST['username'] ) ? wp_unslash( $_POST['username'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$password = 'no' === get_option( 'woocommerce_registration_generate_password' ) && isset( $_POST['password'] ) ? $_POST['password'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			$email    = wp_unslash( $_POST['email'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			try {
				$validation_error  = new WP_Error();
				$validation_error  = apply_filters( 'woocommerce_process_registration_errors', $validation_error, $username, $password, $email );
				$validation_errors = $validation_error->get_error_messages();

				if ( 1 === count( $validation_errors ) ) {
					throw new Exception( $validation_error->get_error_message() );
				} elseif ( $validation_errors ) {
					foreach ( $validation_errors as $message ) {
						wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> ' . $message, 'error' );
					}
					throw new Exception();
				}

				self::$reg_origin = 'account_registration'; // <Modified>

				$new_customer = wc_create_new_customer( sanitize_email( $email ), wc_clean( $username ), $password );

				if ( is_wp_error( $new_customer ) ) {
					throw new Exception( $new_customer->get_error_message() );
				}

				if ( 'yes' === get_option( 'woocommerce_registration_generate_password' ) ) {
					wc_add_notice( __( 'Your account was created successfully and a password has been sent to your email address.', 'woocommerce' ) );
				} else {
					wc_add_notice( __( 'Your account was created successfully. Your login details have been sent to your email address.', 'woocommerce' ) );
				}

				// Only redirect after a forced login - otherwise output a success notice.
				if ( apply_filters( 'woocommerce_registration_auth_new_customer', true, $new_customer ) ) {
					wc_set_customer_auth_cookie( $new_customer );

					if ( ! empty( $_POST['redirect'] ) ) {
						$redirect = wp_sanitize_redirect( wp_unslash( $_POST['redirect'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					} elseif ( wc_get_raw_referer() ) {
						$redirect = wc_get_raw_referer();
					} else {
						$redirect = wc_get_page_permalink( 'myaccount' );
					}

					wp_redirect( wp_validate_redirect( apply_filters( 'woocommerce_registration_redirect', $redirect ), wc_get_page_permalink( 'myaccount' ) ) ); //phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
					exit;
				}
			} catch ( Exception $e ) {
				if ( $e->getMessage() ) {
					wc_add_notice( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> ' . $e->getMessage(), 'error' );
				}
			}
		}
	}

	/**
	 * Process the login form.
     * Modified: Sets username if a mobile number entered as username & hides username in the case of incorrect login details
     *
     * @see \WC_Form_Handler::process_login()
	 *
	 * @throws Exception On login error.
	 */
	public static function override_process_login()
	{
		// The global form-login.php template used `_wpnonce` in template versions < 3.3.0.
		$nonce_value = wc_get_var( $_REQUEST['woocommerce-login-nonce'], wc_get_var( $_REQUEST['_wpnonce'], '' ) ); // @codingStandardsIgnoreLine.

		if ( isset( $_POST['login'], $_POST['username'], $_POST['password'] ) && wp_verify_nonce( $nonce_value, 'woocommerce-login' ) ) {

			try {
				$creds = array(
					'user_login'    => trim( wp_unslash( $_POST['username'] ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					'user_password' => $_POST['password'], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
					'remember'      => isset( $_POST['rememberme'] ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				);

				// <Modified> : if a mobile number entered instead of username
				$maybe_user = get_user_by( is_email( $creds['user_login'] ) ? 'email' : 'login', $creds['user_login'] );
				if ( ! $maybe_user ) {
					$default_countries = get_option( 'tlr_frontend_default_countries', ['US'] );
					$mobile_user = TLR\tlr_get_possible_user( $creds['user_login'], $default_countries );

					if ( $mobile_user ) {
						$mobile_number = $creds['user_login'];
						$creds['user_login'] = $mobile_user->get('user_login') ? $mobile_user->get('user_login') : $creds['user_login'];
					}
				}
				// </Modified>

				$validation_error = new WP_Error();
				$validation_error = apply_filters( 'woocommerce_process_login_errors', $validation_error, $creds['user_login'], $creds['user_password'] );

				if ( $validation_error->get_error_code() ) {
					throw new Exception( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> ' . $validation_error->get_error_message() );
				}

				if ( empty( $creds['user_login'] ) ) {
					throw new Exception( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> ' . __( 'Username is required.', 'woocommerce' ) );
				}

				// On multisite, ensure user exists on current site, if not add them before allowing login.
				if ( is_multisite() ) {
					$user_data = get_user_by( is_email( $creds['user_login'] ) ? 'email' : 'login', $creds['user_login'] );

					if ( $user_data && ! is_user_member_of_blog( $user_data->ID, get_current_blog_id() ) ) {
						add_user_to_blog( get_current_blog_id(), $user_data->ID, 'customer' );
					}
				}

				// Perform the login.
				$user = wp_signon( apply_filters( 'woocommerce_login_credentials', $creds ), is_ssl() );

				if ( is_wp_error( $user ) ) {
					// <Modified> Prevent user_login to be revealed by attempting to log in using the mobile number, due to privacy concerns.
					$message = $user->get_error_message();
					if ( isset( $mobile_user, $mobile_number ) ) {
						$message = str_replace( '<strong>' . esc_html( $creds['user_login'] ) . '</strong>', '<strong>' . esc_html( $mobile_number ) . '</strong>', $message );
					}
					$message = str_replace( '<strong>' . esc_html( $creds['user_login'] ) . '</strong>', '<strong>' . esc_html( $creds['user_login'] ) . '</strong>', $message );
					// </Modified>
					throw new Exception( $message );
				} else {

					if ( ! empty( $_POST['redirect'] ) ) {
						$redirect = wp_unslash( $_POST['redirect'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					} elseif ( wc_get_raw_referer() ) {
						$redirect = wc_get_raw_referer();
					} else {
						$redirect = wc_get_page_permalink( 'myaccount' );
					}

					wp_redirect( wp_validate_redirect( apply_filters( 'woocommerce_login_redirect', remove_query_arg( 'wc_error', $redirect ), $user ), wc_get_page_permalink( 'myaccount' ) ) ); // phpcs:ignore
					exit;
				}
			} catch ( Exception $e ) {
				wc_add_notice( apply_filters( 'login_errors', $e->getMessage() ), 'error' );
				do_action( 'woocommerce_login_failed' );
			}
		}
	}

	/**
     * Handle lost password form.
     *
     * Modified: Overrides retrieve password method
     *
	 * @see \WC_Form_Handler::process_lost_password()
	 */
	public static function override_process_lost_password()
	{
		if ( isset( $_POST['wc_reset_password'], $_POST['user_login'] ) ) {
			$nonce_value = wc_get_var( $_REQUEST['woocommerce-lost-password-nonce'], wc_get_var( $_REQUEST['_wpnonce'], '' ) ); // @codingStandardsIgnoreLine.

			if ( ! wp_verify_nonce( $nonce_value, 'lost_password' ) ) {
				return;
			}

			// <Modified>
			$success = self::retrieve_password();
			// </Modified>

			// If successful, redirect to my account with query arg set.
			if ( $success ) {
				wp_safe_redirect( add_query_arg( 'reset-link-sent', 'true', wc_get_account_endpoint_url( 'lost-password' ) ) );
				exit;
			}
		}
	}

	/**
     * Handles sending password retrieval email to customer.
     * Modified: Finds user by mobile number & edits error messages & adds extra action hook
     *
     * @see \WC_Shortcode_My_Account::retrieve_password();
     *
	 * @return bool
	 */
	private static function retrieve_password()
	{
		$login = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ) ) : ''; // WPCS: input var ok, CSRF ok.

		if ( empty( $login ) ) {

			// <Modified>
			wc_add_notice( __( 'Enter a username, mobile number or email address.', 'texteller' ), 'error' );
            //</Modified>

			return false;

		} else {
			// Check on username first, as customers can use emails as usernames.
			$user_data = get_user_by( 'login', $login );
		}

		// If no user found, check if it login is email and lookup user based on email.
		if ( ! $user_data && is_email( $login ) && apply_filters( 'woocommerce_get_username_from_email', true ) ) {
			$user_data = get_user_by( 'email', $login );
		}

		$errors = new WP_Error();

		// <Modified>: if no user found by username or email, search users using mobile number
		if ( ! $user_data ) {
			$default_countries = get_option( 'tlr_frontend_default_countries', ['US'] );
			$user_data = TLR\tlr_get_possible_user( $login, $default_countries );
		}
		// </Modified>

		do_action( 'lostpassword_post', $errors, $user_data );

		if ( $errors->get_error_code() ) {
			wc_add_notice( $errors->get_error_message(), 'error' );

			return false;
		}

		if ( ! $user_data ) {
			// <Modified>
			wc_add_notice( __( 'Invalid username, mobile number or email.', 'texteller' ), 'error' );
			// </Modified>

			return false;
		}

		if ( is_multisite() && ! is_user_member_of_blog( $user_data->ID, get_current_blog_id() ) ) {
			wc_add_notice( __( 'Invalid username or email.', 'woocommerce' ), 'error' );

			return false;
		}

		// Redefining user_login ensures we return the right case in the email.
		$user_login = $user_data->user_login;

		do_action( 'retrieve_password', $user_login );

		$allow = apply_filters( 'allow_password_reset', true, $user_data->ID );

		if ( ! $allow ) {

			wc_add_notice( __( 'Password reset is not allowed for this user', 'woocommerce' ), 'error' );

			return false;

		} elseif ( is_wp_error( $allow ) ) {

			wc_add_notice( $allow->get_error_message(), 'error' );

			return false;
		}

		// Get password reset key (function introduced in WordPress 4.4).
		$key = get_password_reset_key( $user_data );

		// Send email notification.
		WC()->mailer(); // Load email classes.
		do_action( 'woocommerce_reset_password_notification', $user_login, $key );

        // <Modified>
		/**
		 * Fires after a reset-password key is generated for a WC customer
         *
         * @see \Texteller\Modules\WooCommerce\Notifications::send_rp_link()
         *
         * @param int User ID
         * @param string $key Generated RP key
		 */
		do_action( 'tlr_wc_reset_password_notification', $user_data->ID, $key );
		// </Modified>

		return true;

	}

	public static function render_simple_input( $field_args, $field_class, $field_value )
	{
		?>
		<p class="woocommerce-form-row woocommerce-form-row--<?=$field_class; ?> form-row form-row-<?=$field_class; ?>">
			<label for="tlr_<?= $field_args['id'] ?>"><?php echo __( $field_args['title'], 'texteller' ) ; if ( $field_args['required'] ) : ?> <span class="required">*</span><?php endif; ?></label>
			<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="tlr_<?= $field_args['id']; ?>" id="tlr_<?= $field_args['id'] ?>" value="<?= $field_value; ?>" />
		</p>
		<?php
	}

	/**
	 * @param $field_id
	 * @param $field_args
	 * @param $fields_class
	 * @param null|TLR\Member $member
	 */
	public static function render_tlr_fields( $field_id, $field_args, $fields_class, $member = null )
	{
	    $clear = '';
	    if ( in_array($fields_class[$field_id], ['first', 'wide']) ) {
	        $clear = ' style="clear:both;"';
        }
		switch ( $field_id ) {
			case 'first_name' :
			case 'last_name' :
				self::render_simple_input( $field_args, $fields_class[$field_id], TLR\tlr_get_posted_field_value( $field_id, $member ) );
				break;

			case 'mobile' :
				if ( is_null($member) || !$member->get_id() || 'yes' === get_option('tlr_wc_enable_number_edit') ) {
					?>
                    <p class="woocommerce-form-row woocommerce-form-row--<?=$fields_class['mobile'];?> form-row form-row-<?=$fields_class['mobile'];?>"<?= $clear; ?>>
                        <label for="tlr_national_mobile">
                            <?php _e('Mobile number','texteller');?> <span class="required">*</span>
                        </label>
                        <input type='text' id='tlr_national_mobile' name='tlr_national_mobile' class='woocommerce-Input woocommerce-Input--text input-text tlr-mobile-field' value='<?= TLR\tlr_get_posted_field_value('mobile', $member ); ?>' data-hidden-input-name='tlr_mobile'>
                    </p>
					<?php
				} else {
					?>
                    <p class="woocommerce-form-row woocommerce-form-row--<?=$fields_class['mobile'];?> form-row form-row-<?=$fields_class['mobile'];?>"<?= $clear; ?>>
                        <label for="tlr_readonly_mobile"><?php _e('Mobile number','texteller');?> <span class="required">*</span>
                            <input type='text' id='tlr_readonly_mobile' disabled class='woocommerce-Input woocommerce-Input--text input-text' value='<?= $member->get_mobile(); ?>'>
                    </p>
                    <?php
				}
				if ( !is_null($member) && $member->get_id() > 0 && !empty($member->get_mobile()) && 'yes' === get_option('tlr_wc_verify_number') ) {
					$number_verification = new TLR\Verify_Number();
					$number_verification->set_member($member);
					echo $number_verification->get_html();
				}
                break;

			case 'email' : ?>
				<p class="woocommerce-form-row woocommerce-form-row--<?=$fields_class['email'];?> form-row form-row-<?=$fields_class['email'];?>"<?= $clear; ?>>
					<label for="reg_email"><?php esc_html_e( 'Email address', 'woocommerce' ); ?>&nbsp;<?php if ($field_args['required']) : ?><span class="required">*</span><?php endif; ?> </label>
					<input type="email" class="woocommerce-Input woocommerce-Input--email input-text" name="email" id="reg_email" autocomplete="email" value="<?= TLR\tlr_get_posted_field_value('email', $member, 'email' ); ?>" />
				</p>
				<?php break;

			case 'title' : ?>
				<p class="woocommerce-form-row woocommerce-form-row--<?=$fields_class['title'];?> form-row form-row-<?=$fields_class['title'];?>"<?= $clear; ?>>
					<label for="tlr_title"><?php _e('Title','texteller'); if ($field_args['required']) : ?> <span class="required">*</span><?php endif; ?></label>
                    <select id='tlr_title' name='tlr_title' class='tlr-data-fields tlr-ltr-field'><?php
                        $posted_title = TLR\tlr_get_posted_field_value('title', $member );
                        ?>
                        <option<?= empty( TLR\tlr_get_posted_field_value('title', $member ) ) ? ' selected' : ''; ?> value=''><?=__('Title', 'texteller');?></option>
                        <option <?php selected( 'mr', $posted_title ); ?> value='mr'><?= __('Mr', 'texteller');?></option>
                        <option <?php selected( 'mrs', $posted_title ); ?> value='mrs'><?= __('Mrs', 'texteller');?></option>
                        <option <?php selected( 'miss', $posted_title ); ?> value='miss'><?= __('Miss', 'texteller');?></option>
                        <option <?php selected( 'ms', $posted_title ); ?> value='ms'><?= __('Ms', 'texteller');?></option>
                    </select>
				</p>
				<?php break;

			case 'member_group' :
				$member_group = TLR\tlr_get_public_member_groups();
                ?>

				<p class="woocommerce-form-row woocommerce-form-row--<?=$fields_class['member_group'];?> form-row form-row-<?=$fields_class['member_group'];?>"<?= $clear; ?>>
					<label for="tlr_member_group"><?php _e('Member group','texteller'); if ($field_args['required']) : ?> <span class="required">*</span><?php endif; ?></label>

					<select id='tlr_member_group' name='tlr_member_group' class='tlr-wc-input tlr-title-field'>
                        <?php $posted_group = TLR\tlr_get_posted_field_value('member_group', $member ); ?>
						<option <?= empty($posted_group) ? 'selected="selected" ' : ''; ?> value=''><?= __('Select', 'texteller'); ?></option>
						<?php
						$posted_group = is_array( $posted_group ) ? !empty( $posted_group ) ? $posted_group[0] : '' : $posted_group;

						foreach ($member_group as $slug => $name) {
							$selected = selected( $slug , $posted_group );
							echo "<option$selected value='$slug'>$name</option>";
						} ?>
					</select>
				</p>
				<?php break;
		}
	}

	public static function render_account_registration_fields()
	{
	    $fields = TLR\get_form_fields('tlr_wc_registration_form_fields', Options::class );
	    $fields_class = get_option('tlr_wc_registration_account_fields_class', []);
	    if ( empty($fields_class) ) {
		    $fields_class = [
		    	'first_name'    =>  'first',
			    'last_name'     =>  'last',
			    'mobile'        =>  'first',
			    'email'         =>  'last',
			    'title'         =>  'first',
			    'member_group'  =>  'last'
		    ];
	    }
		foreach ( (array) $fields as $id => $args) {
			if ( isset( $args['enabled'] ) && 'yes' === $args['enabled'] ) {
				self::render_tlr_fields( $id, $args, $fields_class );
			}
		}
	}

	public static function render_account_edit_fields( array $fields, array $fields_class, \WP_User $user )
	{
		foreach ( (array) $fields as $id => $args) {

			$member_id = TLR\tlr_get_member_id( $user->ID, 'user_id' );
			$member = new TLR\Member( $member_id );

			if ( isset( $args['enabled'] ) && 'yes' === $args['enabled'] ) {
				switch ( $id ) {
					case 'first_name' :
					case 'last_name' :
						break;
					case 'mobile' :
					case 'title' :
					case 'member_group' :
						self::render_tlr_fields( $id, $args, $fields_class, $member );
						break;
					case 'email' : ?>
                        <p class="woocommerce-form-row woocommerce-form-row--<?=$fields_class['email'];?> form-row form-row-<?=$fields_class['email'];?>">
                            <label for="account_email"><?php esc_html_e( 'Email address', 'woocommerce' ); ?>&nbsp;<?php if ($args['required']) : ?><span class="required">*</span><?php endif; ?> </label>
                            <input type="email" class="woocommerce-Input woocommerce-Input--email input-text" name="account_email" id="account_email" autocomplete="email" value="<?php echo esc_attr( $user->user_email ); ?>" />
                        </p>
						<?php break;
				}
			}
		}
	}

	public static function render_checkout_registration_fields()
	{
		$fields = $fields = TLR\get_form_fields('tlr_wc_registration_form_fields', Options::class );
		if ( is_user_logged_in() ) {
			// If old user with no linked member is logged-in
			$fields_class = get_option( 'tlr_wc_registration_old_user_fields_class' );
		} else {
			$fields_class = get_option( 'tlr_wc_registration_checkout_fields_class' );
		}
		if ( empty($fields_class) ) {
			$fields_class = [
				'first_name'    =>  'first',
				'last_name'     =>  'last',
				'mobile'        =>  'first',
				'title'         =>  'first',
				'member_group'  =>  'last'
			];
		}

		foreach ( (array) $fields as $id => $args) {

			if ( isset( $args['enabled'] ) && 'yes' === $args['enabled'] ) {
				switch ( $id ) {
					case 'first_name' :
					case 'last_name' :
						break;

					case 'mobile' :
					case 'title' :
					case 'member_group' :
						self::render_tlr_fields( $id, $args, $fields_class );
						break;
				}
			}
		}
	}

	public static function enqueue_assets()
	{
		if ( ! is_account_page() && ! is_checkout() ) {
			return;
		}

		if ( ! self::is_registration_enabled() ) {
			return;
		}

		// Register and enqueue mobile field assets
		TLR\Texteller::register_mobile_field_assets();
		wp_enqueue_style('tlr-intl-tel-input');
		wp_enqueue_script('tlr-intl-tel-input');
		wp_enqueue_script('tlr-mobile-field');
		$intl_tel_input_options = get_option('tlr_intl_tel_input_options',[
			'initial_country'       =>  'US',
			'country_dropdown'      =>  'yes',
			'preferred_countries'   =>  ['US', 'IN', 'GB']
		]);
		wp_localize_script( 'tlr-mobile-field', 'tlrFrontData', [
			'initialCountry'       =>  $intl_tel_input_options['initial_country'],
			'allowDropdown'        =>  $intl_tel_input_options['country_dropdown'],
			'preferredCountries'   =>  $intl_tel_input_options['preferred_countries'],
			'utilsURL'             =>  TLR_LIBS_URI . '/intl-tel-input/build/js/utils.js'
		]);

		wp_enqueue_style('tlr-wc-registration-form', TLR_WC_URI . '/assets/wc-registration-form.css');

		if ( is_checkout() ) {
			$verification_error = __( 'Please verify your mobile number.' , 'texteller' ); // The message should be same with the one used in checkout_check_number_verification()
            $verify_check = wp_create_nonce( 'tlr-checkout-init-verify' );
            $ajax_url = admin_url( 'admin-ajax.php' );
			$checkout_script = "jQuery(document).ready(function ($)
{
    $('.tlr-mobile-field').on('keyup focusout change', function () {
        $('input[name=\"tlr_mobile\"]').val(window.tlrMobileField.intlTelInput.instance.getNumber());
    });
    $( document.body ).on( 'init_checkout', function() {
        $('input[name=\"tlr_mobile\"]').val(window.tlrMobileField.intlTelInput.instance.getNumber());
    });
    $( document.body ).on( 'checkout_error', function() {
        $('input[name=\"tlr_mobile\"]').val(window.tlrMobileField.intlTelInput.instance.getNumber());
        let errorText = $('.woocommerce-error').find('li').first().text().trim();
        let mobileInput = $('#tlr_mobile');
        let verifyWrap = $('.tlr-verify-wrap');

        if ( errorText === '$verification_error' && mobileInput.val() && ! verifyWrap.length ) {
            let postData = {
                'tlr_mobile': mobileInput.val(),
                'tlr_security': '$verify_check',
                'action': 'tlr_wc_checkout_init_verify'
            };
            $.post('$ajax_url', postData, function( response )
            {
                let data = JSON.parse( response );

                if ( data.hasOwnProperty('html') ) {
                    //verifyWrap.html( data.html );
                    $('<div class=\"tlr-verify-wrap\"></div>').insertAfter( '.woocommerce-NoticeGroup-checkout' ).html( data.html );
                }
            });
        }
    });
});";
			wp_add_inline_script( 'tlr-mobile-field', $checkout_script, 'after' );
		}
	}

	private static function is_registration_enabled()
	{
	    if ( self::$is_enabled === null ) {
		    self::$is_enabled = 'yes' === get_option( 'tlr_wc_is_registration_enabled', 'yes' );
        }
		return self::$is_enabled;
	}
}