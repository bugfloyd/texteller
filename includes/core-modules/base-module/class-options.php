<?php

namespace Texteller\Core_Modules\Base_Module;
use Texteller as TLR;

defined( 'ABSPATH' ) || exit;

/**
 * Class Base_Options Texteller core options
 * @package Texteller
 */
final class Options implements TLR\Interfaces\Options
{
	use TLR\Traits\Options_Base;
	use TLR\Traits\Encrypted_Options;

	/**
	 * Base_Options constructor.
	 * Adds option hooks
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
		add_filter( 'texteller_option_pages', [self::class, 'add_option_pages'], 10 );
		add_action( 'admin_init', [$this, 'register_module_options'], 10);
		add_filter( 'pre_update_option_tlr_slink_bitly_token', [self::class, 'update_encrypted_option'], 10, 2 );
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
		$base_tabs = [
			[
				'slug'  =>  'tlr_texteller',
				'title' =>  __('General', 'texteller')
			],
			[
				'slug'  =>  'tlr_gateways',
				'title' =>  __('Gateways', 'texteller'),
			]
		];
		return array_merge( $base_tabs, $tabs );
	}

	/**
	 * @see \Texteller\Interfaces\Options::get_option_tags()
	 *
	 * @return TLR\Tags
	 */
	public static function get_option_tags() : TLR\Tags
	{
		$tags = self::get_the_tags();
		$member_tags = self::get_base_tags_array('member');
		$unverified_member_tags = [
			'code'  =>  __( 'verification code', 'texteller' )
		];
		$tags->add_tag_type_data( 'unverified_member', array_merge( $member_tags, $unverified_member_tags ) );

		return $tags;
	}

	/**
	 * @see \Texteller\Interfaces\Options::register_module_options()
	 */
	public function register_module_options()
	{
		self::register_sections();

		$active_gateways = (array) get_option( 'tlr_active_gateways', [] );
		foreach ( $active_gateways as $active_gateway ) {
			$gateway_class = TLR\tlr_get_gateway_class($active_gateway);
			if( $gateway_class ) {
				$gateway = new $gateway_class();    /** @var TLR\Interfaces\Gateway $gateway */
				$gateway->register_gateway_options();
			}
		}

		$this->register_general_options();
		$this->register_common_notifications_options();
		$this->register_active_gateways_options();
	}

	/**
	 * @see \Texteller\Interfaces\Options::register_sections()
	 */
	public static function register_sections()
	{
		self::register_section( [
			'id'    =>  'tlr_basics',
			'title' =>  __( 'Basics', 'texteller' ),
			'desc'  =>  __( 'Configure the basic options', 'texteller' ),
			'class' =>  'description',
			'page'  =>  'tlr_texteller'
		] );
		self::register_section( [
			'id'    =>  'tlr_general_notifications',
			'title' =>  __( 'General Notifications', 'texteller'),
			'desc'  =>  __( 'Set cross-module, site wide notifications', 'texteller' ),
			'class' =>  'description',
			'page'  =>  'tlr_texteller'
		] );
		self::register_section( [
			'id'    =>  'tlr_advanced',
			'title' =>  __( 'Advanced', 'texteller'),
			'desc'  =>  __( 'Modify advanced options', 'texteller' ),
			'class' =>  'description',
			'page'  =>  'tlr_texteller'
		] );
		self::register_section( [
			'id'    =>  'tlr_active_gateways',
			'title' =>  __( 'Active Gateways', 'texteller' ),
			'desc'  =>  __( 'Manage third party gateways to send and receive messages', 'texteller' ),
			'class' =>  'description',
			'page'  =>  'tlr_gateways'
		] );
	}

	/**
	 * Registers options in the general section
	 */
	private function register_general_options()
	{
		$general_fields = [
			[
				'id'        =>  'tlr_staff',
				'title'     =>  __( 'Site Staff', 'texteller' ),
				'page'      =>  'tlr_texteller',
				'section'   =>  'tlr_basics',
				'type'      =>  'staff_selector',
				'desc'      =>  __( 'Selected members will be marked as site staff.', 'texteller' ),
				'helper'    =>  __( "Staff should be registered as members beforehand. Adding members to staff list per se won't grant them any privileges. However, they can be selected from the notification trigger related options under the Site Staff recipient tab." , 'texteller' ),
				'params'    =>  [
					'label' =>  __( 'Select staff', 'texteller' )
				]
			],
			[
				'id'            =>  'tlr_message_signature',
				'title'         =>  __( 'Message Signature', 'texteller' ),
				'page'          =>  'tlr_texteller',
				'section'       =>  'tlr_basics',
				'desc'          =>  __( 'This text would be appended to notification messages.', 'texteller' ),
				'helper'        =>  __( "Note that this signature will be ignored in two cases: admin manual messages and notifications sent by gateways which require predefined text templates." , 'texteller' ),
				'type'          =>  'textarea',
				'params'        =>  [
					'attribs'   =>  [
						'class' =>  'tlr-count',
						'cols'  =>  30,
						'rows'  => 5
					]
				]
			],
			[
				'id'            =>  'tlr_delete_member_with_user',
				'title'         =>  __( 'Automatic Member Deletion', 'texteller' ),
				'page'          =>  'tlr_texteller',
				'section'       =>  'tlr_basics',
				'desc'          =>  __( 'Linked member will also be permanently deleted on user removal.', 'texteller' ),
				'helper'        =>  __( 'If left disabled, at the time of user removal the linked member will be remained but becomes unlinked from the deleted user.', 'texteller' ),
				'type'          =>  'input',
				'params'        =>  [
					'type'    =>    'checkbox',
					'label' =>  __( 'Delete member after user removal', 'texteller' )
				]
			],
			[
				'id'            =>  'tlr_code_lifetime',
				'title'         =>  __( 'Codes Lifetime', 'texteller' ),
				'page'          =>  'tlr_texteller',
				'section'       =>  'tlr_basics',
				'field_args'    =>  [ 'default' => 5 ],
				'helper'        =>  __( 'The minimum acceptable value is 1 minute.', 'texteller' ),
				'desc'          =>  __( 'Determine the lifespan for verification codes generated by plugin.', 'texteller' ),
				'type'          =>  'input',
				'params'        =>  [
					'type'      =>  'number',
					'attribs'   =>  [ 'class' => 'tlr-small-field' ],
					'label'     =>  __( 'minute(s)', 'texteller' )
				]
			],
			[
				'id'            =>  'tlr_frontend_default_countries',
				'title'         =>  __('Default Country List', 'texteller'),
				'page'          =>  'tlr_texteller',
				'section'       =>  'tlr_basics',
				'field_args'    =>  [ 'default' => [ 'US' ] ],
				'helper'        =>  __("Whenever a customer doesn't include a country code in their entered mobile number for the process of login or password retrieval, the full number and the related user will be fetched using the associated country codes on this list.", 'texteller'),
				'desc'          =>  sprintf( /* translators: %s: Name of a country code standard */
					__( 'Mobile numbers without a country code will be checked with the %s codes added above, in the same order as entered.', 'texteller'),
					'<a href="https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2" target="_blank">ISO 3166-1 alpha-2</a>'
				),
				'class'         =>  'country-codes',
				'type'          =>  'variable_list'
			],
			[
				'id'            =>  'tlr_intl_tel_input_options',
				'title'         =>  __('Mobile Number Input', 'texteller'),
				'page'          =>  'tlr_texteller',
				'section'       =>  'tlr_basics',
				'field_args'    =>  [ 'default' =>  [
					'country_dropdown'      =>  'yes',
					'preferred_countries'   =>  ['US', 'IN', 'GB'],
					'initial_country'       =>  'US'
				]
				],
				'helper'        =>  __( 'By selecting a default country and disabling the country dropdown option, the member registration will be limited to the default country in all frontend member registration modules.', 'texteller' ),
				'desc'          =>  __( 'These options will be applied to the mobile number field on registration forms in all modules.', 'texteller' ),
				'type'          =>  'tel_input_options'
			],
			[
				'id'            =>  'tlr_manual_send_member_db_save',
				'page'          =>  'tlr_texteller',
				'section'       =>  'tlr_basics',
				'title'         =>  __('Save Member Manual Messages', 'texteller'),
				'field_args'    =>  [ 'default' => 'yes' ],
				'desc'          =>  __( 'Sent messages can be reviewed from Messages.', 'texteller' ),
				'type'          =>  'input',
				'params'        =>  [
					'type'  =>  'checkbox',
					'label' =>  __( 'Save manual messages sent via member edit screen', 'texteller' ),
				]
			],
			[
				'id'            =>  'tlr_manual_send_db_save',
				'page'          =>  'tlr_texteller',
				'section'       =>  'tlr_basics',
				'title'         =>  __('Save Manual Messages', 'texteller'),
				'field_args'    =>  [ 'default' => 'yes' ],
				'desc'          =>  __('Sent messages can be reviewed from Messages.', 'texteller'),
				'type'          =>  'input',
				'params'        =>  [
					'type'  =>  'checkbox',
					'label' =>  __( 'Save manual messages sent via Send Message screen', 'texteller' )
				]
			],
			[
				'id'            =>  'tlr_message_numbers_lang',
				'title'         =>  __( 'Convert Numbers', 'texteller' ),
				'page'          =>  'tlr_texteller',
				'section'       =>  'tlr_advanced',
				'field_args'    =>  [ 'default' => 'none' ],
				'desc'          =>  __( 'Digits in the messages will be converted to the selected language.', 'texteller' ),
				'helper'        =>  __( 'This does not include the digits and numbers in the links.', 'texteller' ),
				'type'          =>  'select',
				'params'        =>  [
					'options'   =>  [
						'none'              =>  __( 'Do not convert', 'texteller' ),
						'european'          =>  __( 'European', 'texteller' ),
						'devanagari'        =>  __( 'Devanagari Hindi', 'texteller' ),
						'arabic'            =>  __( 'Arabic-Indic', 'texteller' ),
						'persian'           =>  __( 'Persian/Urdu', 'texteller' ),
						'bengali'           =>  __( 'Bengali', 'texteller' ),
						'thai'              =>  __( 'Thai', 'texteller' ),
						'chinese-simple'    =>  __( 'Chinese(simple)', 'texteller' ),
						'chinese-complex'   =>  __( 'Chinese(complex)', 'texteller' )
					],
					'attribs'    =>    [
						'style'     => 'width:200px;height:34px;'
					]
				]
			],
			[
				'id'            =>  'tlr_calendar_type',
				'title'         =>  __( 'Calendar Type', 'texteller' ),
				'page'          =>  'tlr_texteller',
				'section'       =>  'tlr_advanced',
				'field_args'    =>  [ 'default' => 'gregorian' ],
				'desc'          =>  __( 'The calendar type that will be used in plugin admin pages e.g. Messages, Members, etc.', 'texteller' ),
				'helper'        =>  __( 'In order for this option to work, the internationalization (intl) extension of PHP should be installed and activated on the server.', 'texteller' ),
				'type'          =>  'select',
				'params'        =>  [
					'options'   =>  [
						'gregorian'             =>  _x( 'Gregorian (Default)', 'calendar type', 'texteller' ),
						'japanese'              =>  _x( 'Japanese', 'calendar type', 'texteller' ),
						'buddhist'              =>  _x( 'Buddhist', 'calendar type', 'texteller' ),
						'roc'                   =>  _x( 'Republic of China', 'calendar type', 'texteller' ),
						'persian'               =>  _x( 'Persian', 'calendar type', 'texteller' ),
						'islamic-civil'         =>  _x( 'Islamic Civil', 'calendar type', 'texteller' ),
						'islamic'               =>  _x( 'Islamic', 'calendar type', 'texteller' ),
						'hebrew'                =>  _x( 'Hebrew', 'calendar type', 'texteller' ),
						'chinese'               =>  _x( 'Chinese', 'calendar type', 'texteller' ),
						'indian'                =>  _x( 'Indian', 'calendar type', 'texteller' ),
						'coptic'                =>  _x( 'Coptic or Alexandrian', 'calendar type','texteller'),
						'ethiopic'              =>  _x( 'Ethiopian', 'calendar type', 'texteller' ),
						'ethiopic-amete-alem'   =>  _x( 'Ethiopian (Amete Alem)', 'calendar type', 'texteller')
					],
					'attribs'    =>    [
						'style'     => 'width:200px;height:34px;'
					]
				]
			],
			[
				'id'            =>  'tlr_shorten_links',
				'title'         =>  __('Short Links', 'texteller'),
				'page'          =>  'tlr_texteller',
				'section'       =>  'tlr_advanced',
				'helper'        =>  __( 'This option would be ignored if no valid access token is given. After registering a free account on bitly.com, the access token should be entered in the option below.', 'texteller'),
				'type'          =>  'input',
				'params'        =>  [
					'type'  =>  'checkbox',
					'label' =>  __( 'Replace all the links in the messages with short links', 'texteller' )
				]
			],
			[
				'id'            =>  'tlr_slink_bitly_token',
				'title'         =>  __( 'Bitly Access Token'),
				'page'          =>  'tlr_texteller',
				'section'       =>  'tlr_advanced',
				'helper'        =>  sprintf( /* translators: %s: Bitly.com */
					__( 'Access tokens can be acquired from the profile menu on %s, after account registration.', 'texteller'),
					'<a href="https://bitly.com" target="_blank">Bitly.com</a>'
				),
				'desc'          =>  __( 'A valid bitly.com Generic Access Token should be entered here.', 'texteller' ),
				'type'          =>  'input',
				'params'        =>  [
					'type'  =>  'password'
				]
			]
		];
		self::register_options( $general_fields );
	}

	/**
	 * Registers options in the common notifications section
	 */
	private function register_common_notifications_options()
	{
		$fields = [
			[
				'id'            =>  'tlr_trigger_tlr_number_verification',
				'title'         =>  __('Verification Message', 'texteller'),
				'page'          =>  'tlr_texteller',
				'section'       =>  'tlr_general_notifications',
				'desc'          =>  __( "This message will be sent on each verification, and it's applied to any module that is set to verify the numbers.", 'texteller' ) . '<br>'
				                    . __( 'Make sure to include the code tag in the message content.', 'texteller' ),
				'field_args'    =>  [ 'default' => [] ],
				'type'          =>  'notification',
				'params'        =>  [
					'tag_type'              =>  'unverified_member',
					'recipient_types'       =>  ['trigger'],
					'trigger_recipients'    =>  ['member' => __('Member', 'texteller' )],
					'label'                 =>  __( 'Verification message', 'texteller' )
				]
			]
		];
		self::register_options( $fields );
	}

	/**
	 * Registers options in the active gateways section
	 */
	private function register_active_gateways_options()
	{
	    $available_gateways = [
		    'bulksms'       =>  'BulkSMS',
		    'gatewayapi'    =>  'GatewayAPI',
		    'melipayamak'   =>  'Melipayamak',
		    'sabanovin'     =>  'SabaNovin',
		    'spryng'        =>  'Spryng',
		    'textlocal'     =>  'Textlocal',
		    'twilio'        =>  'Twilio'
	    ];

        /**
         * Filter available gateways list
         *
         * @param array $available_gateways
         * @since 0.1.3
         */
        $available_gateways = apply_filters( 'tlr_available_gateways', $available_gateways );

		$gateway_fields = [
			[
				'id'            =>  'tlr_active_gateways',
				'title'         =>  __( 'Active Gateways', 'texteller' ),
				'page'          =>  'tlr_gateways',
				'section'       =>  'tlr_active_gateways',
				'field_args'    =>  [ 'default' => [] ],
				'desc'          =>  __( 'Active gateways can be selected from Available Gateways table.', 'texteller' )
				                    . '<br>' . __( 'By adding any gateway to the active table, a new tab will appear with the same name where related options can be configured.', 'texteller' ),
				'type'          =>  'gateway_selector',
				'params'        =>  [
					'gateways'  =>  $available_gateways
				]
			]
		];
		self::register_options( $gateway_fields );
	}
}