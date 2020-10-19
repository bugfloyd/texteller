<?php
/**
 * Lost password form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/form-lost-password.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 3.5.2
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_lost_password_form' );

$tlr_wc_lost_pw = get_option('tlr_wc_lost_password_base_gateway', 'both');
?>

	<form method="post" class="woocommerce-ResetPassword lost_reset_password">

		<p><?php
            if ( 'wc_default' === $tlr_wc_lost_pw ) {
                echo apply_filters( 'woocommerce_lost_password_message', esc_html__( 'Lost your password? Please enter your username, email address or mobile number. You will receive a link to create a new password via email.', 'texteller' ) );
            } elseif ( 'texteller' === $tlr_wc_lost_pw ) {
                esc_html_e( 'Lost your password? Please enter your username, email address or mobile number. You will receive a link to create a new password.', 'texteller' );
            } elseif ( 'both' === $tlr_wc_lost_pw ) {
                esc_html_e( 'Lost your password? Please enter your username, email address or mobile number. You will receive a link to create a new password.', 'texteller' );
            } else {
                esc_html_e( 'Lost your password? Please enter your username, email address or mobile number. You will receive a link to create a new password.', 'texteller' );
            }
			?></p><?php // @codingStandardsIgnoreLine ?>

		<p class="woocommerce-form-row woocommerce-form-row--first form-row form-row-first">
			<label for="user_login"><?php esc_html_e( 'Username, email or mobile number', 'texteller' ); ?></label>
			<input class="woocommerce-Input woocommerce-Input--text input-text" type="text" name="user_login" id="user_login" autocomplete="username" />
		</p>

		<?php if ( 'user_choice' === $tlr_wc_lost_pw ) : ?>
			<div class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<span><?php esc_html_e( 'How do you prefer to receive the reset password link?', 'texteller' ); ?></span>

				<div style="display:flex">

					<div style="display:flex;margin:10px;">
						<input type="radio" value="message" id="tlr-message" name="tlr_lost_pw_user_choice" style="margin-top:0.7em;">
						<label for="tlr-message" style="padding-left: 5px;padding-right: 5px;"><?php esc_html_e( 'Text Message', 'texteller' ); ?></label>
					</div>

					<div style="display:flex;margin:10px;">
						<input type="radio" value="email" id="tlr-email" name="tlr_lost_pw_user_choice" style="margin-top:0.7em;">
						<label for="tlr-email" style="padding-left: 5px;padding-right: 5px;"><?php esc_html_e( 'Email', 'texteller' ); ?></label>
					</div>

					<div style="display:flex;margin:10px;">
						<input type="radio" value="both" id="tlr-both" name="tlr_lost_pw_user_choice" checked="checked" style="margin-top:0.7em;">
						<label for="tlr-both" style="padding-left: 5px;padding-right: 5px;"><?php esc_html_e( 'Text Message & Email', 'texteller' ); ?></label>
					</div>

				</div>
			</div>
		<?php endif; ?>

		<div class="clear"></div>

		<?php do_action( 'woocommerce_lostpassword_form' ); ?>

		<p class="woocommerce-form-row form-row">
			<input type="hidden" name="wc_reset_password" value="true" />
			<button type="submit" class="woocommerce-Button button" value="<?php esc_attr_e( 'Reset password', 'woocommerce' ); ?>"><?php esc_html_e( 'Reset password', 'woocommerce' ); ?></button>
		</p>

		<?php wp_nonce_field( 'lost_password', 'woocommerce-lost-password-nonce' ); ?>

	</form>
<?php
do_action( 'woocommerce_after_lost_password_form' );
