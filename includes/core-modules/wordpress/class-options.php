<?php

namespace Texteller\Core_Modules\WordPress;
use Texteller as TLR;

defined( 'ABSPATH' ) || exit;

final class Options implements TLR\Interfaces\Options
{
	use TLR\Traits\Options_Base;

	public function __construct()
	{
		$this->init_option_hooks();
	}

	/**
	 * @see \Texteller\Interfaces\Options::init_option_hooks()
	 */
	public function init_option_hooks()
	{
		add_filter( 'texteller_option_pages', [ self::class, 'add_option_pages' ], 20 );
		add_action( 'admin_init', [ $this, 'register_module_options' ], 15 );
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
			'slug'  =>  'tlr_wordpress',
			'title' =>  __( 'WordPress', 'texteller' ),
		];
		return $tabs;
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
		$this->register_post_notifications_options();
	}

	/**
	 * @see \Texteller\Interfaces\Options::register_sections()
	 */
	public static function register_sections()
	{
		self::register_section([
			'id'    =>  'wp_general',
			'title' =>  _x('General', 'Module settings tab title', 'texteller'),
			'desc'  =>  __('Manage general options for WordPress user registration integration', 'texteller'),
			'class' =>  'description',
			'page'  =>  'tlr_wordpress'
		]);

		self::register_section([
			'id'    =>  'wp_registration_fields',
			'title' =>  _x('Registration Form Fields', 'Module settings tab title', 'texteller'),
			'desc'  =>  __('Configure WordPress user registration form fields', 'texteller'),
			'class' =>  'description',
			'page'  =>  'tlr_wordpress'
		]);

		self::register_section([
			'id'    =>  'wp_registration_behaviour',
			'title' =>  _x('Registration & Login Behaviour', 'Module settings tab title', 'texteller'),
			'desc'  =>  __("Modify the registration and login process", 'texteller'),
			'class' =>  'description',
			'page'  =>  'tlr_wordpress'
		]);

		self::register_section([
			'id'    =>  'wp_registration_notifications',
			'title' =>  _x('Registration & Login Notifications', 'Module settings tab title', 'texteller'),
			'desc'  =>  __('Manage Wordpress registration and login related notifications', 'texteller'),
			'class' =>  'description',
			'page'  =>  'tlr_wordpress'
		]);

		self::register_section([
			'id'    =>  'wp_post_notifications',
			'title' =>  _x('Post Notifications', 'Module settings tab title', 'texteller'),
			'desc'  =>  __('Configure notifications for WordPress posts', 'texteller'),
			'class' =>  'description',
			'page'  =>  'tlr_wordpress'
		]);
	}

	/**
	 * Registers options in the general section
	 */
	private function register_general_options()
	{
		$fields = [
			[
				'id'            =>  'tlr_wp_is_registration_enabled',
				'title'         =>  __( 'WordPress User Integration', 'texteller' ),
				'page'          =>  'tlr_wordpress',
				'section'       =>  'wp_general',
				'field_args'    =>  ['default' => 'yes'],
				'helper'        =>  __( 'With this integration, email field can become optional or be removed completely, in which case, users can register and login using their phone numbers.', 'texteller')
				                    . ' ' . __( 'The next two tabs are involved with configuration of related options.', 'texteller'),
				'desc'          =>  __( 'Enabling this will integrate Texteller member registration module with WordPress user registration and login behaviour (wp-login.php & Add/Edit User from admin dashboard).', 'texteller' ),
				'type'          =>  'input',
				'params'        =>  [
					'type'  =>  'checkbox',
					'label' =>  __('Enable WordPress user integration', 'texteller')
				]
			]
		];

		self::register_options( $fields );
	}

	/**
	 * Registers options in the registration fields section
	 */
	private function register_registration_fields_options()
	{
		$fields = [
			[
				'id'            =>  'tlr_wp_registration_form_fields',
				'title'         =>  __( 'Form Fields', 'texteller' ),
				'page'          =>  'tlr_wordpress',
				'section'       =>  'wp_registration_fields',
				'field_args'    =>  ['default' => self::get_default_fields()],
				'desc'          =>  __( 'Each enabled field will appear on the form, in the chosen size.', 'texteller')
				                    . ' ' . __( 'Re-ordering fields can be done by a simple drag and drop.', 'texteller')
				                    . ' ' . __( 'Editing field labels is also possible via clicking on the title (e.g. First Name) and typing the intended label instead.', 'texteller' )
				                    . '<br>' .
				                    '<strong>'. __('Note:', 'texteller') .' </strong>' .
				                    __('At least one of the fields Mobile Number or Email should be set as enabled.','texteller'),
				'helper'        =>  __( 'This option controls Wordpress frontend user registration form fields (wp-login.php).', 'texteller' )
				                    . '<br>' .
				                    __("Email can become an optional field if it's set as such, or it can be totally removed from the form by unchecking the field, in this option.",'texteller'),
				'type'          =>  'registration_fields',
				'params'        =>      [
					'size_selection' => false
				]
			],
			[
				'id'            =>  'tlr_wp_registration_frontend_username',
				'page'          =>  'tlr_wordpress',
				'section'       =>  'wp_registration_fields',
				'title'         =>  __( 'Username Field on Frontend Registration', 'texteller' ),
				'field_args'    =>  ['default' => 'wp_default'],
				'desc'          =>  __('These options are about modifying the username field on Wordpress frontend registration form (wp-login.php).', 'texteller'),
				'type'          =>  'radio',
				'params'        =>  [
					'values'    =>  [
						'wp_default'      =>  [
							'label' =>  __('WordPress default', 'texteller' ),
							'desc'  =>  __( 'Keep username as a required field.', 'texteller'),
						],
						'hide' => [
							'label' => __('Hide username field', 'texteller'),
							'desc'  =>  __( 'An auto-generated username will be used.', 'texteller') . '<br>'
							            . sprintf(/* translators: %s: title of a setting tab */ 'Username auto-generation settings can be configured on the %s tab.', sprintf(
							            	'<a href="' . admin_url('admin.php?page=tlr-options&tab=tlr_wordpress&section=wp_registration_behaviour')
								            . '">%s</a>', __( 'Registration & Login Behaviour', 'texteller') )
							            ),
						],
						'optional'      =>  [
							'label' =>  __('Optional field', 'texteller'),
							'desc'  =>  __( 'Username will become an optional field. If left empty, an auto-generated username will be used.', 'texteller') . '<br>'
							            . sprintf( /* translators: %s: title of a setting tab */ 'Username auto-generation settings can be configured on the %s tab.', sprintf(
										'<a href="' . admin_url('admin.php?page=tlr-options&tab=tlr_wordpress&section=wp_registration_behaviour')
										. '">%s</a>', __( 'Registration & Login Behaviour', 'texteller') )
							            )
						]
					]
				]
			],
			[
				'id'            =>  'tlr_wp_registration_form_description',
				'title'         =>  __('Form Description', 'texteller'),
				'page'         =>      'tlr_wordpress',
				'section'       =>  'wp_registration_fields',
				'field_args'    =>  ['default'=>''],
				'desc'          =>   __('This text will be displayed at the end of the form, below the form fields.', 'texteller'),
				'helper'        =>  __( 'Leave it empty to remove the description.', 'texteller' ),
				'type'          =>  'textarea',
				'params'        =>  [
					'size'    =>    [
						'cols'  =>  30,
						'rows'  => 5
					]
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
				'required'  =>  0
			],
			'last_name' =>  [
				'enabled'   =>  'yes',
				'id'        =>  'last_name',
				'title'     =>  __('Last Name', 'texteller'),
				'required'  =>  0
			],
			'mobile'    =>  [
				'enabled'   =>  'yes',
				'id'        =>  'mobile',
				'title'     =>  __('Mobile Number', 'texteller'),
				'required'  =>  1
			],
			'email'    =>  [
				'enabled'   =>  'yes',
				'id'        =>  'email',
				'title'     =>  __('Email', 'texteller'),
				'required'  =>  1
			],
			'title'    =>  [
				'enabled'   =>  '',
				'id'        =>  'title',
				'title'     =>  __('Title', 'texteller'),
				'required'  =>  0
			],
			'member_group'   =>  [
				'enabled'   =>  '',
				'id'        =>  'member_group',
				'title'     =>  __('Member Groups', 'texteller'),
				'required'  =>  0
			]
		];
	}

	/**
	 * Registers options in the registration behaviour section
	 */
	private function register_registration_behaviour_options()
	{
		$fields = [
			[
				'id'            =>  'tlr_wp_registration_username_generator',
				'page'          =>  'tlr_wordpress',
				'section'       =>  'wp_registration_behaviour',
				'title'         =>  __('Frontend Username Generation Method', 'texteller'),
				'field_args'    =>  ['default'=>'int_mobile'],
				'desc'          =>  sprintf( /* translators: %s: title of a setting tab */
					__('In order to choose one of these options as the means of front end username generation, Username field should be hidden or set as optional on the registration form. This setting can be modified on the %s tab.', 'texteller'),
					sprintf('<a href="'. admin_url('admin.php?page=tlr-options&tab=tlr_wordpress&section=wp_registration_fields') .'">%s</a>', __('Registration From Fields', 'texteller') )
				),
				'extra_options'  =>  [
					[
						'id'        =>  'tlr_wp_registration_username_generator_national_mobile',
						'page'      =>  'tlr_wordpress',
						'section'   =>  'wp_registration_behaviour',
						'title'     =>  'National mobile username generator pattern',
						'type'      =>  'hidden'
					]
				],
				'type'          =>  'radio',
				'params'        =>  [
					'pre_logic' =>  'OR',
					'pre'      =>  [
						'tlr_wp_registration_frontend_username'  => [ 'hide', 'optional' ],
					],
					'values'    =>  [
						'texteller'      =>  [
							'label' =>  __('Name, surname, email', 'texteller'),
							'desc'  =>  sprintf ( /* translators: %s: title of a setting tab */
								__('To enable this option, Email field should be enabled in %s tab.', 'texteller'),
									'"' . sprintf('<a href="'
									              . admin_url('admin.php?page=tlr-options&tab=tlr_wordpress&section=wp_registration_fields')
									              . '">%s</a>'
										, __('Registration Form Fields', 'texteller') ) . '"'
							            ) . '<br>' . __( 'If Email field is set as optional and user leaves it empty during the registration, the Random Numbers method will be used as the fallback.', 'texteller'),
							'pre'  =>  [
								'tlr_wp_registration_form_fields'    => [
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
									'"' . sprintf('<a href="'
									              . admin_url('admin.php?page=tlr-options&tab=tlr_wordpress&section=wp_registration_fields')
									              . '">%s</a>'
										, __('Registration Form Fields', 'texteller') ) . '"'
							            ) . '<br>' . __( 'If Mobile field is set as optional and user leaves it empty during the registration, the Random Numbers method will be used as the fallback.', 'texteller' ),
							'pre'  =>  [
								'tlr_wp_registration_form_fields'    => [
									'mobile' => [
										'enabled'   =>  'yes'
									]
								]
							]
						],
						'national_mobile'      =>  [
							'label' =>  __('National mobile number', 'texteller'),
							'select' => [
								'id'        =>  'tlr_wp_registration_username_generator_national_mobile',
								'title'     =>  _x( 'Format', 'national number format', 'texteller' ),
								'options'   =>  [
									'leading-zero' => __( 'Prefixed with the leading zero', 'texteller' ),
									'no-leading-zero' => __( 'Without the leading zero', 'texteller' )
								]
							],
							'desc'  =>  sprintf ( /* translators: %s: title of a setting tab */
								__('To enable this option, Mobile field should be enabled on %s tab.', 'texteller'),
									'"' . sprintf('<a href="'
									              . admin_url('admin.php?page=tlr-options&tab=tlr_wordpress&section=wp_registration_fields')
									              . '">%s</a>'
										, __('Registration Form Fields', 'texteller') ) . '"'
							            ) . '<br>' . __( 'If Mobile field is set as optional and user leaves it empty during the registration, the Random Numbers method will be used as the fallback.', 'texteller'),
							'pre'  =>  [
								'tlr_wp_registration_form_fields'    => [
									'mobile' => [
										'enabled'   =>  'yes'
									]
								]
							]
						],
						'rand_numbers' => [
							'label' => __('Random numbers', 'texteller'),
							'desc'  =>  __( 'Using this option, the generated username will be in this format:', 'texteller' ) .
							            ' <code>user_xxxxxx</code>'
						]
					]
				]
			],
			[
				'id'            =>  'tlr_wp_registration_frontend_update_names',
				'title'         =>  __('Names Update', 'texteller'),
				'page'          =>  'tlr_wordpress',
				'section'       =>  'wp_registration_behaviour',
				'field_args'    =>  ['default'=>'yes'],
				'helper'        =>  __( 'In order for this option to work, fields for first name or last name should be enabled in Registration Form Fields tab.', 'texteller'),
				'desc'          =>  __( 'This will effect new users who register via WordPress frontend user registration form (wp-login.php).', 'texteller' ),
				'type'          =>  'input',
				'params'        =>  [
					'type'  =>  'checkbox',
					'label' =>  __('Update first and last name on Wordpress user profile for new users', 'texteller')
				]
			],
			[
				'id'            =>  'tlr_wp_profile_mobile_update',
				'title'         =>  __('Profile Mobile Number Update', 'texteller'),
				'page'          =>  'tlr_wordpress',
				'section'       =>  'wp_registration_behaviour',
				'field_args'    =>  [ 'default' => 'yes'],
				'desc'          =>  __('Users who are already linked to a member, will be able to edit their mobile number from WordPress Profile page.', 'texteller'),
				'type'          =>  'input',
				'params'        =>  [
					'type'  =>  'checkbox',
					'label' =>  __( 'Allow users to update their mobile number via Profile page', 'texteller' )
				]
			],
			[
				'id'            =>  'tlr_wp_profile_verify_number',
				'title'         =>  __('Mobile Number Verification on Profile', 'texteller'),
				'page'          =>  'tlr_wordpress',
				'section'       =>  'wp_registration_behaviour',
				'field_args'    =>  [ 'default' => 'yes'],
				'desc'          =>  __( 'Users who are already linked to a member, will be able to verify their mobile number from Wordpress Profile page.', 'texteller' ),
				'type'          =>  'input',
				'params'        =>  [
					'type'  =>  'checkbox',
					'label' =>  __( 'Allow users to verify their mobile number on Profile page', 'texteller' )
				]
			],
			[
				'id'            =>  'tlr_wp_registration_new_user_rp_message',
				'page'          =>  'tlr_wordpress',
				'section'       =>  'wp_registration_behaviour',
				'title'         =>  __( "Base Gateway for New Users' Set-Password Message", 'texteller'),
				'field_args'    =>  ['default'=>'both'],
				'desc'          =>  __('This will effect new users who register via Wordpress frontend user registration form (wp-login.php).', 'texteller')
				                    . ' ' . __('By default, WordPress sends an email notification to the new registered users, which contains their username and a reset-password link for them to set a password.', 'texteller'),
				'helper'        =>  __( 'In case any option but Email (WordPress default) is chosen, the notification message should be customized from Registration and Login Notifications tab.', 'texteller' ),
				'type'          =>  'radio',
				'params'        =>  [
					'values'    =>  [
						'both'      =>  [
							'label' =>  __('Email and Texteller message', 'texteller'),
							'desc'  =>  __('Texteller will try both email and mobile number to reach the user.', 'texteller')
						],
						'email'     =>  [
							'label' =>  __('Email (WordPress default)', 'texteller'),
							'desc'  =>  sprintf ( /* translators: %s: title of a setting tab */
								__('To enable this option, email field should be enabled in %s tab.', 'texteller'),
								'"' . sprintf('<a href="'
								              . admin_url('admin.php?page=tlr-options&tab=tlr_wordpress&section=wp_registration_fields')
								              . '">%s</a>'
									, __('Registration Form Fields', 'texteller') ) . '"'
							),
							'pre'   =>  [
								'tlr_wp_registration_form_fields'   =>  [
									'email' => [
										'enabled'   =>  'yes'
									]
								]
							]
						],
						'texteller' =>  [
							'label' =>  __('Texteller message', 'texteller'),
							'desc'  =>  sprintf ( /* translators: %s: title of a setting tab */
								__('To enable this option, mobile number should be enabled in %s tab.', 'texteller'),
								'"' . sprintf('<a href="'
								              . admin_url('admin.php?page=tlr-options&tab=tlr_wordpress&section=wp_registration_fields')
								              . '">%s</a>'
									, __('Registration Form Fields', 'texteller') ) . '"'
							),
							'pre'   =>  [
								'tlr_wp_registration_form_fields'    => [
									'mobile' => [
										'enabled'   =>  'yes'
									]
								]
							]
						]
					]
				]
			],
			[
				'id'            =>  'tlr_wp_lost_password_base_gateway',
				'page'          =>  'tlr_wordpress',
				'section'       =>  'wp_registration_behaviour',
				'title'         =>  __('Base Gateway for Forget Password Message', 'texteller'),
				'field_args'    =>  ['default' => 'both'],
				'desc'          =>  __('By default, WordPress sends a Forget Password email which includes a reset-password link to set a new password.', 'texteller'),
				'helper'        =>  __( 'In case any option but Email (WordPress default) is chosen, the notification message should be customized from Registration and Login Notifications tab..', 'texteller' ),
				'type'          =>  'radio',
				'params'        =>  [
					'values'    =>  [
						'both'      =>  [
							'label' =>  __('Email and Texteller message', 'texteller'),
							'desc'  =>  __( 'Texteller will try both email and mobile number to reach the user.', 'texteller' )
						],
						'wp_default'      =>  [
							'label' =>  __('Email (WordPress default)', 'texteller'),
							'pre'   =>  [
								'tlr_wp_registration_form_fields'    => [
									'email' => [
										'enabled'   =>  'yes'
									]
								]
							],
							'desc'  =>  sprintf ( /* translators: %s: title of a setting tab */
								__('To enable this option, email field should be enabled in %s tab.', 'texteller'),
								'"' . sprintf('<a href="'
								              . admin_url('admin.php?page=tlr-options&tab=tlr_wordpress&section=wp_registration_fields')
								              . '">%s</a>'
									, __('Registration Form Fields', 'texteller') ) . '"'
							)
						],
						'texteller'      =>  [
							'label' =>  __('Texteller message', 'texteller'),
							'desc'  =>  sprintf ( /* translators: %s: title of a setting tab */
								__('To enable this option, mobile number should be enabled in %s tab.', 'texteller'),
								'"' . sprintf('<a href="'
								              . admin_url('admin.php?page=tlr-options&tab=tlr_wordpress&section=wp_registration_fields')
								              . '">%s</a>'
									, __('Registration Form Fields', 'texteller') ) . '"'
							),
							'pre'   =>  [
								'tlr_wp_registration_form_fields'    => [
									'mobile' => [
										'enabled'   =>  'yes'
									]
								]
							],
						],
						'user_choice'      =>  [
							'label' =>  __( "User's choice", 'texteller' ),
							'desc'  =>  __( 'Users will be asked to choose their preferred way of receiving the message.', 'texteller')
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
				'id'            =>  'tlr_trigger_wp_registration_member_registered',
				'title'         =>  __( 'Registration of New User', 'texteller' ),
				'page'          =>  'tlr_wordpress',
				'section'       =>  'wp_registration_notifications',
				'field_args'    =>  [ 'default' => [] ],
				'type'          =>  'notification',
				'params'        =>  [
					'label'                 =>  __( 'Send notifications when a new user is registered', 'texteller' ),
					'tag_type'              =>  'user',
					'trigger_recipients'    =>  [
						'registered_user'           => __( 'Registered User (wp-login.php)', 'texteller' ),
						'dashboard_registered_user' => __( 'Registered User (Admin Dashboard)', 'texteller' ),
					]
				]
			],
			[
				'id'            =>  'tlr_trigger_wp_registration_rp_link',
				'title'         =>  __( 'Set-Password for New Users', 'texteller' ),
				'page'          =>  'tlr_wordpress',
				'section'       =>  'wp_registration_notifications',
				'field_args'    =>  [ 'default' => [] ],
				'helper'        =>  sprintf( /* translators: %s: Registration & Login Behaviour */
					__('In order for this notification message to be sent, the chosen option in the "Base Gateway for New Users\' Set-Password Message" section from %s tab, must include "Texteller message".', 'texteller'),
					sprintf(
						'<a href="' . admin_url('admin.php?page=tlr-options&tab=tlr_wordpress&section=wp_registration_behaviour') . '">%s</a>',
						__('Registration & Login Behaviour', 'texteller')
					)
				),
				'type'          =>  'notification',
				'params'        =>  [
					'label'                 =>  __( 'Send set-password link on new user registration', 'texteller' ),
					'tag_type'              =>  'draft_member',
					'recipient_types'       =>  ['trigger'],
					'trigger_recipients'    =>  [
						'registered_draft_user'           => __( 'Registered User (wp-login.php)', 'texteller' ),
						'dashboard_registered_draft_user' => __( 'Registered User (Admin Dashboard)', 'texteller' ),
					]
				]
			],
			[
				'id'            =>  'tlr_trigger_wp_lost_pw_rp_link',
				'title'         =>  __( 'Retrieve-Password For Forgetful Users', 'texteller' ),
				'page'          =>  'tlr_wordpress',
				'section'       =>  'wp_registration_notifications',
				'field_args'    =>  [ 'default' => [] ],
				'desc'          =>  __( 'In order for this notification message to be sent, the chosen option in the "Base Gateway for Forget Password Message" section from "Registration & Log Behaviour" tab must include "Texteller message".', 'texteller'),
				'type'          =>  'notification',
				'params'        =>  [
					'label'                 =>  __( 'Send retrieve-password message when user asks for forget-password link', 'texteller' ),
					'tag_type'              =>  'lost_user',
					'recipient_types'       =>  ['trigger'],
					'trigger_recipients'    =>  [ 'lost_user' => __( 'Forgetful User', 'texteller' ) ]
				]
			]
		];

		self::register_options( $fields );
	}

	/**
	 * Registers options in the post notifications section
	 */
	private function register_post_notifications_options()
	{
		$fields = [
			[
				'id'            =>  'tlr_trigger_wp_posts_new_post_published',
				'title'         =>  __( 'New Post Published', 'texteller' ),
				'page'          =>  'tlr_wordpress',
				'section'       =>  'wp_post_notifications',
				'field_args'    =>  [ 'default' => [] ],
				'type'          =>  'notification',
				'params'        =>  [
					'label'                 =>  __( 'Send notifications when a new blog post published', 'texteller' ),
					'tag_type'              =>  'post',
					'trigger_recipients'    =>  [ 'post_author' => __( 'Post Author', 'texteller' )]
				]
			],
			[
				'id'            =>  'tlr_trigger_wp_posts_comment_posted',
				'title'         =>  __( 'New Comment Added', 'texteller' ),
				'page'          =>  'tlr_wordpress',
				'section'       =>  'wp_post_notifications',
				'field_args'    =>  [ 'default' => [] ],
				'type'          =>  'notification',
				'params'        =>  [
					'label'                 =>  __( 'Send notifications when a new comment is added to a blog post', 'texteller' ),
					'tag_type'              =>  'comment',
					'trigger_recipients'    =>  [
						'comment_author'            =>  __( 'Comment Author', 'texteller' ),
						'parent_comment_author'     =>  __( 'Parent Comment Author', 'texteller' ),
						'post_author'               =>  __( 'Post Author', 'texteller' ),
					]
				]
			],
			[
				'id'            =>  'tlr_trigger_wp_posts_comment_approved',
				'title'         =>  __( 'Comment Approved', 'texteller' ),
				'page'          =>  'tlr_wordpress',
				'section'       =>  'wp_post_notifications',
				'field_args'    =>  [ 'default' => [] ],
				'type'          =>  'notification',
				'params'        =>  [
					'label'                 =>  __( 'Send notifications when a comment is approved', 'texteller' ),
					'tag_type'              =>  'comment',
					'trigger_recipients'    =>  [
						'comment_author'            =>  __( 'Comment Author', 'texteller' ),
						'parent_comment_author'     =>  __( 'Parent Comment Author', 'texteller' ),
						'post_author'               =>  __( 'Post Author', 'texteller' ),
					]
				]
			]
		];

		self::register_options( $fields );
	}

	/**
	 * @see \Texteller\Interfaces\Options::get_option_tags()
	 *
	 * @return TLR\Tags
	 */
	public static function get_option_tags() : TLR\Tags
	{
		$tags = self::get_the_tags();

		$user_tags = [
			'login_link'    =>  __( 'WP Login Link', 'texteller' )
		];
		$lost_user_tags = [
			'rp_link'     =>  __('WP reset password link', 'texteller'),
		];
		$comment_tags = [
			'comment_author'        =>  __('comment author name', 'texteller'),
			'comment_author_email'  =>  __('comment author email', 'texteller'),
			'comment_author_url'    =>  __('comment author URL', 'texteller'),
			'comment_content'       =>  __('comment content', 'texteller'),
			'comment_status'        =>  __('comment status', 'texteller'),
			'comment_parent'        =>  __('parent comment content', 'texteller'),
			'comment_parent_author' =>  __('parent comment author', 'texteller'),
			'comment_author_ip'     =>  __('comment author IP', 'texteller'),
			'comment_agent'         =>  __('comment user agent', 'texteller'),
			'comment_date'          =>  __('comment date', 'texteller')
		];
		$post_tags = [
			'post_id'           =>  __( 'post ID', 'texteller' ),
			'post_title'        =>  __( 'post title', 'texteller' ),
			'post_author'       =>  __( 'post author name', 'texteller' ),
			'post_slug'         =>  __( 'post slug', 'texteller' ),
			'post_url'          =>  __( 'post url', 'texteller' ),
			'post_date'         =>  __( 'post date', 'texteller' ),
			'post_excerpt'      =>  __( 'post excerpt', 'texteller' ),
			'post_status'       =>  __( 'post status', 'texteller' ),
			'comments_status'   =>  __( 'comment status', 'texteller' ),
			'comments_count'    =>  __( 'comments count', 'texteller' ),
			'post_cats'         =>  __( 'post categories', 'texteller' ),
			'post_tags'         =>  __( 'post tags', 'texteller' )
		];

		$member_tags = self::get_base_tags_array('member' );

		$draft_member_tags = $member_tags;
		unset( $draft_member_tags['member_id'] ); // When RP notification sends, member isn't saved in the db yet, so there is no member_id available.
		$draft_member_tags['rp_link'] = __( 'RetrievePass Link', 'texteller' );

		$tags->add_tag_type_data( 'user', array_merge( $member_tags, $user_tags ) );
		$tags->add_tag_type_data( 'lost_user', array_merge( $member_tags, $user_tags, $lost_user_tags ) );
		$tags->add_tag_type_data( 'draft_member', array_merge( $draft_member_tags, $member_tags, $user_tags ) );
		$tags->add_tag_type_data( 'comment', array_merge( $comment_tags, $post_tags ) );
		$tags->add_tag_type_data( 'post', $post_tags );

		return $tags;
	}
}