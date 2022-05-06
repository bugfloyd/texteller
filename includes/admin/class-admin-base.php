<?php

namespace Texteller\Admin;
use Texteller as TLR;

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin
 *
 * Main Texteller admin class to create admin pages and forms
 *
 * @class		TLR_Admin
 * @version		1.0.0
 * @author		Yashar Hosseinpour
 */
class Admin_Base {

	/**
	* Settings_Tabs.
	* @since 3.0
	* @var $settings_tabs array Settings Tabs.
	*/
	public $settings_tabs = [];

	protected $content_html = '';

	public function __construct() {
		$this->init();
	}

	public function init ()
	{
		add_action( 'admin_menu', [self::class, 'admin_menu'] );
		add_action( 'admin_enqueue_scripts', [self::class, 'enqueue_admin_assets'] );
		add_action( 'admin_bar_menu', [ self::class, 'toolbar_callback' ], 15 );
		add_action( 'delete_user', [self::class, 'maybe_modify_member'] );
		add_filter( 'tlr_registration_origins', [ self::class, 'add_core_registration_origins' ], 1, 1 );
		add_filter( 'set-screen-option', [self::class, 'save_objects_per_page'], 99, 3 );

		Ajax::init();
	}

	public static function add_core_registration_origins()
	{
		return [
			'tlr-newsletter'            =>  'Newsletter form',
			'wp-dashboard-edit-user'    =>  'WP-Dashboard: Edit user',
			'wp-profile'                =>  'User profile',
			'wp-login'                  =>  'wp-login',
			'wp-dashboard-new-user'     =>  'WP-Dashboard: Add new user',
			'tlr-dashboard'             =>  'Texteller Dashboard'
		];
	}

	/**
	 * Adds admin bar items
	 */
	public static function toolbar_callback()
	{
		/** @var $wp_admin_bar \WP_Admin_bar */
		global  $wp_admin_bar;

		if (!is_super_admin()
		    || !is_object( $wp_admin_bar)
		    || !function_exists( 'is_admin_bar_showing' )
		    || !is_admin_bar_showing()) {
			return;
		}

		$wp_admin_bar->add_node([
			'id'        =>  'texteller',
			'title'     =>  '<img style="padding-top: 8px" src="' . TLR_ASSETS_URI . '/images/logo.png"/>',
			'href'      =>  admin_url('admin.php?page=texteller')
		]);
		$wp_admin_bar->add_node([
			'id'        =>  'tlr_send',
			'title'     =>  __( 'Send Message', 'texteller' ),
			'href'      =>  admin_url('admin.php?page=texteller'),
			'parent'    =>  'texteller'
		]);
		$wp_admin_bar->add_node([
			'id'        =>  'tlr-messages',
			'title'     =>  __( 'Messages', 'texteller' ),
			'href'      =>  admin_url('admin.php?page=tlr-messages'),
			'parent'    =>  'texteller'
		]);
		$wp_admin_bar->add_node([
			'id'        =>  'tlr-members',
			'title'     =>  __( 'Members', 'texteller' ),
			'href'      =>   admin_url('admin.php?page=tlr-members'),
			'parent'    =>  'texteller'
		]);
	}

	public static function enqueue_admin_assets( $hook_suffix )
	{
		/**
		 * Register common admin assets
		 */

		// SMS Count
		wp_register_script(
			'tlr-sms-count',
			TLR_ASSETS_URI . '/admin/libs/sms-counter/sms_counter.min.js',
			['jquery'],
			null,
			true
		);

		// Select2
		wp_register_script(
			'tlr-select2',
			TLR_ASSETS_URI . '/admin/libs/select2/js/select2.min.js',
			['jquery'],
			'4.0.12',
			true
		);
		wp_register_style( 'tlr-select2', TLR_ASSETS_URI . '/admin/libs/select2/css/select2.min.css', [], '4.0.9' );

		// Intl Tel Input
		TLR\Texteller::register_mobile_field_assets();

        if ( false === strpos( $hook_suffix,'tlr' ) && false === strpos( $hook_suffix,'texteller' ) ) {
            return;
        }

		/**
		 * Enqueue page specific assets
		 */

		// Options assets
		if( false !== strpos( $hook_suffix,'tlr-options' ) ) {

			// jQuery UI
			wp_enqueue_script('jquery-ui-core');
			wp_enqueue_script( 'jquery-ui-sortable' );

			// Select2
			wp_enqueue_script('tlr-select2');
			wp_enqueue_style('tlr-select2');

			// Color Picker
			wp_enqueue_style( 'wp-color-picker' );
			//wp_enqueue_script( 'wp-color-picker');
			wp_enqueue_script(
				'wp-color-picker-alpha',
				TLR_ASSETS_URI . '/admin/libs/wp-color-picker-alpha/wp-color-picker-alpha.min.js',
				[ 'wp-color-picker' ],
				'2.1.3',
				true
			);

			// jQuery Transfer
			wp_enqueue_style(
				'tlr-jquery-transfer',
				TLR_ASSETS_URI . '/admin/libs/jquery-transfer/css/jquery.transfer.css'
			);
			wp_enqueue_style(
				'tlr-jquery-transfer-icons',
				TLR_ASSETS_URI . '/admin/libs/jquery-transfer/icon_font/css/icon_font.css'
			);
			wp_enqueue_script(
				'tlr-jquery-transfer',
				TLR_ASSETS_URI . '/admin/libs/jquery-transfer/js/jquery.transfer.js',
				['jquery'],
				1.0,
				true
			);

			wp_enqueue_script( 'tlr-sms-count' );

			wp_enqueue_script( 'tlr-intl-tel-input' );
			wp_enqueue_style( 'tlr-intl-tel-input' );
		}

		// Member edit assets
		elseif( false !== strpos( $hook_suffix,'tlr_edit_member' ) ) {

			wp_enqueue_script( 'tlr-select2' );
			wp_enqueue_style( 'tlr-select2' );
			wp_enqueue_script( 'tlr-sms-count' );
			wp_enqueue_script( 'post' );
			wp_enqueue_script( 'tlr-intl-tel-input' );
			wp_enqueue_style( 'tlr-intl-tel-input' );
		}

		// Manual send assets
		elseif( false !== strpos( $hook_suffix,'texteller' ) ) {

			wp_enqueue_script( 'tlr-select2' );
			wp_enqueue_style( 'tlr-select2' );
			wp_enqueue_script( 'tlr-intl-tel-input' );
			wp_enqueue_style( 'tlr-intl-tel-input' );
		}

		/**
		 * Enqueue admin stylesheet
		 */

		// Texteller admin stylesheet
		wp_enqueue_style( 'tlr-admin', TLR_ASSETS_URI . '/admin/tlr-admin.css' );

		$member_id = ! empty( $_GET['member'] ) ? (int) $_GET['member'] : 0;
		$user_id = TLR\tlr_get_user_id( $member_id );
		$user = $user_id ? get_userdata($user_id) : null;
		$intl_tel_input_options = get_option('tlr_intl_tel_input_options',[
			'initial_country'       =>  'US',
			'preferred_countries'   =>  ['US', 'IN', 'GB']
		]);

		$data = [
			'memberSelectorNonce'       =>  wp_create_nonce( 'tlr-member-selector-nonce' ),
			'userSelectorNonce'         =>  wp_create_nonce( 'tlr-user-selector-nonce' ),
			'memberSendNonce'           =>  wp_create_nonce( 'tlr-send-message-nonce' ),
			'sendStatusCheckNonce'      =>  wp_create_nonce( 'tlr-admin-manual-send-status' ),
			'manualSendNonce'           =>  wp_create_nonce( 'tlr-admin-manual-send' ),
			'gatewayDataNonce'          =>  wp_create_nonce( 'tlr-get-gateway-data' ),
			'memberSelectorPlaceholder' =>  __( 'Select Members', 'texteller' ),
			'userSelectorPlaceholder'   =>  __( 'Select a User', 'texteller' ),
			'staffSelectorPlaceholder'  =>  __( 'Select Staff', 'texteller'),
			'waitText'                  =>  __( 'Please wait.', 'texteller' ),
			'mayCloseNowText'           =>  __( 'You can close this window. Sending process would be continued in the background.' ),
			'memberDeleteAlert'         =>  __( 'This member will be deleted permanently and could not be recovered. Are you sure?', 'texteller' ),
			'intlTelOptions'            =>  [
				'preferredCountries'    =>  $intl_tel_input_options['preferred_countries'],
				'utilsURL'              =>  TLR_LIBS_URI . '/intl-tel-input/build/js/utils.js',
				'initialCountry'        =>  $intl_tel_input_options['initial_country'],
			],

			'tlr_send_sms_nonce'        =>  wp_create_nonce( 'tlr-filter-members-nonce' ),
			'display_name'              =>  isset( $user->display_name ) ? $user->display_name : null,
		];

		wp_enqueue_script(
			'tlr-admin',
			TLR_ASSETS_URI . '/admin/tlr-admin.js',
			[ 'jquery', 'tlr-sms-count', 'tlr-select2', 'tlr-intl-tel-input' ],
			'1.0.0',
			true
		);

		wp_localize_script( 'tlr-admin', 'tlrAdminData', $data );
	}

	public static function save_objects_per_page( $status, $option, $value )
    {
		if ( 'tlr-messages_per_page' === $option  || 'tlr-members_per_page' === $option ) {
			return $value; //todo
		} else {
			return $status;
		}
    }

	/**
     *
	 * Admin Menu
	 * Add admin menu items
	 *
	 * @since 3.0
	 */
	public static function admin_menu()
    {
	    add_menu_page(
			__('Texteller', 'texteller'),
			__('Texteller', 'texteller'),
			'manage_options',
		    'texteller',
		    [Manual_Send::class, 'render'],
		    'data:image/svg+xml;base64,'. base64_encode(
		    	file_get_contents(TLR_ASSETS_URI . '/admin/texteller-icon.svg')
		    )
		);

	    add_submenu_page(
		    'texteller',
		    __('Send Message', 'texteller'),
		    __('Send Message', 'texteller'),
		    'manage_options',
		    'texteller'
	    );

	    $members_page = add_submenu_page(
		    'texteller',
		    __('Members', 'texteller'),
		    __('Members', 'texteller'),
		    'manage_options',
		    'tlr-members',
		    [self::class, 'admin_members']
	    );
	    add_action( "load-$members_page", [self::class, 'init_member_list_table'] );

	    add_submenu_page(
	    	'texteller',
		    __('Member Groups', 'texteller'),
		    __('Member Groups', 'texteller'),
		    'manage_options',
		    'edit-tags.php?taxonomy=member_group',
		    null
	    );

	    $edit_page = add_submenu_page(
		    'tlr_hidden',
		    __('Edit Member', 'texteller'),
		    'Add new Member',
		    'manage_options',
		    'tlr_edit_member',
		    [self::class, 'admin_edit_member']
	    );

	    add_action( "load-$edit_page", function() use ( $edit_page ) {
	    	global $submenu;
	    	if ( isset( $submenu['tlr_hidden'] ) ) {
			    $submenu['texteller'] = array_merge( $submenu['tlr_hidden'], $submenu['texteller'] );
		    }
	    } );

	    add_filter( 'submenu_file', function( $submenu_file ) {
		    global $plugin_page;
		    if ( 'tlr_edit_member' === $plugin_page ) {
			    $submenu_file = 'tlr-members';
		    }
		    return $submenu_file;
	    } );

	    $messages_page = add_submenu_page(
	    	'texteller',
		    __('Messages History', 'texteller'),
		    __('Messages', 'texteller'),
		    'manage_options',
		    'tlr-messages',
		    [self::class, 'admin_messages']
	    );
	    add_action( "load-$messages_page", [self::class, 'init_message_list_table'] );

	    add_submenu_page(
		    'texteller',
		    __('Texteller Options', 'texteller'),
		    __('Options', 'texteller'),
		    'manage_options',
		    'tlr-options',
		    [self::class, 'render_options_page' ]
	    );

	    $tools_page = add_submenu_page(
		    'texteller',
		    __('Texteller Tools', 'texteller'),
		    __('Tools', 'texteller'),
		    'manage_options',
		    'tlr-tools',
		    ['Texteller\Admin\Tools', 'render' ]
	    );

        add_action( "load-$tools_page", [ 'Texteller\Admin\Tools', 'init' ] );
	}

	public static function init_member_list_table()
	{
		$arguments	=	array(
			'label'		=>  __('Members per page', 'texteller'),
			'default'	=>	20,
			'option'	=>	'tlr-members_per_page'
		);
		add_screen_option( 'per_page', $arguments );

		Member_List_Table::get_instance();
	}

	public static function init_message_list_table()
	{
		$arguments	=	array(
			'label'		=>  __( 'Messages per page', 'texteller' ),
			'default'	=>	20,
			'option'	=>	'tlr-messages_per_page'
		);

		add_screen_option( 'per_page', $arguments );
		Message_List_Table::get_instance();
	}

	public static function admin_edit_member()
	{
		// Get the current instance of Member Edit
		$edit_member = Member::get_instance();

		// Initialize, save data and render the page
		$edit_member->init();
	}

	public static function render_options_page() {
		TLR\get_admin_template('admin-options-base');
	}

	public static function admin_messages()
	{
		$messages_table = Message_List_Table::get_instance();
		$messages_table->render_table();
	}

	public static function admin_members()
	{
		$members_table = Member_List_Table::get_instance();
		$members_table->render_table();
	}

	public static function maybe_modify_member( $user_id )
	{
		$member_id = TLR\tlr_get_member_id($user_id, 'user_id');

		if ( $member_id > 0 ) {
			$member = new TLR\Member( $member_id );

			//if we should delete the member with the user
			if ( 'yes' === get_option('tlr_delete_member_with_user') ) {
				$member->delete();
			}  else {
				//if member was not deleted with user
				$member->unlink_user();
			}
		}
	}
}