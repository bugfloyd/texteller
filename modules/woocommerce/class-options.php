<?php

namespace Texteller\Modules\WooCommerce;
use Texteller as TLR;

defined( 'ABSPATH' ) || exit;

final class Options implements TLR\Interfaces\Options
{
	use TLR\Traits\Options_Base;

	/**
	 * Options constructor.
	 */
	public function __construct()
	{
		$this->init_option_hooks();
	}

	/**
	 * @see \Texteller\Interfaces\Options::init_option_hooks()
	 */
	public function init_option_hooks()
	{
		add_filter( 'texteller_option_pages', [self::class, 'add_option_pages'], 25 );
		add_action( 'admin_init', [$this, 'register_module_options' ] );
		add_action( 'update_option_tlr_wc_registration_form_fields', [self::class, 'process_saved_fields_data'], 10, 2);
	}

	/**
	 * @see \Texteller\Interfaces\Options::add_option_tabs()
	 *
	 * @param array $tabs
	 *
	 * @return array
	 */
	public static function add_option_pages( array $tabs ) : array
	{
		$tabs[] = [
			'slug'  =>  'tlr_woocommerce',
			'title' =>  __('WooCommerce', 'texteller')
		];
		return $tabs;
	}

	/**
	 * @see \Texteller\Interfaces\Options::get_option_tags()
	 *
	 * @return TLR\Tags
	 */
	public static function get_option_tags() : TLR\Tags
	{
		$tags = self::get_the_tags();

		$customer_tags = [
			'login_link'    =>  __('WC my-account link', 'texteller')
		];

		$new_customer_tags = [
			'rp_link'     =>  __('WC set password link', 'texteller'),
		];

		$lost_customer_tags = [
			'rp_link'     =>  __('WC reset password link', 'texteller'),
		];

		$order_tags = [
			'first_name'        =>  __( 'first name', 'texteller' ),
			'last_name'         =>  __( 'last name', 'texteller' ),
			'status'            =>  __( 'order status', 'texteller' ),
			'total'             =>  __( 'order total', 'texteller' ),
			'transaction_id'    =>  __( 'transaction ID', 'texteller' ),
			'order_id'          =>  __( 'order ID', 'texteller' ),
			'date'              =>  __( 'order date', 'texteller' ),
			'items'             =>  __( 'order items', 'texteller' )
		];

		$tags->add_tag_type_data( 'customer', array_merge( self::get_base_tags_array('member'), $customer_tags ) );
		$tags->add_tag_type_data( 'new_customer', array_merge( self::get_base_tags_array('member'), $customer_tags, $new_customer_tags ) );
		$tags->add_tag_type_data( 'lost_customer', array_merge( self::get_base_tags_array('member'), $customer_tags, $lost_customer_tags ) );
		$tags->add_tag_type_data( 'order', $order_tags );

		return $tags;
	}

	/**
	 * @see \Texteller\Interfaces\Options::register_module_options()
	 */
	public function register_module_options()
	{
		self::register_sections();

		$this->register_general_options();
		$this->register_registration_fields_options();
		$this->register_registration_behaviour_options();
		$this->register_registration_notifications_options();
		$this->register_order_notifications_options();
	}

	/**
	 * @see \Texteller\Interfaces\Options::register_sections()
	 */
	public static function register_sections()
	{
		self::register_section([
			'id'    =>  'wc_general',
			'title' =>  _x( 'General', 'Module settings tab title', 'texteller'),
			'desc'  =>  __( 'Manage WooCommerce module general options', 'texteller'),
			'class' =>  'description',
			'page'  =>  'tlr_woocommerce'
		]);
		self::register_section([
			'id'    =>  'wc_registration_fields',
			'title' =>  _x( 'Registration Form Fields', 'Module settings tab title', 'texteller'),
			'desc'  =>  __( 'Configure WooCommerce customer registration form fields', 'texteller'),
			'class' =>  'description',
			'page'  =>  'tlr_woocommerce'
		]);
		self::register_section([
			'id'    =>  'wc_registration_behaviour',
			'title' =>  _x( 'Registration & Login Behaviour', 'Module settings tab title', 'texteller'),
			'desc'  =>  __( 'Modify the registration and login process', 'texteller'),
			'class' =>  'description',
			'page'  =>  'tlr_woocommerce'
		]);
		self::register_section([
			'id'    =>  'wc_registration_notifications',
			'title' =>  _x('Registration & Login Notifications', 'Module settings tab title', 'texteller'),
			'desc'  =>  __("Manage WooCommerce registration and login related notifications", 'texteller'),
			'class' =>  'description',
			'page'  =>  'tlr_woocommerce'
		]);
		self::register_section([
			'id'    =>  'wc_order_notifications',
			'title' =>  _x('Order Notifications', 'WC module settings tab title', 'texteller'),
			'desc'  =>  __('Configure WooCommerce order notifications', 'texteller'),
			'class' =>  'description',
			'page'  =>  'tlr_woocommerce'
		]);
	}

	/**
	 * Registers options in the general section
	 */
	private function register_general_options()
	{
		$fields = [
			[
				'id'            =>  'tlr_wc_is_registration_enabled',
				'title'         =>  __('WooCommerce Costumer Integration', 'texteller'),
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_general',
				'field_args'    =>  [ 'default' => 'yes'],
				'desc'          =>  __('Enabling this will integrate Texteller member registration module with WooCommerce customers registration and login behaviour (WooCommerce checkout and MyAccount page).','texteller'),
				'helper'        =>  sprintf( /* translators: %s: WooCommerce Settings */
					__('In order for this module to be activated, customers should be allowed to create an account on Checkout and/or My Account pages. These settings can be configured from %s page.', 'texteller'),
					sprintf('<a href="'. admin_url('admin.php?page=wc-settings&tab=account') .'" target="_blank">%s</a>', __('WooCommerce Settings', 'texteller') )
				),
				'type'          =>  'input',
				'params'        =>  [
					'label'     =>  __( 'Enable WooCommerce costumer integration', 'texteller' ),
					'type'    =>    'checkbox'
				]
			]
		];
		self::register_options( $fields );
	}

	/**
	 * Default registration form fields. Defines available fields to be displayed on the registration form
	 * User may override each field option
	 *
	 * @return array Registration form fields
	 */
	public static function get_default_fields()
	{
		return [
			'first_name'    =>  [
				'enabled'   =>  'yes',
				'id'        =>  'first_name',
				'title'     =>  __('First Name', 'texteller'),
				'size'      =>  'half',
				'required'  =>  0
			],
			'last_name' =>  [
				'enabled'   =>  'yes',
				'id'        =>  'last_name',
				'title'     =>  __('Last Name', 'texteller'),
				'size'      =>  'half',
				'required'  =>  0
			],
			'mobile'    =>  [
				'enabled'   =>  'yes',
				'id'        =>  'mobile',
				'title'     =>  __('Mobile Number', 'texteller'),
				'size'      =>  'half',
				'required'  =>  1
			],
			'email'     =>  [
				'enabled'   =>  'yes',
				'id'        =>  'email',
				'title'     =>  __('Email', 'texteller'),
				'size'      =>  'half',
				'required'  =>  0
			],
			'title'    =>  [
				'enabled'   =>  '',
				'id'        =>  'title',
				'title'     =>  __('Title', 'texteller'),
				'size'      =>  'half',
				'required'  =>  0
			],
			'member_group'   =>  [
				'enabled'   =>  '',
				'id'        =>  'member_group',
				'title'     =>  __('Member Group', 'texteller'),
				'size'      =>  'half',
				'required'  =>  0
			]
		];
	}

	/**
	 * Registers options in the registration fields section
	 */
	private function register_registration_fields_options()
	{
		$fields = [
			[
				'id'            =>  'tlr_wc_registration_form_fields',
				'title'         =>  __('Form Fields', 'texteller'),
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_registration_fields',
				'helper'        =>  __("Email can become an optional field if it's set as such, or it can be totally removed from the form by unchecking the field, in this option.",'texteller')
				                    . '<br>' .
				                    __( 'On Checkout form, Billing First Name and Billing Last Name fields will be used as first and last name. Thus the configurations for the related two fields on this option will not be applied there.', 'texteller' )
				                    . '<br>' .
				                    __( "If checkout registration option for logged-in customers (who don't have a linked member) is enabled in Registration and Login Behaviour tab, their own email will be used and Email field in this option will not be considered.", 'texteller' )
				                    . '<br>' .
				                    __( 'On Edit Account endpoint of  WooCommerce My Account page, first and last name will be updated with the field values of WooCommerce default name fields. Thus the configurations for the related two fields on this option will not be applied there.', 'texteller' ),
				'field_args'    =>  ['default' => self::get_default_fields()],
				'desc'          =>  __( 'Each enabled field will appear on the form, in the chosen size.', 'texteller' ) . ' '
                                    . __( 'Re-ordering fields can be done by a simple drag and drop.', 'texteller' ) . ' '
                                    . __( 'Editing field labels is also possible via clicking on the title (e.g. First Name) and typing the intended label instead.', 'texteller')
				                    . '<br>' . '<strong>'. __('Note:', 'texteller') .' </strong>' . __('At least one of the fields Mobile Number or Email should be set as enabled.','texteller'),
				'type'          =>  'registration_fields',
				'params'        =>  [
					'size_selection'    => true,
				]
			]
		];

		self::register_options( $fields );
	}

	/**
	 * Registers options in the registration behaviour section
	 */
	private function register_registration_behaviour_options()
	{
		$fields = [
			[
				'id'            =>  'tlr_wc_registration_new_customer_notification_base_gateway',
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_registration_behaviour',
				'title'         =>  __('Base Gateway for New Customer Notification', 'texteller'),
				'field_args'    =>  ['default'=>'wc_default'],
				'desc'          =>  __('This will effect new customers who register via My-Account and Checkout pages.', 'texteller')
				                    . ' ' . __('By default, WooCommerce sends an email notification to the new registered customers, which contains their username and password.', 'texteller'),
				'helper'        =>  __( 'In case any option but Email (WooCommerce default) is chosen, the notification message should be customized from Registration and Login Notifications tab.', 'texteller' ),
				'type'          =>  'radio',
				'params'        =>  [
					'values'    =>  [
						'both'      =>  [
							'label' =>  __('Email and Texteller message', 'texteller'),
							'desc'  =>  __('Texteller will try both email and mobile number to reach the customer.','texteller')
						],
						'wc_default'      =>  [
							'label' =>  __('Email (WooCommerce default)', 'texteller'),
							'desc'  =>  sprintf ( /* translators: %s: setting tab title */
								__(' To enable this option, email field should be enabled and set as a required field in %s tab.', 'texteller'),
								'"' .
								sprintf(
									'<a href="'
									. admin_url('admin.php?page=tlr-options&tab=tlr_woocommerce&section=wc_registration_fields')
									. '">%s</a>',
									__('Registration Form Fields', 'texteller')
								)
								. '"'
							),
							'pre'   =>  [
								'tlr_wc_registration_form_fields'    => [
									'email' => [
										'enabled'   =>  'yes',
										'required'  =>  1
									]
								]
							]
						],
						'texteller'      =>  [
							'label' =>  __('Texteller message', 'texteller'),
							'desc'  =>  sprintf ( /* translators: %s: setting tab title */
								__('To enable this option, mobile number should be enabled and set as a required field in %s tab.', 'texteller'),
								'"' .
								sprintf(
									'<a href="'
									. admin_url('admin.php?page=tlr-options&tab=tlr_woocommerce&section=wc_registration_fields')
									. '">%s</a>',
									__('Registration Form Fields ', 'texteller')
								)
								. '"'
							),
							'pre'   =>  [
								'tlr_wc_registration_form_fields'    => [
									'mobile' => [
										'enabled'   =>  'yes',
										'required'  =>  1
									]
								]
							]
						]
					]
				]
			],
			[
				'id'            =>  'tlr_wc_registration_username_generator',
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_registration_behaviour',
				'title'         =>  __('Front end Username Generation Method', 'texteller'),
				'field_args'    =>  ['default'=>'wc_default'],
				'desc'          =>  sprintf( /* translators: %s: WooCommerce settings */
					__('In order to activate these options, automatic username generation for new accounts should be enabled from %s page', 'texteller'),
					sprintf(
						'<a href="'
						. admin_url('admin.php?page=wc-settings&tab=account') .'" target="_blank">%s</a>',
						__('WooCommerce settings', 'texteller') )
				),
				'type'          =>  'radio',
				'extra_options' =>  [
					[
						'id'        =>  'tlr_wc_registration_username_generator_national_mobile',
						'page'      =>  'tlr_wordpress',
						'section'   =>  'wp_registration_behaviour',
						'title'     =>  'National mobile username generator pattern',
						'type'      =>  'hidden'
					]
				],
				'params'        =>  [
					'values'    =>  [
						'wc_default'      =>  [
							'label' =>  __('WooCommerce default (Using name, surname, email)', 'texteller'),
							'desc'  =>  sprintf ( /* translators: %s: title of a setting tab */
								            __('To enable this option, Email field should be enabled in %s tab.', 'texteller'),
								            '"' .
								            sprintf(
								            	'<a href="'
									            . admin_url('admin.php?page=tlr-options&tab=tlr_woocommerce&section=wc_registration_fields')
									            . '">%s</a>',
									            __('Registration Form Fields', 'texteller')
								            ) . '"'
							            )
							            . '<br>' .
							            __( 'If Email field is set as optional and the customer leaves it empty during the registration, the Random Numbers method will be used as the fallback.', 'texteller'),
							'pre'  =>  [
								'tlr_wc_registration_form_fields'    => [
									'email' => [
										'enabled'   =>  'yes'
									]
								]
							]
						],
						'int_mobile' => [
							'label' => __('International mobile number', 'texteller'),
							'desc'  =>  sprintf ( /* translators: %s: title of a setting tab */
								            __('To enable this option, Mobile field should be enabled on %s tab.', 'texteller'),
								            '"' .
								            sprintf(
								            	'<a href="'
									            . admin_url('admin.php?page=tlr-options&tab=tlr_woocommerce&section=wc_registration_fields')
									            . '">%s</a>',
									            __('Registration Form Fields', 'texteller')
								            )
								            . '"'
							            )
							            . '<br>' .
							            __( 'If Mobile field is set as optional and the customer leaves it empty during the registration, the Random Numbers method will be used as the fallback.', 'texteller' ),
							'pre'  =>  [
								'tlr_wc_registration_form_fields'    => [
									'mobile' => [
										'enabled'   =>  'yes'
									]
								]
							]
						],
						'national_mobile'      =>  [
							'label' =>  __('National mobile number', 'texteller'),
							'select' => [
								'id'        =>  'tlr_wc_registration_username_generator_national_mobile',
								'title'     =>  _x( 'Format', 'national number format', 'texteller' ),
								'options'   =>  [
									'leading-zero' => __( 'Prefixed with the leading zero', 'texteller' ),
									'no-leading-zero' => __( 'Without the leading zero', 'texteller' )
								]
							],
							'desc'  =>  sprintf ( /* translators: %s: title of a setting tab */
								            __('To enable this option, Mobile field should be enabled on %s tab.', 'texteller'),
								            '"' .
								            sprintf(
								            	'<a href="'
									            . admin_url('admin.php?page=tlr-options&tab=tlr_woocommerce&section=wc_registration_fields')
									            . '">%s</a>',
									            __('Registration Form Fields', 'texteller')
								            )
								            . '"'
							            )
							            . '<br>' .
							            __( 'If Mobile field is set as optional and the customer leaves it empty during the registration, the Random Numbers method will be used as the fallback.', 'texteller'),
							'pre'  =>  [
								'tlr_wc_registration_form_fields'    => [
									'mobile' => [
										'enabled'   =>  'yes'
									]
								]
							]
						],
						'rand_numbers' => [
							'label' => __('Random Numbers', 'texteller'),
							'desc'  =>  __( 'Using this option, the generated username will be in this format:', 'texteller' ) .
							            ' <code>user_xxxxxx</code>'
						]
					]
				]
			],
			[
				'id'            =>  'tlr_wc_registration_update_customer_names',
				'title'         =>  __('Customer Names Update', 'texteller'),
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_registration_behaviour',
				'field_args'    =>  [ 'default' => 'yes'],
				'helper'        =>  __('User first name, last name, display name, and billing names will be updated.', 'texteller'),
				'type'          =>  'input',
				'params'        =>  [
					'type'  =>  'checkbox',
					'label' =>  __( 'Update name fields on user profile for new customers registered via My-Account page', 'texteller' )
				]
			],
			[
				'id'            =>  'tlr_wc_checkout_registration_update_member_names',
				'title'         =>  __('Member Names Update on Checkout', 'texteller'),
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_registration_behaviour',
				'field_args'    =>  [ 'default' => 'yes'],
				'desc'          =>  __('Member first and last name fields will be updated based on the entered billing first and last name in the checkout process.', 'texteller'),
				'type'          =>  'input',
				'params'        =>  [
					'type'  =>  'checkbox',
					'label' =>  __( 'For logged in customers with an already linked member, update member name fields in the checkout process', 'texteller' )
				]
			],
			[
				'id'            =>  'tlr_wc_checkout_old_customer_registration',
				'title'         =>  __( "Old Customers' Registration via Checkout", 'texteller'),
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_registration_behaviour',
				'field_args'    =>  [ 'default' => 'yes'],
				'desc'          =>  __( 'Member registration form will be displayed for these customers on the Checkout page.', 'texteller'),
				'helper'        =>  __( "These are the existing customers who have not registered via Texteller forms.", 'texteller'),
				'type'          =>  'input',
				'params'        =>  [
					'type'  =>  'checkbox',
					'label' =>  __( 'Allow logged in customers with no linked members, to register via Checkout page', 'texteller' )
				]
			],
			[
				'id'            =>  'tlr_wc_verify_number',
				'title'         =>  __('Number Verification', 'texteller'),
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_registration_behaviour',
				'field_args'    =>  [ 'default' => ''],
				'desc'          =>  __('Mobile number verification will be allowed via Edit Account endpoint of WooCommerce My Account page.','texteller'),
				'type'          =>  'input',
				'params'        =>  [
					'type'  =>  'checkbox',
					'label' =>  __( 'Allow customers to verify their mobile number on My-Account page', 'texteller' )
				]
			],
			[
				'id'            =>  'tlr_wc_checkout_force_verify',
				'title'         =>  __('Force Number Verification on Checkout', 'texteller'),
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_registration_behaviour',
				'field_args'    =>  [ 'default' => ''],
				'desc'          =>  __( 'Customers will not be allowed to place any order before mobile number verification.', 'texteller' ),
				'type'          =>  'input',
				'params'        =>  [
					'type'  =>  'checkbox',
					'label' =>  __( 'Force customers to verify their mobile number on Checkout', 'texteller' )
				]
			],
			[
				'id'            =>  'tlr_wc_checkout_thank_you_verify',
				'title'         =>  __( 'Number Verification after Checkout', 'texteller' ),
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_registration_behaviour',
				'field_args'    =>  [ 'default' => ''],
				'type'          =>  'input',
				'params'        =>  [
					'type'  =>  'checkbox',
					'label' =>  __( 'Ask customers to verify their mobile on "Thank You" page', 'texteller' )
				]
			],
			[
				'id'            =>  'tlr_wc_enable_number_edit',
				'title'         =>  __('Mobile Number Edit', 'texteller'),
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_registration_behaviour',
				'field_args'    =>  [ 'default' => ''],
				'desc'          =>  __('Customers with an existing linked member, will be able to edit their mobile number via Edit Account endpoint of WooCommerce My Account page.', 'texteller'),
				'type'          =>  'input',
				'params'        =>  [
					'type'  =>  'checkbox',
					'label' =>  __( 'Allow customers to update their mobile number via My-Account page', 'texteller' )
				]
			],
			[
				'id'            =>  'tlr_wc_registration_update_billing_phone',
				'title'         =>  __('Billing Phone Update', 'texteller'),
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_registration_behaviour',
				'field_args'    =>  [ 'default' => 'yes'],
				'desc'          =>  __( "When a customer registers via My-Account or Checkout page, Billing Phone will be updated with user's Mobile Number value.", 'texteller' ),
				'helper'        =>  __( 'Texteller removes Billing Phone field from Checkout page to avoid confusions.', 'texteller'),
				'type'          =>  'input',
				'params'        =>  [
					'type'  =>  'checkbox',
					'label' =>  __( 'Update Billing Phone field for new customers', 'texteller' )
				]
			],
			[
				'id'            =>  'tlr_wc_lost_password_base_gateway',
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_registration_behaviour',
				'title'         =>  __('Base Gateway for Forget Password Message', 'texteller'),
				'field_args'    =>  ['default'=>'both'],
				'helper'        =>  __('In case any option but Email (WooCommerce default) is chosen, the notification message should be customized from Registration and Login Notifications tab.', 'texteller'),
				'desc'          =>  __( 'By default, WooCommerce sends a Forget Password email which includes a reset-password link to set a new password.', 'texteller' ),
				'type'          =>  'radio',
				'params'        =>  [
					'values'    =>  [
						'both'      =>  [
							'label' =>  __('Email and Texteller message', 'texteller'),
							'desc'  =>  __( 'Texteller will try both email and mobile number to reach the customer.', 'texteller' )
						],
						'wc_default'      =>  [
							'label' =>  __('Email (WooCommerce default)', 'texteller'),
							'pre'   =>  [
								'tlr_wc_registration_form_fields'    => [
									'email' => [
										'enabled'   =>  'yes',
										'required'  =>  1
									]
								]
							],
							'desc'  =>  sprintf ( /* translators: %s: setting tab title */
								__(' To enable this option, email field should be enabled and set as a required field in %s tab.', 'texteller'),
								'"' .
								sprintf(
									'<a href="'
									. admin_url('admin.php?page=tlr-options&tab=tlr_woocommerce&section=wc_registration_fields')
									. '">%s</a>',
									__('Registration Form Fields', 'texteller')
								)
								. '"'
							)
						],
						'texteller'      =>  [
							'label' =>  __('Texteller message', 'texteller'),
							'desc'  =>  sprintf ( /* translators: %s: setting tab title */
								__('To enable this option, mobile number should be enabled and set as a required field in %s tab.', 'texteller'),
								'"' .
								sprintf(
									'<a href="'
									. admin_url('admin.php?page=tlr-options&tab=tlr_woocommerce&section=wc_registration_fields')
									. '">%s</a>',
									__('Registration Form Fields ', 'texteller')
								)
								. '"'
							),
							'pre'   =>  [
								'tlr_wc_registration_form_fields'    => [
									'mobile' => [
										'enabled'   =>  'yes',
										'required'  =>  1
									]
								]
							],
						],
						'user_choice'      =>  [
							'label' =>  __("User's choice", 'texteller'),
							'desc'  =>  __('Users will be asked to choose their preferred way of receiving the message.', 'texteller')
						]
					]
				]
			]
		];

		self::register_options( $fields );
	}

	/**
	 * Registers options in the registration notifications section
	 */
	private function register_registration_notifications_options()
	{
		$fields = [
			[
				'id'            =>  'tlr_trigger_wc_registration_new_customer',
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_registration_notifications',
				'title'         =>  __('Registration of New Costumer', 'texteller'),
				'field_args'    =>  ['default'=>'both'],
				'type'          =>  'notification',
				'params'        =>  [
					'label'                 =>  __( 'Send notifications when a new costumer is registered', 'texteller' ),
					'tag_type'              =>  'customer',
					'trigger_recipients'    =>  [ 'new_customer' => __( 'New Customer', 'texteller' ) ],
					'recipient_types'       =>  ['staff', 'members', 'numbers']
				]
			],
			[
				'id'            =>  'tlr_trigger_wc_registration_new_customer_new_customer_rp',
				'title'         =>  __( 'Set-password & Welcome Message for new Customers', 'texteller' ),
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_registration_notifications',
				'field_args'    =>  [ 'default' => [] ],
				'desc'          =>  sprintf( /* translators: %s: WooCommerce settings */
					__('In order to include set-password link in this message, sending password links for new customers should be enabled from %s page.', 'texteller'),
					sprintf(
						'<a href="'. admin_url('admin.php?page=wc-settings&tab=account')
						.'" target="_blank">%s</a>',
						__('WooCommerce settings', 'texteller')
					)
				),
				'helper'        =>  sprintf( /* translators: %s: Registration & Login Behaviour */
					                    __('In order for this notification message to be sent, the chosen option in the "Base Gateway for New Customer Notification" section from %s tab, must include "Texteller message".', 'texteller'),
					                    sprintf(
											'<a href="' . admin_url('admin.php?page=tlr-options&tab=tlr_woocommerce&section=wc_registration_behaviour') . '">%s</a>',
											__('Registration & Login Behaviour', 'texteller')
					                    )
				                    )
				                    .'<br>' .
				                    __( 'Note: If "Email and Texteller message" is selected, set password links will not work in Texteller messages.', 'texteller' ),
				'type'          =>  'notification',
				'params'        =>  [
					'label'                 =>  __( 'Send welcome and set-password message on new customer registration', 'texteller' ),
					'recipient_types'       =>  ['trigger'],
					'tag_type'              =>  'new_customer',
					'trigger_recipients'    =>  [ 'new_customer' => __( 'New Customer', 'texteller' ) ]
				]
			],
			[
				'id'            =>  'tlr_trigger_wc_lost_pw_rp_link',
				'title'         =>  __( 'Retrieve-Password for Forgetful Customers', 'texteller' ),
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_registration_notifications',
				'field_args'    =>  [ 'default' => [] ],
				'desc'          =>  __( 'In order for this notification message to be sent, the chosen option in the "Base Gateway for Forget Password Message" section from "Registration & Log Behaviour" tab must include "Texteller message".', 'texteller'),
				'type'          =>  'notification',
				'params'        =>  [
					'label'                 =>  __( 'Send retrieve-password message when a customer asks for the forget-password link', 'texteller' ),
					'tag_type'              =>  'lost_customer',
					'recipient_types'       =>  ['trigger'],
					'trigger_recipients'    =>  [ 'lost_customer' => __( 'Lost Customer', 'texteller' ) ]
				]
			],
			[
				'id'            =>  'tlr_trigger_wc_registration_checkout_old_customer',
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_registration_notifications',
				'title'         =>  __('Member Registration for Existing Customers via Checkout', 'texteller'),
				'field_args'    =>  [ 'default' => [] ],
				'desc'          =>  __( 'This notification will be sent when a logged in customer with no linked member, registers a new member via Checkout page.', 'texteller' ),
				'helper'        =>  __( "This will only work if Old Customers' Registration via Checkout option is enabled from Registration & Log Behaviour tab.", 'texteller' ),
				'pre'           =>  ['tlr_wc_checkout_old_customer_registration' => 'yes'],
				'type'          =>  'notification',
				'params'        =>  [
					'label'                 =>  __( 'Send notification to existing customers on registering via Checkout', 'texteller' ),
					'tag_type'              =>  'customer',
					'trigger_recipients'    =>  [ 'customer' => __( 'Customer', 'texteller' ) ]
				]
			],
			[
				'id'            =>  'tlr_trigger_wc_account_edit_old_customer_registered',
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_registration_notifications',
				'title'         =>  __('Member Registration for Existing Customers via Edit Account', 'texteller'),
				'field_args'    =>  [ 'default' => [] ],
				'class'         =>  'hasContent',
				'desc'          =>  __('This notification will be sent when a logged in customer with no linked member, registers a new member via Edit Account end-point on the My-Account page.', 'texteller'),
				'type'          =>  'notification',
				'params'        =>  [
					'label'                 =>  __( 'Send notification to existing customers on registering via Edit Account', 'texteller' ),
					'tag_type'              =>  'customer',
					'trigger_recipients'    =>  [ 'customer' => __( 'Customer', 'texteller' ) ]
				]
			],
			[
				'id'            =>  'tlr_trigger_wc_registration_account_updated',
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_registration_notifications',
				'title'         =>  __('Account Update', 'texteller'),
				'field_args'    =>  [ 'default' => [] ],
				'desc'          =>  __( 'This notification will be sent when a logged in customer who is already linked to a member, updates account details via Edit Account endpoint on the My-Account page.', 'texteller' ),
				'type'          =>  'notification',
				'params'        =>  [
					'label'                 =>  __( 'Send notification to logged-in customers after updating account details', 'texteller' ),
					'tag_type'              =>  'customer',
					'trigger_recipients'    =>  [ 'customer' => __( 'Customer', 'texteller' ) ]
				]
			]
		];

		self::register_options( $fields );
	}

	/**
	 * Registers options in the order notifications section
	 */
	private function register_order_notifications_options()
	{
		$fields = [
			[
				'id'            =>  'tlr_trigger_wc_new_admin_order',
				'title'         =>  __( 'Admin Dashboard New Order', 'texteller' ),
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_order_notifications',
				'type'          =>  'notification',
				'params'        =>  [
					'label'                 =>  __( 'Send notifications when a new order is placed from admin dashboard', 'texteller' ),
					'tag_type'              =>  'order',
					'trigger_recipients'    =>  [
						'order_customer'    =>  __( 'Customer', 'texteller' ),
						'order_admin'       =>  __( 'Creator Admin', 'texteller' )
					]
				]
			],
			[
				'id'            =>  'tlr_trigger_wc_new_customer_order',
				'title'         =>  __( 'Customer New Order', 'texteller' ),
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_order_notifications',
				'type'          =>  'notification',
				'params'        =>  [
					'label'                 =>  __( 'Send notifications when a new order is placed by a customer', 'texteller' ),
					'tag_type'              =>  'order',
					'trigger_recipients'    =>  [ 'order_customer' => __( 'Customer', 'texteller' ) ]
				]
			],
			[
				'id'            =>  'tlr_trigger_wc_order_status_on_hold',
				'title'         =>  __( 'Order Status Changed to On-Hold', 'texteller' ),
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_order_notifications',
				'type'          =>  'notification',
				'params'        =>  [
					'label'                 =>  __( 'Send notifications when order status changes to On-Hold', 'texteller' ),
					'tag_type'              =>  'order',
					'trigger_recipients'    =>  [ 'order_customer' => __( 'Customer', 'texteller' ) ]
				]
			],
			[
				'id'            =>  'tlr_trigger_wc_order_status_completed',
				'title'         =>  __( 'Order Status Changed to Completed', 'texteller' ),
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_order_notifications',
				'type'          =>  'notification',
				'params'        =>  [
					'label'                 =>  __( 'Send notifications when order status changes to Completed', 'texteller' ),
					'tag_type'              =>  'order',
					'trigger_recipients'    =>  [ 'order_customer' => __( 'Customer', 'texteller' ) ]
				]
			],
			[
				'id'            =>  'tlr_trigger_wc_order_status_completed',
				'title'         =>  __( 'Order Status Changed to Completed', 'texteller' ),
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_order_notifications',
				'type'          =>  'notification',
				'params'        =>  [
					'label'                 =>  __( 'Send notifications when order status changes to Completed', 'texteller' ),
					'tag_type'              =>  'order',
					'trigger_recipients'    =>  [ 'order_customer' => __( 'Customer', 'texteller' ) ]
				]
			],
			[
				'id'            =>  'tlr_trigger_wc_order_status_refunded',
				'title'         =>  __( 'Order Status Changed to Refunded', 'texteller' ),
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_order_notifications',
				'type'          =>  'notification',
				'params'        =>  [
					'label'                 =>  __( 'Send notifications when order status changes to Refunded', 'texteller' ),
					'tag_type'              =>  'order',
					'trigger_recipients'    =>  [ 'order_customer' => __( 'Customer', 'texteller' ) ]
				]
			],
			[
				'id'            =>  'tlr_trigger_wc_order_status_cancelled',
				'title'         =>  __( 'Order Status Changed to Cancelled', 'texteller' ),
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_order_notifications',
				'type'          =>  'notification',
				'params'        =>  [
					'label'                 =>  __( 'Send notifications when order status changes to Cancelled', 'texteller' ),
					'tag_type'              =>  'order',
					'trigger_recipients'    =>  [ 'order_customer' => __( 'Customer', 'texteller' ) ]
				]
			],
			[
				'id'            =>  'tlr_trigger_wc_order_status_failed',
				'title'         =>  __( 'Order Status Changed to Failed', 'texteller' ),
				'page'          =>  'tlr_woocommerce',
				'section'       =>  'wc_order_notifications',
				'type'          =>  'notification',
				'params'        =>  [
					'label'                 =>  __( 'Send notifications when order status changes to Failed', 'texteller' ),
					'tag_type'              =>  'order',
					'trigger_recipients'    =>  [ 'order_customer' => __( 'Customer', 'texteller' ) ]
				]
			]
		];

		self::register_options( $fields );
	}

	/**
	 * Generates and updates registration field size classes.
	 * Updates registration behaviour options based on the enabled and required fields
	 *
	 * @param array $old_value Old option value
	 * @param array $value Updated option value
	 */
	public static function process_saved_fields_data( $old_value, $value )
	{
		$last_size = 'wide';
		$fields_class = [];
		$account_fields_class = [];
		$account_fields_last_size = 'wide';
		$old_user_fields_class = [];
		$old_user_fields_last_size = 'wide';
		$checkout_fields_class = [];
        $checkout_fields_last_size = 'wide';
        $required_mobile = true;
		$required_email = true;

		foreach ( $value as $id => $args ) {

			if ( 'mobile' === $id ) {
				if ( !isset( $args['enabled'] ) || 'yes' !== $args['enabled'] ) {
					$username_generator = get_option( 'tlr_wc_registration_username_generator' );
					if ( 'int_mobile' === $username_generator || 'national_mobile' === $username_generator ) {
						update_option( 'tlr_wc_registration_username_generator', 'wc_default' );
					}
				}
				if (
					!isset( $args['enabled'] ) || 'yes' !== $args['enabled']
					|| ! isset( $args['required'] ) || 1 != $args['required']
				) {
					if ( 'texteller' === get_option( 'tlr_wc_lost_password_base_gateway' ) ) {
						update_option( 'tlr_wc_lost_password_base_gateway', 'both' );
					}
					if ( 'texteller' === get_option( 'tlr_wc_registration_new_customer_notification_base_gateway', 'both' ) ) {
						update_option( 'tlr_wc_registration_new_customer_notification_base_gateway', 'both' );
					}
					$required_mobile = false;
				}
			}

			if ( 'email' === $id ) {
				if ( ! isset( $args['enabled'] ) || 'yes' !== $args['enabled'] ) {
					if ( 'wc_default' === get_option( 'tlr_wc_registration_username_generator' ) ) {
						update_option( 'tlr_wc_registration_username_generator', 'rand_numbers' );
					}
				}
				if (
					! isset( $args['enabled'] ) || 'yes' !== $args['enabled']
					|| ! isset( $args['required'] ) || 1 != $args['required']
				) {
					if ( 'wc_default' === get_option('tlr_wc_lost_password_base_gateway') ) {
						update_option( 'tlr_wc_lost_password_base_gateway', 'both' );
					}
					if ( 'wc_default' === get_option('tlr_wc_registration_new_customer_notification_base_gateway', 'both') ) {
						update_option( 'tlr_wc_registration_new_customer_notification_base_gateway', 'both' );
					}
					$required_email = false;
				}
			}

			// Generate field size class
			if ( isset( $args['enabled'] ) && 'yes' === $args['enabled'] ) {
				if ( in_array( $id, [ 'first_name', 'last_name' ] ) ) {
					$size = isset( $args['size'] ) ? $args['size'] : '';
					if ( 'full' === $size ) {
						$last_size = $account_fields_class[$id] = 'wide';
					} else {
						$last_size = $account_fields_class[$id] = ( $last_size === 'last' || 'wide' === $last_size )
							? 'first' : 'last';
					}
				} elseif( 'email' === $id ) {
					$size = isset( $args['size'] ) ? $args['size'] : '';
					if ( 'full' === $size ) {
						$last_size = $account_fields_class[$id] = 'wide';
						$account_fields_last_size = $fields_class[$id] = 'wide';
					} else {
						$last_size = $account_fields_class[$id] = ( $last_size === 'last' || 'wide' === $last_size )
							? 'first' : 'last';
						$account_fields_last_size = $fields_class[$id] =
							( 'last' === $account_fields_last_size || 'wide' === $account_fields_last_size )
								? 'first' : 'last';
					}
				} else {
					$size = isset( $args['size'] ) ? $args['size'] : '';
					if ( 'full' === $size ) {
						$last_size = $account_fields_class[$id] = 'wide';
						$account_fields_last_size = $fields_class[$id] = 'wide';
						$old_user_fields_last_size = $old_user_fields_class[$id] = 'wide';
                        $checkout_fields_last_size = $checkout_fields_class[$id] = 'wide';
					} else {
						$last_size = $account_fields_class[$id] = ( $last_size === 'last' || 'wide' === $last_size )
							? 'first' : 'last';
						$account_fields_last_size = $fields_class[$id] =
							( $account_fields_last_size === 'last' || 'wide' === $account_fields_last_size )
								? 'first' : 'last';
						$old_user_fields_last_size = $old_user_fields_class[$id] =
							( 'last' === $old_user_fields_last_size || 'wide' === $old_user_fields_last_size )
								? 'first' : 'last';
                        $checkout_fields_last_size = $checkout_fields_class[$id] =
                            ( 'last' === $checkout_fields_last_size || 'wide' === $checkout_fields_last_size )
                                ? 'first' : 'last';
					}
				}
			}
		}

		// Update field size class
		update_option( 'tlr_wc_registration_account_fields_class', $account_fields_class );
		update_option( 'tlr_wc_registration_fields_class', $fields_class );
		update_option( 'tlr_wc_registration_old_user_fields_class', $old_user_fields_class );
        update_option( 'tlr_wc_registration_checkout_fields_class', $checkout_fields_class );


        // Both email and mobile fields could not be disabled or set as optional.
		if ( ! $required_mobile && ! $required_email ) {
			if ( isset($value['email']['enabled']) && 'yes' === $value['email']['enabled'] ) {
				$value['email']['required'] = 1;
			} elseif ( isset($value['mobile']['enabled']) && 'yes' == $value['mobile']['enabled'] ) {
				$value['mobile']['required'] = 1;
			} else {
				$value['email']['enabled'] = 'yes';
				$value['email']['required'] = 1;
			}
			update_option('tlr_wc_registration_form_fields', $value );
		}
	}
}