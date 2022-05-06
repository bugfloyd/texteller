<?php

namespace Texteller\Core_Modules\WordPress;
use Texteller as TLR;

defined( 'ABSPATH' ) || exit;

final class Registration {

	public static $reg_origin = '';

	/**
	 * @var \WP_Error
	 */
	private static $errors = null;

	private static $is_enabled = null;

	private function __construct() {}

	public static function init()
	{
		add_action( 'register_form', [ self::class, 'render_wp_login' ] );
		add_action( 'show_user_profile', [ self::class, 'render_profile' ] );
		add_action( 'user_new_form', [ self::class, 'render_dashboard_new_user' ] );
		add_action( 'edit_user_profile', [ self::class, 'render_dashboard_edit_user'] );

		add_action( 'login_form_register', [ self::class, 'init_frontend_registration' ] );
		add_action( 'user_profile_update_errors', [ self::class, 'init_dashboard_registration'], 10, 3 );

		add_filter( 'registration_errors', [ self::class, 'frontend_registration_validation'], 99 );

		add_action( 'register_new_user', [ self::class, 'member_register' ] );
		add_action( 'edit_user_created_user', [ self::class, 'member_register' ] );
		add_action( 'profile_update', [ self::class, 'profile_member_register'] );

		add_filter( 'wp_new_user_notification_email', [ self::class, 'control_new_user_email_notification' ] );

		add_action( 'login_form_login', [ self::class, 'init_login' ] );
		add_filter( 'wp_login_errors', [ self::class, 'override_messages' ], 99 );

        add_action( 'login_form_lostpassword', [ self::class, 'init_lost_password' ] );
        add_action( 'lostpassword_form', [ self::class, 'lost_password_user_choice' ] );
        add_action( 'lost_password', [ self::class, 'lost_password_errors' ] );
        add_filter( 'retrieve_password_message', [ self::class, 'control_lost_password_notification' ], 10, 4 );

		add_action( 'login_enqueue_scripts', [ self::class, 'enqueue_wp_form_assets' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_wp_form_assets' ] );
	}

	public static function init_frontend_registration()
    {
	    if ( ! self::is_registration_enabled() ) {
		    return;
	    }

	    add_filter( 'gettext', function( $translation, $text )
	    {
		    if( 'Registration confirmation will be emailed to you.' === $text ) {
			    $registration_rp = get_option('tlr_wp_registration_new_user_rp_message', 'both');
			    if( 'both' === $registration_rp ) {
				    $translation = __( 'Registration confirmation will be sent to you by email and text message.', 'texteller' );
			    }elseif( 'texteller' === $registration_rp ) {
				    $translation = __( 'Registration confirmation will be sent to you by text message.', 'texteller' );
			    }
		    }
		    return $translation;
	    }, 10, 2 );

	    if( empty( $_POST ) || !isset( $_POST['wp-submit'] ) ) {
		    return;
	    }

        $_POST['user_email'] = isset( $_POST['tlr_email'] ) ? $_POST['tlr_email'] : '';
	    $registration_module = TLR\Registration_Module::get_instance();
	    self::$errors = new \WP_Error();

	    $fields = TLR\get_form_fields( 'tlr_wp_registration_form_fields', Options::class );

	    $registration_module->init( $fields, self::$errors );

	    self::$reg_origin = 'wp-login';

	    // Maybe generate username
	    $username_behaviour = get_option( 'tlr_wp_registration_frontend_username', 'wp_default' );
	    if( 'wp_default' === $username_behaviour ) {
		    return;
	    }
	    if( 'hide' === $username_behaviour
            || ( 'optional' === $username_behaviour && empty( sanitize_user($_POST['user_login']) ) )
        ) {
		    $_POST['user_login'] = $registration_module->generate_username( '', 'tlr_wp_registration_username_generator' );
	    }
    }

	/**
	 * @param \WP_Error $errors
	 *
	 * @return \WP_Error
	 */
	public static function frontend_registration_validation( $errors )
	{
		if ( ! self::is_registration_enabled() ) {
			return $errors;
		}

	    if ( ! is_null( self::$errors ) ) {
		    $errors->errors = array_merge( $errors->errors, self::$errors->errors );
		    $errors->error_data = array_merge( $errors->error_data, self::$errors->error_data );

		    if( ( $errors->get_error_message('tlr_error_exists_mobile')
                  || $errors->get_error_message('tlr_error_exists_email') )
                && $errors->get_error_message('username_exists')
            ) {
		        $errors->remove( 'username_exists' );
            }
        }

		// Remove TLR invalid email error. (WP validates the given email and notices the user)
		if( !empty( $errors->get_error_message('tlr_error_invalid_email') ) ) {
			$errors->remove('tlr_error_invalid_email');
		}

		// Check for empty email is done in the validation process
		if( !empty( $errors->get_error_message('empty_email') ) ) {
			$member_fields = TLR\get_form_fields('tlr_wp_registration_form_fields',Options::class);

			if( ( !isset( $member_fields['email']['enabled'] ) || 'yes' !== $member_fields['email']['enabled'] )
			    || ( !isset( $member_fields['email']['required'] ) || 1 != $member_fields['email']['required'] )
			) {
				$errors->remove('empty_email' );
			}
		}

		return $errors;
	}

	/**
	 * @param $errors \WP_Error
	 * @param $update
     * @param $user \WP_User
	 */
	public static function init_dashboard_registration( &$errors, $update, &$user )
    {
	    if ( ! self::is_registration_enabled() ) {
		    return;
	    }

        if( ( !isset( $_POST['tlr_register_member'] ) || 'yes' !== $_POST['tlr_register_member'] ) && ( !defined( 'IS_PROFILE_PAGE' ) || true !== IS_PROFILE_PAGE )   ) {
            return;
        }

        $member_id = false === $update ? 0 : ( isset($user->ID) ? TLR\tlr_get_member_id( $user->ID, 'user_id' ) : 0 );

	    $registration_module = TLR\Registration_Module::get_instance( $member_id );

	    if( defined( 'IS_PROFILE_PAGE' ) && true === IS_PROFILE_PAGE ) {
		    $fields = TLR\get_form_fields( 'tlr_wp_registration_form_fields', Options::class );

        } else {
	        // Fields to be used for member registration on add/edit user screens.
            // We can't use default fields here (since some fields may be disabled on default frontend fields)
		    $fields = [
			    'mobile'    =>  [
				    'enabled'   =>  'yes',
				    'id'        =>  'mobile',
				    'title'     =>  __('Mobile Number', 'texteller'),
				    'required'  =>  0
			    ],
			    'email'     =>  [
				    'enabled'   =>  'yes',
				    'id'        =>  'email',
				    'title'     =>  __('Email', 'texteller'),
				    'required'  =>  0
			    ],
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
			    'title'    =>  [
				    'enabled'   =>  'yes',
				    'id'        =>  'title',
				    'title'     =>  __('Title', 'texteller'),
				    'required'  =>  0
			    ],
			    'member_group'   =>  [
				    'enabled'   =>  'yes',
				    'id'        =>  'member_group',
				    'title'     =>  __('Member Group', 'texteller'),
				    'required'  =>  0
			    ],
			    'description'   =>  [
				    'enabled'   =>  'yes',
				    'id'        =>  'description',
				    'title'     =>  __('Member Description', 'texteller'),
				    'required'  =>  0
			    ]
		    ];
        }

	    $ignore_list = ( $member_id && 'yes' !== get_option( 'tlr_wp_profile_mobile_update','yes' ) ) ? [ 'mobile' ] : [];

	    // If mobile number is empty, allow the customer to edit it, even if mobile number update is disabled.
	    if( empty( $registration_module->get_member_field_value('mobile') )
	        && $member_id && 'yes' !== get_option( 'tlr_wp_profile_mobile_update', 'yes' )
	    ) {
		    $ignore_list = [];
	    }

        $registration_module->init(
	        $fields,
            $errors,
	        $ignore_list,
            'user_reg',
            ['email' => 'email', 'first_name' => 'first_name', 'last_name' => 'last_name']
        );

        if( defined( 'IS_PROFILE_PAGE' ) && true === IS_PROFILE_PAGE ) {
            self::$reg_origin = 'wp-profile';
        } elseif( isset($user->ID) && $user->ID > 0 ) {
            $registration_module->set_member_field_value('user_id', $user->ID );
            self::$reg_origin = 'wp-dashboard-edit-user';
        } else {
            self::$reg_origin = 'wp-dashboard-new-user';
        }

        if( !empty( $errors->get_error_message( 'empty_email' ) ) ) {
            $errors->remove('empty_email' );
        }

    }

	public static function member_register( $user_id )
	{
		if ( ! self::is_registration_enabled() ) {
			return;
		}

		// to prevent triggering the registration from wp_insert_user() function, which is called during RP generation etc.
		if( !in_array( self::$reg_origin, [ 'wp-login', 'wp-dashboard-new-user', 'wp-dashboard-edit-user', 'wp-profile' ] ) ) {
		    return;
        }

		if( 'wp-profile' !== self::$reg_origin ) {
			remove_action( 'profile_update', [ self::class, 'profile_member_register' ], 10 );
		}

		$registration_module = TLR\Registration_Module::get_instance();
		$member_id = $registration_module->get_member_field_value('id' );

		if ( $member_id && in_array( self::$reg_origin, ['wp-dashboard-edit-user', 'wp-profile'], true ) ) {
		    $registration_module->save_member();
		} else {
			$member_id = $registration_module->register_new_user_member( $user_id, self::$reg_origin );
			if ( $member_id > 0 ) {
				if ( 'wp-login' === self::$reg_origin && 'yes' === get_option( 'tlr_wp_registration_frontend_update_names', 'yes' ) ) {

					self::user_update_names( $registration_module->get_member(), $user_id );
				}
				do_action( 'tlr_wp_member_registered', $registration_module->get_member(), self::$reg_origin );
			}
        }
	}

	public static function profile_member_register( $user_id )
    {
	    if ( ! self::is_registration_enabled() ) {
		    return;
	    }

		if( 'wp-profile' === self::$reg_origin ) {
			remove_action( 'profile_update', [ self::class, 'profile_member_register' ], 10 );
			self::member_register($user_id);
		}
	}

	private static function user_update_names( TLR\Member $member, $user_id )
    {
        $user = get_userdata( $user_id );

        if ( $user && !empty($user->ID) ) {

	        $first_name = $member->get_first_name();
	        $last_name = $member->get_last_name();

	        if ( ! empty( $first_name ) && empty( $user->first_name ) ) {
		        $user->first_name = $first_name;
	        }
	        if ( ! empty( $last_name ) && '' ===  $user->last_name ) {
		        $user->last_name = $last_name;
	        }

            $names = array_filter([ $first_name,$last_name ]);
            $user->display_name = implode( ' ', $names );

            $user_id = wp_update_user( $user );

            if( is_wp_error( $user_id ) ) {
	            TLR\tlr_write_log('TLR Error: An error occurred while trying to update the WP user data for saving first and last names.' );
            }
        }
    }

	/**
	 * @param $errors \WP_Error
	 *
	 * @return mixed
	 */
	public static function override_messages( $errors )
	{
		if ( ! self::is_registration_enabled() ) {
			return $errors;
		}

		if( $errors->get_error_message('confirm' ) ) {
			$by = get_option( 'tlr_wp_lost_password_base_gateway', 'both' );
			if( 'email' !== $by ) {
				$errors->remove( 'confirm' );
				if( 'texteller' === $by ) {
					$message = __( 'Check your phone for the confirmation link.', 'texteller' );
				} else{
					$message = __( 'Check your your email or message inbox for the confirmation link.', 'texteller' );
				}
				$errors->add('confirm', $message, 'message' );
			}
		}
		if( $errors->get_error_message('registered' ) ) {
			$by = get_option( 'tlr_wp_registration_new_user_rp_message', 'both' );
			if ( $by !== 'email' ) {
				$errors->remove( 'registered' );
				if( 'texteller' === $by ) {
					$message = __( 'Registration complete. Please check your phone.', 'texteller' );
				} else{
					$message = __( 'Registration complete. Please check your email or message inbox.', 'texteller' );
				}
				$errors->add('confirm', $message, 'message' );
			}
		}

		return $errors;
	}

	public static function control_new_user_email_notification( $wp_new_user_notification_email )
	{
		if ( ! self::is_registration_enabled() ) {
			return $wp_new_user_notification_email;
		}

		if( 'wp-login' !== self::$reg_origin ) {
			return $wp_new_user_notification_email;
		}

		$by = get_option( 'tlr_wp_registration_new_user_rp_message', 'both' );

		if ( $by === 'both' || $by === 'email' ) {
			return $wp_new_user_notification_email;
		} else {

			$registration_module = TLR\Registration_Module::get_instance();
			if ( !empty( $registration_module->get_member_field_value( 'mobile' ) ) ) {
				return [
					'to'        =>  '',
					'subject'   =>  '',
					'message'   =>  '',
					'headers'   =>  ''
				];
			}

			return $wp_new_user_notification_email;
		}
	}

	public static function init_login()
    {
	    if ( ! self::is_registration_enabled() ) {
		    return;
	    }

	    add_filter( 'gettext', function( $translation, $text )
	    {
		    if( 'Username or Email Address' === $text ) {
			    $translation = __( 'Username, Email Address or Mobile Number', 'texteller' );
		    }
		    return $translation;
	    }, 10, 2 );

        if( !empty( $_POST['log'] ) ) {
	        $possible_user =  TLR\tlr_get_possible_user(
		        $_POST['log'],
		        get_option( 'tlr_frontend_default_countries', ['US'] )
	        );
	        if( $possible_user ) {
		        $_POST['log'] = $possible_user->user_login;
	        }
        }
    }

	public static function render_dashboard_new_user( $type )
	{
		if ( ! self::is_registration_enabled() ) {
			return;
		}

		if( 'add-new-user' !== $type ) {
			return;
		}

		self::render_dashboard_user();
	}

	public static function render_dashboard_edit_user( $user )
	{
		if ( ! self::is_registration_enabled() ) {
			return;
		}

	    $member_id =  isset( $user->ID ) ? TLR\tlr_get_member_id( $user->ID, 'user_id' ) : 0;
	    $member = $member_id ? new TLR\Member( $member_id ) : null;
		self::render_dashboard_user( $member );
	}

	/**
	 * @param TLR\Member|null $member
	 */
	public static function render_dashboard_user( $member = null )
	{
		?>
        <style>.tlr-wp-input{width:25em}</style>
        <table class="form-table">
            <tr class="member-data-wrap">
                <th scope="row">
                    <label>
						<?php _e( 'Texteller Membership' ); ?>
                    </label>
                </th>
                <td>
                    <input type='text' hidden id='tlr-member-id' name='tlr_member_id' value='<?= !is_null($member) ? $member->get_id() : ''; ?>' aria-label="Hidden member ID">
                    <button type="button" class="button tlr-register-member hide-if-no-js"><?php is_null($member) ? _e( 'Add new member', 'texteller' ) : _e( 'Edit member', 'texteller' ); ?></button>
                    <div class="tlr-register hide-if-js">
                        <div style="display: inline-block;">
                            <input type='text' hidden id='tlr_register_member' name='tlr_register_member' value='yes' aria-label="Hidden registration data">
                            <p>
                                <label for='tlr_national_mobile'><?php esc_html_e('Mobile number', 'texteller'); ?>
                                    <input type='text' id='tlr_national_mobile' name='tlr_national_mobile' class='tlr-mobile-field tlr-wp-input' value='<?= TLR\tlr_get_posted_field_value('mobile', $member ); ?>' data-hidden-input-name="tlr_mobile">
                                </label>
                            </p>
                            <p>
                                <label for="tlr_title"><?php esc_html_e('Title','texteller'); ?></label><br>
                                <select id='tlr_title' name='tlr_title' class='tlr-wp-input'><?php
									$posted_title = TLR\tlr_get_posted_field_value('title', $member );
									?>
                                    <option <?= empty( TLR\tlr_get_posted_field_value('title', $member ) ) ? 'selected' : ''; ?>value=''><?= esc_html__('Select one', 'texteller');?></option>
                                    <option <?php selected( 'mr', $posted_title ); ?> value='mr'><?= esc_html__('Mr', 'texteller');?></option>
                                    <option <?php selected( 'mrs', $posted_title ); ?> value='mrs'><?= esc_html__('Mrs', 'texteller');?></option>
                                    <option <?php selected( 'miss', $posted_title ); ?> value='miss'><?= esc_html__('Miss', 'texteller');?></option>
                                    <option <?php selected( 'ms', $posted_title ); ?> value='ms'><?= esc_html__('Ms', 'texteller');?></option>
                                </select>
                            </p>

							<?php

							?>
                            <p>
                                <label for="tlr_member_group"><?php esc_html_e('Member group','texteller'); ?></label><br>
                                <select id='tlr_member_group' name='tlr_member_group' class='tlr-wp-input'>
									<?php $posted_group = TLR\tlr_get_posted_field_value('member_group', $member ); ?>
                                    <option <?= empty($posted_group) ? 'selected="selected" ' : ''; ?> value=''><?php esc_html_e('Select one', 'texteller'); ?></option>
									<?php
									$posted_group = is_array( $posted_group ) ? !empty( $posted_group ) ? $posted_group[0] : '' : $posted_group;
									$member_group = TLR\tlr_get_member_groups();

									foreach ($member_group as $slug => $name) {
										$selected = selected( $slug , $posted_group );
										$name = esc_html($name);
										$slug = esc_attr($slug);
										echo "<option$selected value='$slug'>$name</option>";
									}
									?>
                                </select>
                            </p>
                            <p>
                                <label for="tlr-description"><?php esc_html_e('Description','texteller'); ?></label><br>
                                <textarea id="tlr-description" name="tlr_description" class='tlr-wp-input'><?= TLR\tlr_get_posted_field_value('description', $member ); ?></textarea>
                            </p>
                            <p>
                                <strong><?php esc_html_e( 'Note', 'texteller' ); ?>: </strong><?= sprintf(
		                            esc_html__( 'Please check the %s options page to configure set-password and welcome messages.', 'texteller' ),
		                            '<a href="' . admin_url( 'admin.php?page=tlr-options&tab=tlr_wordpress&section=wp_registration_notifications' ).'">'
		                            . esc_html__( 'Registration & Login Notifications', 'texteller' ) . '</a>' ); ?>
                            </p>
                        </div>
                        <button type="button" style="vertical-align: baseline;" class="button tlr-cancel-reg hide-if-no-js" data-toggle="0" aria-label="<?php esc_attr_e( 'Cancel member registration', 'texteller' ); ?>">
                            <span class="dashicons dashicons-no" style="vertical-align: middle;" aria-hidden="true"></span>
                            <span class="text"><?php esc_html_e( 'Cancel' ); ?></span>
                        </button>
                    </div>
                </td>
            </tr>
        </table>
		<?php
	}

	public static function render_profile( $user )
    {
	    if ( ! self::is_registration_enabled() ) {
		    return;
	    }

	    $fields = TLR\get_form_fields( 'tlr_wp_registration_form_fields',Options::class );

	    $member_id = TLR\tlr_get_member_id( $user->ID, 'user_id' );
	    $member = $member_id ? new TLR\Member( $member_id ) : null;
	    ?>
        <style> .tlr-wp-input {width:25em;}</style>
        <table class="form-table">
        <?php
	    foreach( $fields as $field_slug => $fields_data ) {

		    if ( !isset($fields_data['enabled']) || $fields_data['enabled'] != 'yes' ) {
			    continue;
		    }

	        if( 'email' === $field_slug ) {
	            if( !isset($data['enabled']) || 'yes' !== $fields_data['enabled'] || !isset($data['required']) || 1 != $fields_data['required'] ) {
	                ?>
            <script>
                (function($) {
                    $(document).ready( function() {
                        $('.user-email-wrap label .description').hide();
                    });
                })(jQuery);
            </script>
            <style>.user-email-wrap label .description{display:none!important;}</style><?php
                }
            }
	        if( in_array( $field_slug, [ 'email', 'first_name', 'last_name' ], true ) ) {
	            continue;
            }
	        ?>
            <tr class="member-<?= esc_attr($field_slug); ?>-wrap">
                <th>
                    <label for='tlr_<?=esc_attr($field_slug)?>'><?= esc_html($fields_data['title']); ?></label>
                </th>
                <td>
                    <?php
                    switch ( $field_slug ) {
	                    case 'mobile' : ?>
                            <p>
                                <?php
                                if ( $member_id > 0 && !empty($member->get_mobile()) && 'yes' !== get_option('tlr_wp_profile_mobile_update','yes') ) : ?>
                                    <input type='text' id='tlr_readonly_mobile' disabled class='tlr-mobile-field' value='<?= $member->get_mobile(); ?>' aria-label="Hidden mobile field">
                                <?php else : ?>
                                    <input type='text' id='tlr_national_mobile' name='tlr_national_mobile' class='tlr-mobile-field' value='<?= TLR\tlr_get_posted_field_value('mobile', $member ); ?>' data-hidden-input-name="tlr_mobile">
                                <?php endif; ?>
                            </p>
		                    <?php
                            if ( $member_id > 0 && !empty($member->get_mobile()) && 'yes' === get_option('tlr_wp_profile_verify_number','yes') ) {
                                $number_verification = new TLR\Verify_Number();
                                $number_verification->set_member($member);
                                echo $number_verification->get_html();
                            }
		                    break;

	                    case 'title' : ?>
                            <p>
                                <select id='tlr_title' name='tlr_title' class='tlr-wp-input'><?php
				                    $posted_title = TLR\tlr_get_posted_field_value('title', $member );
				                    ?>
                                    <option <?= empty( TLR\tlr_get_posted_field_value('title', $member ) ) ? 'selected' : ''; ?>value=''><?php esc_html_e('Select one', 'texteller'); ?></option>
                                    <option <?php selected( 'mr', $posted_title ); ?> value='mr'><?php esc_html_e('Mr', 'texteller');?></option>
                                    <option <?php selected( 'mrs', $posted_title ); ?> value='mrs'><?php esc_html_e('Mrs', 'texteller');?></option>
                                    <option <?php selected( 'miss', $posted_title ); ?> value='miss'><?php esc_html_e('Miss', 'texteller');?></option>
                                    <option <?php selected( 'ms', $posted_title ); ?> value='ms'><?php esc_html_e('Ms', 'texteller');?></option>
                                </select>
                            </p>
		                    <?php
		                    break;

	                    case 'member_group':
		                    $member_group = TLR\tlr_get_public_member_groups();
		                    ?>
                            <p>
                                <select id='tlr_member_group' name='tlr_member_group' class='tlr-wp-input'>
				                    <?php $posted_group = TLR\tlr_get_posted_field_value('member_group', $member ); ?>
                                    <option <?= empty($posted_group) ? 'selected="selected" ' : ''; ?> value=''><?php esc_html_e('Select one', 'texteller'); ?></option>
				                    <?php
				                    $posted_group = is_array( $posted_group ) ? !empty( $posted_group ) ? $posted_group[0] : '' : $posted_group;
				                    foreach ($member_group as $slug => $name) {
					                    $selected = selected( $slug , $posted_group );
					                    $slug = esc_attr($slug);
					                    $name = esc_html($name);
					                    echo "<option$selected value='$slug'>$name</option>";
				                    } ?>
                                </select>
                            </p>
		                    <?php
		                    break;

	                    default: ?>
                            <p>
                                <input type='text' id='tlr_<?=esc_attr($field_slug)?>' name='tlr_<?=esc_attr($field_slug); ?>' value='<?= TLR\tlr_get_posted_field_value( $field_slug, $member ) ?>'>
                            </p>
	                    <?php
                    }
                    ?>
                </td>
            </tr>
            <?php
	    } ?>
        </table>
        <?php
    }

	public static function render_wp_login()
	{
		if ( ! self::is_registration_enabled() ) {
			return;
		}

		$fields = TLR\get_form_fields( 'tlr_wp_registration_form_fields', Options::class );
		$username_option = get_option( 'tlr_wp_registration_frontend_username', 'wp_default' );

		if ( 'hide' === $username_option ) {
			wp_enqueue_script('jquery');
			?>
            <style>#registerform > p:first-child{display:none!important;}</style>
            <script>jQuery(document).ready(function($){ $('#registerform > p:first-child').css('display', 'none');});</script>
			<?php
		}

		?>
		<style> .tlr-wp-input {width: 100%;min-height: 3em;margin: 2px 6px 16px 0;} #registerform > p:nth-child(2){display:none!important;}</style>
        <script>
            jQuery(document).ready(function($){
                $('#registerform > p:nth-child(2)').css('display', 'none');
            });
        </script
        <?php
		foreach( (array) $fields as $field_slug => $field_data ) {
			if ( !isset($field_data['enabled']) || $field_data['enabled'] != 'yes' ) {
				continue;
			}

			switch ($field_slug) {
				case 'mobile' : ?>
                    <p>
                        <label for='tlr_national_mobile'><?= esc_html($field_data['title']); ?>
                            <input type='text' id='tlr_national_mobile' name='tlr_national_mobile' class='tlr-mobile-field' value='<?= TLR\tlr_get_posted_field_value('mobile' ); ?>' data-hidden-input-name="tlr_mobile">
                        </label>
                    </p>
					<?php
					break;

				case 'email': // todo: use WP email?>
                    <p>
                        <label for='tlr_email'><?=esc_html( $field_data['title']); ?></label><br>
                        <input type="email" name="tlr_email" id="tlr_email" class="input" value="<?= TLR\tlr_get_posted_field_value('email' ); ?>" size="25">
                    </p>
					<?php
					break;

				case 'title' : ?>
                    <p>
                        <label for="tlr_title"><?= esc_html($field_data['title']); ?></label><br>
                        <select id='tlr_title' name='tlr_title' class='tlr-wp-input'><?php
							$posted_title = TLR\tlr_get_posted_field_value('title' );
							?>
                            <option <?= empty( TLR\tlr_get_posted_field_value('title' ) ) ? 'selected' : ''; ?>value=''><?php esc_html_e('Select one', 'texteller');?></option>
                            <option <?php selected( 'mr', $posted_title ); ?> value='mr'><?php esc_html_e('Mr', 'texteller');?></option>
                            <option <?php selected( 'mrs', $posted_title ); ?> value='mrs'><?php esc_html_e('Mrs', 'texteller');?></option>
                            <option <?php selected( 'miss', $posted_title ); ?> value='miss'><?php esc_html_e('Miss', 'texteller');?></option>
                            <option <?php selected( 'ms', $posted_title ); ?> value='ms'><?php esc_html_e('Ms', 'texteller');?></option>
                        </select>
                    </p>
					<?php
					break;

				case 'member_group':
					$member_group = TLR\tlr_get_public_member_groups();
					?>
                    <p>
                        <label for="tlr_member_group"><?= esc_html($field_data['title']); ?></label><br>
                        <select id='tlr_member_group' name='tlr_member_group' class='tlr-wp-input'>
							<?php $posted_group = TLR\tlr_get_posted_field_value('member_group' ); ?>
                            <option <?= empty($posted_group) ? 'selected="selected" ' : ''; ?> value=''><?php esc_html_e('Select one', 'texteller'); ?></option>
							<?php
							$posted_group = is_array( $posted_group ) ? !empty( $posted_group ) ? $posted_group[0] : '' : $posted_group;
							foreach ($member_group as $slug => $name) {
								$selected = selected( $slug , $posted_group );
								$slug = esc_attr($slug);
								$name = esc_html($name);
								echo "<option$selected value='$slug'>$name</option>";
							} ?>
                        </select>
                    </p>
					<?php
					break;

				default: ?>
                    <p>
                        <label for='tlr_<?=esc_attr($field_slug)?>'><?= esc_html($field_data['title']); ?></label>
                        <input type='text' id='tlr_<?=esc_attr($field_slug)?>' name='tlr_<?=esc_attr($field_slug); ?>' value='<?= TLR\tlr_get_posted_field_value( $field_slug ) ?>'>
                    </p>
				<?php
			}
		}

		if ( $desc = get_option('tlr_wp_registration_form_description') ) {
		    ?>
            <p class='tlr-description'><?= esc_html($desc); ?></p><br>
            <?php
		}
	}

	public static function init_lost_password()
    {
	    if ( ! self::is_registration_enabled() ) {
		    return;
	    }

	    add_filter( 'gettext', function( $translation, $text )
        {
            if( 'Username or Email Address' === $text ) {
	            $translation = __( 'Username, Email Address or Mobile Number', 'texteller' );
            }
	        return $translation;
        }, 10, 2 );

	    add_filter( 'login_message', function( $message )
        {
            if( '<p class="message">'
                . __( 'Please enter your username or email address. You will receive a link to create a new password via email.' )
                . '</p>' === $message
            ) {
	            $lost_pass = get_option('tlr_wp_lost_password_base_gateway', 'both');

	            if( 'both' === $lost_pass ) {
		            $message = __( 'Please enter your username or email address or mobile number. You will receive a link to create a new password via email and text message.', 'texteller' );
	            }elseif( 'texteller' === $lost_pass ) {
		            $message = __( 'Please enter your username or email address or mobile number. You will receive a link to create a new password via text message.', 'texteller' );
	            }elseif( 'user_choice' === $lost_pass ) {
		            $message = __( 'Please enter your username or email address or mobile number. You will receive a link to create a new password.', 'texteller' );
	            }else{
		            $message = __( 'Please enter your username or email address. You will receive a link to create a new password via email.' );
	            }
            }
            return '<p class="message">' . $message . '</p>';
        });

	    if( isset($_POST['user_login']) ) {
	        $possible_user =  TLR\tlr_get_possible_user(
	                $_POST['user_login'],
                    get_option( 'tlr_frontend_default_countries', ['US'] )
            );
		    if( $possible_user ) {
			    $_POST['user_login'] = $possible_user->user_login;
            }
        }
    }

    public static function lost_password_user_choice()
    {
	    if ( ! self::is_registration_enabled() ) {
		    return;
	    }

        if( 'user_choice' === get_option('tlr_wp_lost_password_base_gateway', 'both') ) {
            ?>
            <div class="tlr-rpw-user-choice">
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
            <?php
        }
    }

	/**
	 * @param \WP_Error $errors
	 */
	public static function lost_password_errors( $errors )
    {
	    if ( ! self::is_registration_enabled() ) {
		    return;
	    }

        if( $errors->get_error_message('empty_username') ) {
            $errors->remove('empty_username');
            $errors->add(
                    'empty_username',
	            '<strong>' . __('ERROR','texteller') . '</strong>: ' . __( 'Enter a username, email address or a mobile number.', 'texteller' )
            );
        }

	    if( $errors->get_error_message('invalidcombo') ) {
		    $errors->remove('invalidcombo');
		    $errors->add(
			    'invalidcombo',
			    '<strong>' . __('ERROR','texteller') . '</strong>: ' . __( 'There is no account with that username, email address or mobile number.', 'texteller' )
		    );
	    }
    }

    public static function control_lost_password_notification( $message, $key, $user_login, $user_data )
    {
	    if ( ! self::is_registration_enabled() ) {
		    return $message;
	    }

        $lost_password = get_option('tlr_wp_lost_password_base_gateway', 'both');

	    if( 'texteller' === $lost_password
            || ( 'user_choice' === $lost_password && isset($_POST['tlr_lost_pw_user_choice']) && 'message' === $_POST['tlr_lost_pw_user_choice'] )
        ) {
	        $message = '';
	    }

	    if( 'texteller' === $lost_password
            || 'both' === $lost_password
            || ( 'user_choice' === $lost_password && isset($_POST['tlr_lost_pw_user_choice']) && 'message' === $_POST['tlr_lost_pw_user_choice'] )
        ) {
		    do_action( 'tlr_wp_reset_password_notification', $user_data->ID, $user_login, $key );
        }

	    return $message;
    }

	public static function enqueue_wp_form_assets( $hook = '' )
	{
		if( ! in_array( $hook, [ 'user-new.php', 'profile.php', 'user-edit.php' ] ) && '' !== $hook ) {
		    return;
		}

		wp_enqueue_style('tlr-wp-registration', TLR_ASSETS_URI . '/wp-registration.css');

		// Register and enqueue mobile field assets
		TLR\Texteller::register_mobile_field_assets();
		wp_enqueue_style('tlr-intl-tel-input');
		wp_enqueue_script('tlr-intl-tel-input');
		wp_enqueue_script('tlr-mobile-field');
		$intl_tel_input_options = get_option('tlr_intl_tel_input_options',[
			'initial_country'       =>  'US',
			'country_dropdown'      =>  'yes',
			'preferred_countries'   =>  ['US', 'IN', 'GB']
		]);
        wp_localize_script( 'tlr-mobile-field', 'tlrFrontData', [
	        'initialCountry'       =>  $intl_tel_input_options['initial_country'],
	        'allowDropdown'        =>  $intl_tel_input_options['country_dropdown'],
	        'preferredCountries'   =>  $intl_tel_input_options['preferred_countries'],
	        'utilsURL'             =>  TLR_LIBS_URI . '/intl-tel-input/build/js/utils.js'
        ]);

        // Enqueues admin dashboard add/edit user screen script
		if ( 'user-new.php' === $hook || 'user-edit.php' === $hook ) {
			wp_enqueue_script( 'jquery-ui' );
			wp_enqueue_script(
				'tlr-user-edit',
				TLR_ASSETS_URI . '/admin/wp-user-edit.js',
				[],
				null,
				true
            );
		}
	}

	private static function is_registration_enabled()
	{
		if ( self::$is_enabled === null ) {
			self::$is_enabled = 'yes' === get_option( 'tlr_wp_is_registration_enabled', 'yes' );
		}
		return self::$is_enabled;
	}
}