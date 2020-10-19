<?php

namespace Texteller;

defined( 'ABSPATH' ) || exit;

class Verify_Number {

	/**
	 * @var Member
	 */
	private $member = null;

	public function __construct() {}

	/**
	 * @return Member
	 */
	public function get_member(): Member {
		return $this->member;
	}

	/**
	 * @param Member $member
	 */
	public function set_member( Member $member ) {
		$this->member = $member;
	}

	/**
	 * Creates, stores, then returns a verification key for member.
	 **
	 * @global \PasswordHash $wp_hasher Portable PHP password hashing framework.
	 * @return string|bool Password reset key on success. False on error.
	 */
	private function get_verification_key()
    {
		global $wp_hasher;

		if ( ! ( $this->member instanceof Member ) ) {
			return false;
		}

		// Generate a 5 digit number for verification key.
		$key = tlr_generate_code();

		// Now insert the key, hashed, into the DB.
		if ( empty( $wp_hasher ) ) {
			require_once ABSPATH . WPINC . '/class-phpass.php';
			$wp_hasher = new \PasswordHash( 8, true );
		}

		$hashed = time() . ':' . $wp_hasher->HashPassword( $key );
		$this->member->set_verification_key( $hashed );
		$key_saved = $this->member->update_verification_key();

		if ( ! $key_saved ) {
			return false;
		}
		return (string) $key;
	}

	/**
	 * Retrieves a member based on verification key and the instance of the member
	 *
	 * A key is considered 'expired' if it exactly matches the value of the
	 * user_activation_key field, rather than being matched after going through the
	 * hashing process. This field is now hashed; old values are no longer accepted
	 * but have a different WP_Error code so good user feedback can be provided.
	 *
     *
	 * @global \PasswordHash $wp_hasher Portable PHP password hashing framework instance.
	 *
	 * @param string $key       Hash to verify members's number.
	 * @return bool|\WP_Error true on success, WP_Error object for invalid or expired keys.
	 */
	private function check_verification_key( $key )
    {
		global $wp_hasher;

		$key = preg_replace( '/[^a-z0-9]/i', '', $key );
		if ( empty( $key ) || ! is_string( $key ) ) {
			return false;
		}
		if ( ! $this->member instanceof Member ) {
			return false;
		}

		if ( empty( $wp_hasher ) ) {
			require_once ABSPATH . WPINC . '/class-phpass.php';
			$wp_hasher = new \PasswordHash( 8, true );
		}

		$verification_key = $this->member->read_verification_key();

		if ( false !== strpos( $verification_key, ':' ) ) {
			list( $verify_request_time, $verify_key ) = explode( ':', $verification_key, 2 );
			$stored_lifetime = get_option('tlr_code_lifetime', 5);
			$expiration_duration = $stored_lifetime > 1 ? absint($stored_lifetime) : 5 ; //minute
			$expiration_time = $verify_request_time + $expiration_duration * 60 ;
		} else {
			return false;
		}

		$hash_is_correct = $wp_hasher->CheckPassword( $key, $verify_key );

		if ( $hash_is_correct && $expiration_time && time() < $expiration_time ) {
			return true;
		}
		// Key has an expiration time that's passed
		elseif ( $hash_is_correct && $expiration_time ) {

			$code = $this->get_verification_key();

			/**
			 * This action is documented in init_verification_listener method of this class
             *
             * @see \Texteller\Verify_Number::init_verification_listener()
			 */
			do_action( 'tlr_verification', $this->member, $code );

			return false;
		}

		return false;
	}

	public function get_html()
    {
        if( is_null($this->member) ) {
            return '';
        }
	    ob_start();
        ?>
        <div class="tlr-verification-wrap"><?php
	    if( $this->member->is_verified() ) : ?>
            <span><?php esc_html_e( 'Your number is verified.', 'texteller' );?></span><?php

	    else: ?>
            <div class="verify-number">
                <input id="member-id" hidden value="<?= $this->member->get_id(); ?>" aria-label="hidden field">
                <input id="tlr-init-verify-check" hidden value="<?= wp_create_nonce( 'tlr-init-verification' ); ?>" aria-label="hidden field">
                <div class="description-wrap">
                    <span><?php esc_html_e( 'Your number is not verified. Please Verify your number.', 'texteller' );?></span>
                </div>
                <button id="tlr-init-verify" class="tlr-submit tlr-submit-init-verification" type="button" style="font-size: 13px;">
				    <?php esc_html_e( 'Verify number', 'texteller' ) ;?>
                </button>
            </div>
            <script>
                jQuery(document).ready(function ($) {

                    let initVerify = $('.tlr-submit-init-verification');
                    let descriptionWrap = $('.description-wrap');

                    initVerify.on('click', function(event) {
                        event.preventDefault();
                        initVerify.attr('disabled', 'disabled');
                        let initialInitVerifyLabel = initVerify.text();
                        initVerify.text( '<?= __( 'Please Wait', 'texteller' ); ?>' );
                        let postData = {
                            'memberID': $('#member-id').val(),
                            'tlrCheck' : $('#tlr-init-verify-check').val(),
                            'action': 'tlr_init_verification'
                        };

                        $.post( '<?= admin_url( 'admin-ajax.php' ); ?>', postData, function(response) {
                            descriptionWrap.hide();
                            initVerify.removeAttr('disabled', 'disabled');
                            if( 'false' === response.success ) {
                                initVerify.text( initialInitVerifyLabel );
                            } else {
                                let data = JSON.parse( response );
                                // If the ajax response isn't well formatted in case any error occurs
                                if ( !data.hasOwnProperty('html') ) {
                                    initVerify.text( initialInitVerifyLabel );
                                    return;
                                }
                                initVerify.hide();
                                $('.tlr-verification-wrap').append( data.html );
                            }
                        });
                    });
                });
            </script>
        <?php endif; ?>
        <style>
            .tlr-verification-wrap {font-size:14px;}
            .tlr-submit:hover,.tlr-submit:focus { text-decoration:none; }
            .verify-number { display: flex;}
            .verify-number div { padding:5px;}
        </style>
        </div><?php

	    return ob_get_clean();
    }

    public function init_verification_listener()
    {
	    if ( ! check_ajax_referer( 'tlr-init-verification', 'tlrCheck', false ) || ! isset($_POST['memberID']) ) {
		    wp_send_json_error( 'Invalid request sent.' );
	    }

	    $member_id = (int) sanitize_text_field( $_POST['memberID'] );

	    if( $member_id > 0 ) {
		    $member = new Member($member_id);
		    $this->member = $member;
		    $code = $this->get_verification_key();

		    /**
		     * Fires after a verification code is generated for a member
             *
             * @see \Texteller\Core_Modules\Base_Module\Notifications::send_verify_code()
             *
             * @see \Texteller\Verify_Number::check_verification_key()
             *
             * @param \Texteller\Member $member
             * @param string $code
		     */
		    do_action( 'tlr_verification', $member, $code );

		    echo( json_encode([
			    'html'  =>  $this->get_code_html(),
		    ]));
		    wp_die();
        } else {
		    wp_send_json_error( 'Invalid request sent.' );
        }
    }

	public function get_code_html()
	{
		ob_start();
		?>
		<style>
			.tlr-code-table{border:none;margin:0;display:inline-block;vertical-align: middle;}
			/*.tlr-verification{text-align: center;}*/
			.tlr-code-row-wrapper{direction:ltr;display:inline-block;}
			.tlr-digit-wrapper{border: none;padding: 0!important;}
			input.tlr-digit::-webkit-inner-spin-button,input.tlr-digit::-webkit-outer-spin-button{
                -webkit-appearance:none;
                margin:0;
            }
			input.tlr-digit:focus{outline:none;}
			input.tlr-digit::placeholder{color:#ccc;font-size:30px;}
			input.tlr-digit {
				color:#716c6c;
				border:none;
				width:30px;
				height:40px;
				font-size:20px;
				padding:0;
				text-align: center;
                margin:0;
                border-radius: 0;
                -moz-appearance:textfield;
			}
			.tlr-digit-wrapper:first-of-type input.tlr-digit {border-radius: 2px 0 0 2px;}
			.tlr-digit-wrapper:last-of-type input.tlr-digit {border-radius: 0 2px 2px 0;}
			.tlr-submit-code{ vertical-align: middle;border-radius: 3px;padding: 7px 15px; font-size: 13px!important;}
            .tlr-submit-code:hover, .tlr-submit-code:focus{text-decoration:unset!important;}
            .tlr-verify-submit-wrap{display:inline-block; vertical-align: middle;}
            .tlr-verification-result-text {font-size:14px;}
		</style>
		<span class="tlr-verification-result-text"><?php esc_html_e( 'Please enter the code just sent to your inbox to verify your mobile number.', 'texteller' ); ?></span>
		<div class="tlr-verification">
            <div class="tlr-code-table">
                <table class="tlr-code-table">
                    <tr class="tlr-code-row-wrapper">
                        <td class="tlr-digit-wrapper">
                            <input id="tlr-code0" name="tlr_activation_code[0]" class="tlr-digit" type="number" placeholder="_" maxlength="1" pattern="\d*">
                        </td>
                        <td class="tlr-digit-wrapper">
                            <input id="tlr-code1" name="tlr_activation_code[1]" class="tlr-digit" type="number" placeholder="_" maxlength="1" pattern="\d*">
                        </td>
                        <td class="tlr-digit-wrapper">
                            <input id="tlr-code2" name="tlr_activation_code[2]" class="tlr-digit" type="number" placeholder="_" maxlength="1" pattern="\d*">
                        </td>
                        <td class="tlr-digit-wrapper">
                            <input id="tlr-code3" name="tlr_activation_code[3]" class="tlr-digit" type="number" placeholder="_" maxlength="1" pattern="\d*">
                        </td>
                        <td class="tlr-digit-wrapper">
                            <input id="tlr-code4" name="tlr_activation_code[4]" class="tlr-digit" type="number" placeholder="_" maxlength="1" pattern="\d*">
                        </td>
                    </tr>
                </table>
            </div>
			<div class="tlr-verify-submit-wrap">
                <label style="display:none;">
                    <span><?php esc_html_e('Verification Hidden Data', 'texteller'); ?></span>
                    <input id="tlr-member-id" hidden value="<?= $this->member->get_id(); ?>">
                    <input id="tlr-verification-check" hidden value="<?= wp_create_nonce( 'tlr-verify-mobile' ); ?>">
                </label>
				<button id="tlr-submit-verify-code" class="tlr-submit tlr-submit-code" type="button">
					<?php esc_html_e( 'Send code', 'texteller' ) ;?>
				</button>
			</div>
		</div>
		<script>
            jQuery(document).ready(function ($) {

                function isTextSelected(input) {
                    if ( typeof input.selectionStart == "number" ) {
                        return input.selectionStart === 0 && input.selectionEnd === input.value.length;
                    } else if (typeof document.selection != "undefined") {
                        input.focus();
                        return document.selection.createRange().text === input.value;
                    }
                }
                let codeDigit = $('.tlr-digit');

                // Limit one digit per digit-input
                codeDigit.keypress( function () {
                    if( isTextSelected( this ) ) {
                        return true;
                    }
                    if(this.value.length === 1) return false;
                });

                // Auto-focus for digit-inputs
                codeDigit.keyup(function (event) {
                    event.preventDefault();
                    if (!isFinite(event.key)) return;
                    let a = $(this).attr('id');
                    a = a.substr(0, a.length - 1) + (Number.parseInt(a[a.length - 1]) + 1);
                    a = $('#' + a);
                    if (a.length > 0) {
                        a.focus();
                    }
                });

                let submitCode = $('.tlr-submit-code');
                submitCode.on('click', function(event) {

                    event.preventDefault();
                    submitCode.attr('disabled', 'disabled');

                    let initialButtonText = submitCode.text();
                    submitCode.text( '<?= __( 'Please Wait', 'texteller' ); ?>' );
                    let code = '' + $('input#tlr-code0').val() + $('input#tlr-code1').val() + $('input#tlr-code2').val()
                        + $('input#tlr-code3').val() + $('input#tlr-code4').val();
                    let postData = {
                        'memberID': $('#tlr-member-id').val(),
                        'tlrCode' : code,
                        'tlrCheck': $('#tlr-verification-check').val(),
                        'action': 'tlr_verify_number'
                    };

                    $.post( '<?= admin_url( 'admin-ajax.php' ); ?>', postData, function(response) {

                        submitCode.removeAttr('disabled');

                        let resultText = $('.tlr-verification-result-text');

                        if( 'false' === response.success ) {
                            resultText.text( '<?php esc_html_e( 'An error occurred please try again.', 'texteller' ); ?>' );
                        } else {
                            let data = JSON.parse( response );

                            // If the ajax response isn't well formatted in case any error occurs
                            if ( !data.hasOwnProperty('response') || !data.hasOwnProperty('code')) {
                                resultText.text( '<?php esc_html_e( 'An error occurred please try again.', 'texteller' ); ?>' );
                                $('.tlr-digit').val('');
                                submitCode.text( initialButtonText );
                                return;
                            }

                            if( 'success' === data.code || 'no-need' === data.code ) {
                                $('.tlr-verification').fadeOut('slow').empty();
                            } else {
                                $('.tlr-digit').val('');
                                submitCode.text( initialButtonText );
                            }
                            resultText.text( '' + data.response );
                        }
                    });
                });
            });
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Ajax listener for member validation (registration form)
	 */
	public function member_validation_listener()
	{
		if ( ! check_ajax_referer( 'tlr-verify-mobile', 'tlrCheck', false )
		     || ! isset($_POST['tlrCode'])
		     || ! isset($_POST['memberID'])
		) {
			wp_send_json_error( 'Invalid request sent.' );
		}

		$code = (int) $_POST['tlrCode'];
		$member_id = (int) sanitize_text_field( $_POST['memberID'] );

		if ( $member_id > 0 ) {
			$this->member = new Member( $member_id );

			if( 'verified' !== $this->member->get_status() ) {
				if ( $code && $code > 9999 && $code < 100000 && true === $this->check_verification_key( $code ) ) {
					$this->member->verify();
					echo( json_encode([
						'response'  =>  __( 'Your number is successfully verified.', 'texteller' ),
						'code'  =>  'success'
					]));
					wp_die();
				} else {
					echo( json_encode([
						'response'  =>  __( 'The code entered is invalid or expired. Please enter the latest code, sent to your inbox.', 'texteller' ),
						'code'  =>  'invalid'
					]));
					wp_die();
				}
			} else {
				echo( json_encode([
					'response'  =>  __( 'Your number is already verified.', 'texteller' ),
					'code'  =>  'no-need'
				]));
				wp_die();
			}

		} else {
			echo( json_encode([
				'response'  =>  __( 'To verify your number, please register first.', 'texteller' ),
				'code'  =>  'error'
			]));
			wp_die();
		}
	}
}