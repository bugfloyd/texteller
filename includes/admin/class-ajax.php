<?php

namespace Texteller\Admin;
use Texteller as TLR;

defined( 'ABSPATH' ) || exit;

class Ajax {

	public static function init()
	{
		if ( ! wp_doing_ajax() ) {
			return;
		}

		add_action( 'wp_ajax_tlr_get_members', [self::class, 'members_selector_callback'] );
		add_action( 'wp_ajax_tlr_get_users', [self::class, 'users_selector_callback'] );
		add_action( 'wp_ajax_tlr_send_message', [self::class, 'send_message'] );
		add_action( 'wp_ajax_tlr_get_gateway_data', [ self::class, 'get_gateway_data' ] );

		add_action( 'wp_ajax_tlr_filter_members', [ Manual_Send::class, 'filter_members'] );
		add_action( 'wp_ajax_tlr_manual_send', [ Manual_Send::class, 'manual_send' ] );
		add_action( 'wp_ajax_tlr_manual_send_status', [ Manual_Send::class, 'check_manual_send_status' ] );
	}

	public static function get_gateway_data()
	{
		if (
			! check_ajax_referer( 'tlr-get-gateway-data', false, false )
			|| empty( $_REQUEST['gateway'] )
		) {
			wp_send_json_error( __( 'Invalid request','texteller' ) );
		}
		$gateway = $_REQUEST['gateway'];
		$active_gateways = get_option( 'tlr_active_gateways', [] );
		$gateway_class = TLR\tlr_get_gateway_class($gateway);

		if ( !in_array($gateway, $active_gateways, true) || !$gateway_class ) {
			wp_send_json_error( __( 'Invalid request','texteller' ) );
		}
		/** @var TLR\Interfaces\Gateway $gateway_class */
		$interfaces = [
			'interfaces'        =>  $gateway_class::get_interfaces(),
			'defaultInterface'  =>  $gateway_class::get_default_interface(),
			'contentTypes'      =>  $gateway_class::get_content_types()
		];
		wp_send_json( $interfaces );
	}

	public static function members_selector_callback()
	{
		$security_check_passes = (
			! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] )
			&& 'xmlhttprequest' === strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] )
			&& isset( $_GET['tlr_nonce'] )
			&& ( isset($_GET['q']) || isset($_GET['memberIDs']))
			&& wp_verify_nonce( $_GET['tlr_nonce'],  'tlr-member-selector-nonce' )
		);

		if ( ! $security_check_passes ) {
			wp_send_json_error( __( 'Invalid request','texteller' ) );
		}

		$member_ids = [];

		if ( !empty($_GET['q']) ) {
			$search = sanitize_text_field( $_GET['q'] );
			$member_ids = TLR\tlr_member_query($search);
		} elseif( !empty($_GET['memberIDs']) ) {
			foreach ($_GET['memberIDs'] as $member_id ) {
				$member_ids[] = intval($member_id);
			}
		}

		// bail if we don't have any results
		if ( empty( $member_ids ) ) {
			wp_send_json_error( $_GET );
		}

		$response  = [];
		foreach ( $member_ids as $member_id) {
			$member = new TLR\Member( $member_id );

			$response[] = [
				'member_id' => $member_id,
				'text'      => $member->get_name()
			];
		}
		wp_send_json_success( $response );
	}

	public static function send_message()
	{
		if ( ! check_ajax_referer( 'tlr-send-message-nonce', 'tlr_security', false )
		     || ! isset($_POST['message_content'])
		     || ! isset($_POST['member_id']) ) {
			wp_send_json_error( 'Invalid request sent.','200' );
		}

		if( $member_id = intval( $_REQUEST['member_id'] ) ) {

			$gateway = TLR\tlr_sanitize_input( $_POST['gateway'], 'gateway' );
			$text = TLR\tlr_sanitize_input( $_POST['message_content'], 'sms_content' );
			$member = new TLR\Member( $member_id );
			$mobile = $member->get_mobile();

			if( !TLR\tlr_is_mobile_valid( $mobile) ) {
				echo( json_encode([
					'responseText'  =>  __('Invalid Mobile!', 'texteller'),
					'responseCode'  =>  201
				]));
				wp_die();
			} elseif ( empty($text) ) {
				echo( json_encode([
					'responseText'  =>  __('Empty Text!', 'texteller'),
					'responseCode'  =>  202
				]));
				wp_die();
			} else {
				$save = 'yes' === get_option('tlr_manual_send_member_db_save', 'yes');
				$trigger_name = isset($_POST['trigger']) ? sanitize_text_field($_POST['trigger']) : 'tlr_manual_send_member';

				TLR\Gateway_Manager::send(
					$text,
					$mobile,
                    $trigger_name,
					$gateway,
					'',
					$save,
					$member_id
				);

				echo( json_encode([
					'responseText'  =>  __('Done!', 'texteller'),
					'responseCode'  =>  100
				]));
				wp_die();
			}
		}
	}

	public static function users_selector_callback() {

		$security_check_passes = (
			! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] )
			&& 'xmlhttprequest' === strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] )
			&& isset( $_GET['tlr_nonce'], $_GET['q'] )
			&& wp_verify_nonce( $_GET['tlr_nonce'],  'tlr-user-selector-nonce' )
		);

		if ( ! $security_check_passes ) {
			wp_send_json_error( $_GET );
		}

		// if we have an existing linked user_id, get the display_name
		if ( isset( $_GET['user_id'] ) && $_GET['user_id'] ) {
			$author_data = get_userdata( absint( $_GET['user_id'] ) );
			$results = [
				[
					'user_id' => $author_data->ID,
					'text' => $author_data->display_name,
				],
			];
			wp_send_json_success( $results );
		}

		$search = sanitize_text_field( $_GET['q'] );
		$user_query = new \WP_User_Query(
			[
				'search' => '*'.$search.'*',
				'search_columns' => [ 'user_login', 'user_email', 'user_nicename', 'ID' ],
				'number' => 10,
			]
		);

		// bail if we don't have any results
		if ( empty( $user_query->get_results() ) ) {
			wp_send_json_error( $_GET );
		}

		$results  = [];
		foreach ( $user_query->get_results() as $user ) {
			$results[] = [
				'user_id' => $user->ID,
				'text' => $user->display_name
			];
		}
		wp_send_json_success( $results );
	}


}