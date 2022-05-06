<?php

namespace Texteller\Core_Modules\Newsletter;
use Texteller as TLR;

defined( 'ABSPATH' ) || exit;

final class Registration
{
	public function __construct() {}

	public static function init()
	{
		add_action( 'wp_ajax_tlr_registration', [ self::class, 'member_registration_listener' ] );
		add_action( 'wp_ajax_nopriv_tlr_registration', [ self::class, 'member_registration_listener' ] );
		add_shortcode( 'tlr_newsletter', [ self::class, 'shortcode_generator'] );
		add_action( 'widgets_init', function() {
			register_widget( new Newsletter_Widget() );
        } );
		add_action( 'wp_enqueue_scripts', [self::class, 'register_assets'], 10 );
	}

	/**
	 * Ajax listener for member registration from registration form
	 */
	public static function member_registration_listener()
	{
		if ( ! check_ajax_referer( 'tlr-nl-new-member', 'tlrCheck', false ) ) {
			wp_send_json_error( 'Invalid security token sent.' );
		}

		$registration_fields  = get_option('tlr_nl_form_fields');
		$errors = new \WP_Error();
		$registration_module = TLR\Registration_Module::get_instance();
		$registration_module->init( $registration_fields, $errors, [], 'nl_form' );

		if( $errors->has_errors() ) {
		    self::respond( $errors->get_error_message(), 'message', 'error' );
		} else {
            // Maybe link the current user to the new member
            $current_user_id = wp_get_current_user()->ID ;
            $maybe_member_id = $current_user_id > 0 ? TLR\tlr_get_member_id( $current_user_id, 'user_id' ) : 0;
			$user_id    =  ! $maybe_member_id && 'yes' === get_option('tlr_nl_link_user', 'yes') ? $current_user_id : 0;
			$member_id = $registration_module->register_new_user_member( $user_id, 'tlr-newsletter' );

			// if member successfully inserted
			if ( $member_id > 0 ) {

				/**
				 * Fires after a successful member registration from newsletter form
                 *
                 * @see \Texteller\Core_Modules\Newsletter\Notifications::member_registered()
                 *
				 * @param \Texteller\Member Current registered Member
				 */
				do_action('texteller_nl_member_registered', $registration_module->get_member() );

				if ( 'yes' === get_option('tlr_nl_mobile_verification') ) {
					$response = "<span class='tlr-result-text success'>" . __('Registration was successful.', 'texteller') . "</span>";
					$number_verification = new TLR\Verify_Number();
					$number_verification->set_member( $registration_module->get_member() );
					$response .= $number_verification->get_html();
					self::respond( $response, 'html', 'notice' );
				} else {
					self::respond( __('Registration was successful.', 'texteller'), 'message', 'success' );
				}

			} else {
				self::respond( __( 'An error occurred. Please try again or contact us.', 'texteller' ), 'message', 'error' );
			}
		}
	}

	private static function respond( $content, $type = 'message', $code = 'success' )
    {
        if( 'message' === $type ) {
            $content = "<span class='tlr-result-text $code'>$content</span>";
        }
        echo json_encode( [ 'response'  =>  $content, 'code' => $code ] );
	    wp_die();
    }

	public static function shortcode_generator()
	{
		self::enqueue_form_assets();
		$fields = TLR\get_form_fields('tlr_nl_form_fields', Options::class );

		$form_labels = get_option('tlr_nl_form_labels',[
			'form_title'        =>  'Newsletter',
			'form_description'  =>  'Subscribe to our newsletter to receive the latest news and special offers',
			'submit_label'      =>  'SUBSCRIBE NOW!'
        ]);
		$title = isset($form_labels['form_title']) ? $form_labels['form_title'] : '';
		$desc = isset($form_labels['form_description']) ? $form_labels['form_description'] : '';
		$submit_text = isset($form_labels['submit_label']) ? $form_labels['submit_label'] : '';

		ob_start();
		?>
		<form method="post" id="tlr-nl-form"><div class="tlr-registration-form">
            <div class="tlr-overlay"></div>
            <div class="tlr-results-wrapper">
                <div class="tlr-response"></div>
            </div>
			<div class="tlr-form-wrapper">
                <?php
				if ( ! empty($title) || ! empty($desc) ) {
				    ?>
					<div class="tlr-header">
                    <?php
					if ( ! empty($title) ) {
					    ?>
						<h3 class='tlr-nl-form-title'><?= esc_html($title); ?></h3>
                        <?php
					}

					if ( ! empty($desc) ) {
					    ?>
						<span class='tlr-nl-desc'><?= esc_html($desc); ?></span>
                        <?php
					}
					?>
					</div>
                    <?php
				} ?>
				<div class="tlr-fields-wrapper" style="display: none;">
                    <div>
	                    <?php
	                    foreach( (array) $fields as $field_slug => $fields_data ) {

		                    if ( !isset($fields_data['enabled']) || $fields_data['enabled'] != 'yes' ) {
		                        continue;
		                    }

		                    $asterisk = $fields_data['required'] ? ' *' : '';
		                    $required_data = $fields_data['required'] ? " data-tlr-required='1'" : '';

		                    switch ($field_slug) {

			                    case 'mobile' : ?>
                                    <div class='tlr-input-wrap tlr-mobile-wrap'>
                                    <input type='text' class='tlr-input tlr-mobile-field' data-tlr-required='1' placeholder='<?= esc_attr($fields_data['title'] . $asterisk) ?>' aria-label="<?= esc_attr($fields_data['title'] . $asterisk) ?>">
                                    </div><?php
				                    break;

			                    case 'title' : ?>
                                    <div class='tlr-input-wrap tlr-title-wrap'>
                                    <select id='tlr_title' class='tlr-input tlr-title-field'<?= $required_data; ?> aria-label="<?= esc_attr($fields_data['title'] . $asterisk) ?>">
                                        <option selected value=''><?= esc_attr($fields_data['title'] . $asterisk) ?></option>
                                        <option value='mr'><?= esc_html__('Mr', 'texteller'); ?></option>
                                        <option value='mrs'><?= esc_html__('Mrs', 'texteller'); ?></option>
                                        <option value='miss'><?= esc_html__('Miss', 'texteller'); ?></option>
                                        <option value='ms'><?= esc_html__('Ms', 'texteller'); ?></option>
                                    </select>
                                    </div><?php
				                    break;

			                    case 'member_group':
				                    $member_groups = TLR\tlr_get_public_member_groups();
				                    ?>
                                    <div class='tlr-input-wrap tlr-member-group-wrap'>
                                    <select id='tlr_member_group' class='tlr-input tlr-member-group-field'<?= $required_data; ?> aria-label="<?= esc_attr($fields_data['title'] . $asterisk) ?>">
                                        <option selected value=''><?= esc_attr($fields_data['title'] . $asterisk) ?></option><?php
					                    foreach ($member_groups as $slug => $name) { ?>
                                            <option  value='<?= esc_attr($slug); ?>'><?= esc_html($name); ?></option><?php
					                    } ?>
                                    </select>
                                    </div><?php
				                    break;
			                    default:
				                    $type = 'email' === $field_slug ? 'email' : 'text';
				                    $wrap_class = str_replace('_', '-', $field_slug); ?>
                                    <div class='tlr-input-wrap tlr-<?= esc_attr($wrap_class) ?>-wrap'><?php
				                    $field_class = 'tlr-' . str_replace('_', '-', $field_slug) . '-field'; ?>
                                    <input type='<?= $type; ?>' class='tlr-input <?= esc_attr($field_class) ?>' maxlength='255'<?= $required_data ?> placeholder='<?= esc_attr($fields_data['title'] . $asterisk) ?>' aria-label="<?= esc_attr($fields_data['title'] . $asterisk) ?>">
                                    </div><?php
		                    }
	                    }
	                    ?>
                    </div>
                    <div class="tlr-submit-wrap"><?php
						wp_nonce_field('tlr-nl-new-member', 'tlr-registration-check', false,true )
						?><button class="tlr-submit tlr-submit-registration" type="button"><?= esc_html($submit_text); ?></button>
                    </div>
                </div>
			</div>
        </form><?php
		return ob_get_clean();
	}

	public static function register_assets()
	{
		wp_register_script( 'tlr-newsletter',
			TLR_ASSETS_URI . '/newsletter/tlr-newsletter.js',
			[ 'jquery', 'tlr-intl-tel-input' ],
            null,
            true
        );

		$upload_dir = wp_upload_dir();
		if ( file_exists(trailingslashit($upload_dir['basedir']).'texteller/newsletter/tlr-newsletter.css') ) {
			wp_register_style(
			        'tlr-newsletter',
                    trailingslashit( $upload_dir['baseurl'] ) . 'texteller/newsletter/tlr-newsletter.css'
            );
		}
	}

	protected static function enqueue_form_assets()
	{
		wp_enqueue_style( 'tlr-intl-tel-input' );
		wp_enqueue_script( 'tlr-intl-tel-input' );

		wp_enqueue_style('tlr-newsletter');
		wp_enqueue_script('tlr-newsletter');

		$intl_tel_input_options = get_option('tlr_intl_tel_input_options',[
		        'initial_country'       =>  'US',
                'country_dropdown'      =>  'yes',
                'preferred_countries'   =>  ['US', 'IN', 'GB']
        ]);

		wp_localize_script( 'tlr-newsletter', 'tlrNewsletterData', [
		        'intlTelOptions'        =>  [
			        'utilsURL'              =>  TLR_LIBS_URI . '/intl-tel-input/build/js/utils.js',
			        'preferredCountries'    =>  $intl_tel_input_options['preferred_countries'],
			        'initialCountry'        =>  $intl_tel_input_options['initial_country'],
			        'allowDropdown'         =>  $intl_tel_input_options['country_dropdown']
                ],
                'submitWorkingLabel'    =>  __( 'Please wait', 'texteller' ),
                'ajaxURL'               =>  admin_url( 'admin-ajax.php' ),
		        'errorText'             =>  __( 'An error occurred please try again.', 'texteller' ),
		        'retryText'             =>  __( 'Retry', 'texteller' ),
                'doneText'              =>  __('Done', 'texteller')
		] );
	}
}