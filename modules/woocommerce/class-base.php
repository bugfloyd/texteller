<?php

namespace Texteller\Modules\WooCommerce;

use Texteller\Member;
use function Texteller\tlr_get_member_id;

defined( 'ABSPATH' ) || exit;

/**
 * Class Base Texteller WooCommerce integration class
 * @package Texteller\Modules\WooCommerce
 */
final class Base {

	/**
	 * Base constructor.
	 */
	public function __construct()
	{
		self::define_constants();
		require_once TLR_WC_PATH . '/wc-functions.php';
		add_action( 'woocommerce_init', [self::class, 'init'] );
	}

	/**
	 * Initializes WooCommerce module options and notifications
	 */
	public static function init()
	{
		if ( !defined('WC_VERSION') || !version_compare( WC_VERSION, '6.1', ">=" ) ) {
			return;
		}
		new Options();
		new Notifications();
		Registration::init();

        add_action( 'add_meta_boxes', [ self::class, 'add_order_metabox' ] );
        add_filter( 'tlr_message_triggers', [self::class, 'add_module_triggers' ], 15, 1 );
	}

	public static function add_module_triggers( array $base_triggers )
    {
        $base_triggers['tlr_manual_send_order'] = _x( 'Admin Order Edit', 'message trigger', 'texteller' );

        return $base_triggers;
    }

	public static function add_order_metabox()
    {
        add_meta_box( 'tlr_order_metabox', __( 'Send Message', 'texteller'), [self::class, 'render_order_metabox'], 'shop_order', 'side' );
    }

    public static function render_order_metabox( $post )
    {
        $order = wc_get_order($post->ID);
        $user_id = $order->get_user_id();
        ?>
        <div>
            <?php
            if ( $user_id ) {
                $member_id = tlr_get_member_id( $user_id, 'user_id' );

                if ($member_id) {

                    $member = new Member($member_id);
                    if ( empty( $member->get_mobile() ) || $member->is_canceled() ) {
                        echo '<span>' . esc_html__('Membership status is canceled.') . '</span>';
                    } else {

                        wp_enqueue_script( 'tlr-sms-count' );

                        wp_enqueue_script(
                            'tlr-wc-admin',
                            TLR_WC_URI . '/assets/tlr-wc-admin.js',
                            [ 'jquery', 'tlr-sms-count' ],
                            '1.0.0',
                            true
                        );
                        $data = [
                            'memberSendNonce'   =>  wp_create_nonce( 'tlr-send-message-nonce' )
                        ];
                        wp_localize_script( 'tlr-wc-admin', 'tlrWCAdminData', $data );

                        ?>
                        <div>
                            <span><strong><?php esc_html_e('Mobile Number:','texteller'); ?> </strong><?= $member->get_mobile(); ?></span>
                        </div>
                        <br>
                        <div class="send-message">
                            <div class="message-content-wrap">
                                <label for="message-content"><?= __( 'Message Content', 'texteller' ) ?></label>
                                <textarea id="message-content" class="tlr-count"></textarea>
                                <label for="message-gateway"><?= __( 'Gateway', 'texteller' ) ?></label>
                                <select id="message-gateway" style="width:100%;">
                                    <?php
                                    $gateways = (array) get_option( 'tlr_active_gateways', [] );
                                    foreach ( $gateways as $gateway ) {
                                        ?>
                                        <option value="<?= $gateway; ?>"><?= ucfirst( $gateway ); ?></option>
                                        <?php
                                    }
                                    ?>
                                </select>
                                <input id="member-id" type="hidden" hidden value="<?= $member_id; ?>">
                                <button id="send-message" name="send-message" style="margin-top:10px;width:100%;" disabled><?= __( 'Send Message', 'texteller'); ?></button>
                                <span class="send-result"></span>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    echo '<span>' . esc_html__('No Texteller member found for this user') . '</span>';
                }
            }
            ?>
        </div>
        <?php
    }

	/**
	 * Defines module constants
	 */
	private static function define_constants()
	{
		if ( !defined('TLR_MODULES_PATH' ) ) {
			return;
		}
		if ( !defined( 'TLR_WC_PATH' ) ) {
			define( 'TLR_WC_PATH', TLR_MODULES_PATH . '/woocommerce' );
		}
		if ( !defined( 'TLR_WC_URI' ) ) {
			define( 'TLR_WC_URI', TLR_MODULES_URI . '/woocommerce' );
		}
	}
}