<?php

namespace Texteller\Modules\WooCommerce;
use Texteller as TLR;

defined( 'ABSPATH' ) || exit;

/**
 * Class Notifications Handles WooCommerce notifications
 * @package Texteller\Modules\WooCommerce
 */
final class Notifications
{
	/**
	 * Notifications constructor.
	 */
	public function __construct()
	{
		$this->init();
	}

	/**
	 * Initializes notification trigger hooks
	 */
	public function init()
	{
		add_action( 'tlr_wc_new_customer_registered', [self::class, 'new_customer_registered'], 10, 3 );
		add_action( 'tlr_wc_reset_password_notification', [self::class, 'send_rp_link'], 10, 2 );
		add_action( 'tlr_wc_checkout_old_customer_registered', [self::class, 'checkout_old_customer_registered'] );
		add_action( 'tlr_wc_account_existing_customer_registered', [self::class, 'account_edit_existing_customer_registered'] );
		add_action( 'tlr_wc_account_member_updated', [self::class, 'account_updated'] );

		add_action( 'woocommerce_new_order', [ self::class, 'admin_new_order_placed' ], 10, 2 );
		add_action( 'woocommerce_order_payment_status_changed', [ self::class, 'customer_new_order_placed' ] );
		add_action( 'woocommerce_order_status_changed', [ self::class, 'order_status_changed' ], 10, 4 );
	}

	/**
	 * Generates and returns tag values for the tag type
	 *
	 * @see \Texteller\Gateway_Manager::get_tags()
	 *
	 * @param string $tag_type
	 * @param \Texteller\Tags $tags Current Tags instance to be processed
	 * @param mixed $object Current object to use while generating tag values
	 * @param array $extra_tags Custom tags added by the trigger
	 */
	public static function set_tags_values( string $tag_type, TLR\Tags &$tags, $object, array $extra_tags )
	{
		switch ( $tag_type ) {
			case 'customer':
				$member_tags  = TLR\tlr_get_base_tags_values_array('member', $object);
				$customer_tags_values = [
					'login_link'    =>  esc_url( trailingslashit( wc_get_account_endpoint_url( '' ) ) )
				];
				$tags->add_tag_type_data('customer', array_merge( $member_tags, $customer_tags_values ) );
				break;

			case 'lost_customer':
				$member_tags  = TLR\tlr_get_base_tags_values_array('member', $object);

				$lost_customer_tags_values = [
					'rp_link'       =>  $extra_tags['rp_link'] ?? ''
				];

				$tags->add_tag_type_data('lost_customer', array_merge($member_tags,$lost_customer_tags_values));
				break;

			case 'new_customer':
				$member_tags  = TLR\tlr_get_base_tags_values_array('member', $object);
				$customer_tags_values = [
					'login_link'    =>  esc_url( trailingslashit( wc_get_account_endpoint_url( '' ) ) ),
					'rp_link'       =>  $extra_tags['rp_link'] ?? ''
				];

				if ( isset( $extra_tags['password'] ) ) {
					$customer_tags_values['password'] = $extra_tags['password'];
				}
				$tags->add_tag_type_data('new_customer', array_merge( $member_tags, $customer_tags_values ) );
				break;

			case 'order':
				if ( is_a( $object, '\WC_Order') ) {
					$statuses = array(
						'pending'    => _x('Pending', 'woocommerce order status', 'texteller'),
						'processing' => _x('Processing', 'woocommerce order status', 'texteller'),
						'completed'  => _x('Completed', 'woocommerce order status', 'texteller'),
						'cancelled'  => _x('Cancelled', 'woocommerce order status', 'texteller'),
						'refunded'   => _x('Refunded', 'woocommerce order status', 'texteller'),
						'on-hold'    => _x('On-Hold', 'woocommerce order status', 'texteller')
					);

					$items        = $object->get_items();
					$items_string = [];
					foreach ( $items as $item ) {
						$items_string[] = "{$item['name']} x{$item['qty']}";
					}

					/** @var \WC_DateTime $order_date */
					$order_date = $object->get_date_paid() ? $object->get_date_paid() : $object->get_date_created();
					$order_date = $order_date->format('Y-m-d H:i:s');

					$tag_values = [
						'first_name'    =>  ! empty( $object->get_billing_first_name() ) ?
							$object->get_billing_first_name() : $object->get_shipping_first_name(),

						'last_name'     =>  ! empty( $object->get_billing_last_name() )
							? $object->get_billing_last_name() : $object->get_shipping_last_name(),

						'status'        =>  isset($statuses[ $object->get_status() ])
							? $statuses[ $object->get_status() ] : $object->get_status(),

						'total'         =>  number_format( $object->get_total() ),
						'transaction_id'=>  $object->get_transaction_id(),
						'order_id'      =>  $object->get_id(),
						'date'          =>  $order_date,
						'items'         =>  implode( "\n", $items_string )
					];
					$tags->add_tag_type_data('order', $tag_values);
				}
				break;
		}
	}

	/**
	 * Gets member IDs or mobile numbers for the current trigger recipient type and object
	 *
	 * @param   string    $trigger_recipient_type
	 * @param   object    $object
	 * @param   array     $trigger_recipient_args
	 *
	 * @return array
	 */
	public static function get_trigger_recipient_member_ids( $trigger_recipient_type, $object, $trigger_recipient_args )
	{
		switch( $trigger_recipient_type ) {

			case 'new_customer':
			case 'lost_customer':
			case 'customer':
				if ( is_a($object,'\Texteller\Member') ) {
					/** @var \Texteller\Member $object */
					$member_id = $object->get_id();
				}
				return !empty($member_id) ? [ 'members' => [$member_id] ] : [];

			case 'order_customer':
				if ( is_a($object,'\WC_Order') ) {
					/** @var \WC_Order $object */
					$member_id = TLR\tlr_get_member_id( $object->get_customer_id(), 'user_id' );
				}
				return !empty($member_id) ? [ 'members' => [$member_id] ] : [];

			case 'order_admin':
				if ( is_a($object,'\WC_Order') ) {
					/** @var \WC_Order $object */
					$order_admin = $object->get_created_via();
					if ( $order_admin ) {
						$admin_user = get_user_by('login', $order_admin);
						if ( $admin_user && $admin_user->ID ) {
							$member_id = TLR\tlr_get_member_id( $admin_user->ID, 'user_id' );
						}
					}
					return isset($member_id) ? [ 'members' => [$member_id] ] : [];
				}
				break;

			default:
				return [];
		}
	}
	///////////////////////////////////////////////////
	///                                             ///
	///             Notification Triggers           ///
	///                                             ///
	///////////////////////////////////////////////////

	/**
	 * Sends notifications when new WC customer is registered
	 *
	 * @see \Texteller\Modules\WooCommerce\Registration::account_checkout_new_customer_registration()
	 *
	 * @param \Texteller\Member Registered member
	 * @param int $customer_id User ID of the registered customer
	 * @param array $new_customer_data Customer data array
	 */
	public static function new_customer_registered( TLR\Member $member, $customer_id, $new_customer_data )
	{
		TLR\Gateway_Manager::send_notification( [
			'notification_class'    =>  self::class,
			'tag_type'              =>  'customer',
			'trigger'               =>  'wc_registration_new_customer',
			'object'                =>  $member
		] );

		$gateway_option = get_option('tlr_wc_registration_new_customer_notification_base_gateway', 'wc_default');

		if ( 'wc_default' !== $gateway_option ) {
			$rp_link = '';
			if ('yes' === get_option( 'woocommerce_registration_generate_password' ) && 'both' !== $gateway_option) {
				// Generate a magic link so user can set initial password.
				$user = get_user_by('ID', $customer_id);
				$key = get_password_reset_key( $user );
				if ( ! is_wp_error( $key ) ) {
					$action                 = 'newaccount';
					$rp_link = wc_get_account_endpoint_url( 'lost-password' ) . "?action=$action&key=$key&login=" . rawurlencode( $user->user_login );
				}
			}
			TLR\Gateway_Manager::send_notification( [
				'notification_class'    =>  self::class,
				'tag_type'              =>  'new_customer',
				'trigger'               =>  'wc_registration_new_customer_new_customer_rp',
				'object'                =>  $member,
				'extra_tags'            =>  [ 'rp_link' => $rp_link ]
			] );
		}


	}

	/**
	 * Sends retrieve password link
	 *
	 * @see \Texteller\Modules\WooCommerce\Registration::retrieve_password()
	 *
	 * @param integer $user_id
	 * @param string $key
	 */
	public static function send_rp_link( $user_id, $key )
	{
		$lost_pw_recovery = get_option( 'tlr_wc_lost_password_base_gateway', 'both' );

		if ( 'wc_default' === $lost_pw_recovery ) {
			return;
		} elseif ( 'user_choice' === $lost_pw_recovery ) {

			if ( isset( $_POST['tlr_lost_pw_user_choice'] ) ) {
				$user_choice = in_array( $_POST['tlr_lost_pw_user_choice'], [ 'email', 'message', 'both' ], true ) ? $_POST['tlr_lost_pw_user_choice'] : 'both';
			} else {
				$user_choice = 'both';
			}

			if ( 'email' === $user_choice ) {
				return;
			}
		}

		$member_id = TLR\tlr_get_member_id( $user_id, 'user_id');
		$rp_link = esc_url( add_query_arg( array( 'key' => $key, 'id' => $user_id ), wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) ) ) );

		if ( $member_id ) {
			TLR\Gateway_Manager::send_notification( [
				'notification_class'    =>  self::class,
				'tag_type'              =>  'lost_customer',
				'trigger'               =>  'wc_lost_pw_rp_link',
				'object'                =>  new TLR\Member($member_id),
				'extra_tags'            =>  [ 'rp_link' => $rp_link, 'user_id' => $user_id ]
			] );
		}
	}

	/**
	 * Sends notifications after an existing customer registers via checkout
	 *
	 * @see \Texteller\Modules\WooCommerce\Registration::checkout_old_customer_registration()
	 *
	 * @param \Texteller\Member $member
	 */
	public static function checkout_old_customer_registered( TLR\Member $member )
	{
		TLR\Gateway_Manager::send_notification( [
			'notification_class'    =>  self::class,
			'tag_type'              =>  'customer',
			'trigger'               =>  'wc_registration_checkout_old_customer',
			'object'                =>  $member
		] );
	}

	/**
	 * Send notifications after an existing customer registers via My-Account page
	 *
	 * @see \Texteller\Modules\WooCommerce\Registration::edit_account_member_registration()
	 *
	 * @param \Texteller\Member $member
	 */
	public static function account_edit_existing_customer_registered( TLR\Member $member )
	{
		TLR\Gateway_Manager::send_notification( [
			'notification_class'    =>  self::class,
			'tag_type'              =>  'customer',
			'trigger'               =>  'wc_account_edit_old_customer_registered',
			'object'                =>  $member
		] );
	}

	/**
	 * Sends notifications when a customer with a linked member updates their data on WC My-Account page
	 *
	 * @see \Texteller\Modules\WooCommerce\Registration::edit_account_member_registration()
	 *
	 * @param \Texteller\Member $member
	 */
	public static function account_updated( TLR\Member $member )
	{
		TLR\Gateway_Manager::send_notification( [
			'notification_class'    =>  self::class,
			'tag_type'              =>  'customer',
			'trigger'               =>  'wc_registration_account_updated',
			'object'                =>  $member
		] );
	}

	/**
	 * @param int $order_id
	 * @param \WC_Order $order
	 */
	public static function admin_new_order_placed( $order_id, $order )
	{
		if ( is_admin() ) {
			TLR\Gateway_Manager::send_notification([
				'notification_class'    =>  self::class,
				'tag_type'              =>  'order',
				'trigger'               =>  'wc_new_admin_order',
				'object'                =>  $order
			]);
		}
	}

	/**
	 * Sends notification when a new order is placed by a customer (After a successfully completed payment)
	 *
	 * @param int $order_id
	 *
	 * @since 0.1.2
	 */
	public static function customer_new_order_placed( $order_id )
	{
		if ( ! is_admin() ) {
			TLR\Gateway_Manager::send_notification([
				'notification_class'    =>  self::class,
				'tag_type'              =>  'order',
				'trigger'               =>  'wc_new_customer_order',
				'object'                =>  wc_get_order($order_id)
			]);
		}
	}

	/**
	 * @param int $order_id
	 * @param string $from
	 * @param string $to
	 * @param \WC_Order $order
	 *
	 * @since 0.1.2
	 */
	public static function order_status_changed( $order_id, $from, $to, $order )
	{
		switch ($to) {
			case 'on-hold':
				TLR\Gateway_Manager::send_notification([
					'notification_class'    =>  self::class,
					'tag_type'              =>  'order',
					'trigger'               =>  'wc_order_status_on_hold',
					'object'                =>  $order
				]);
				break;

			case 'completed':
				TLR\Gateway_Manager::send_notification([
					'notification_class'    =>  self::class,
					'tag_type'              =>  'order',
					'trigger'               =>  'wc_order_status_completed',
					'object'                =>  $order
				]);
				break;
			case 'refunded':
				TLR\Gateway_Manager::send_notification([
					'notification_class'    =>  self::class,
					'tag_type'              =>  'order',
					'trigger'               =>  'wc_order_status_refunded',
					'object'                =>  $order
				]);
				break;
			case 'cancelled':
				TLR\Gateway_Manager::send_notification([
					'notification_class'    =>  self::class,
					'tag_type'              =>  'order',
					'trigger'               =>  'wc_order_status_cancelled',
					'object'                =>  $order
				]);
				break;
			case 'failed':
				TLR\Gateway_Manager::send_notification([
					'notification_class'    =>  self::class,
					'tag_type'              =>  'order',
					'trigger'               =>  'wc_order_status_failed',
					'object'                =>  $order
				]);
				break;
		}
	}
}
