<?php

namespace Texteller\Admin\Meta_Boxes;
use Texteller as TLR;

defined( 'ABSPATH' ) || exit;

class Member_Data
{
    private function __construct() {}

    private static function render_user_selector( TLR\Registration_Module $register_member )
    {
	    $args = [
		    'show_option_none'  => __('None', 'texteller'),
		    'echo'              => true,
		    'id'                => 'tlr_link_user',
		    'name'              => 'tlr_link_user'
	    ];

	    $user_id = $register_member->get_form_data('user_id' );

	    if ( $user_id > 0 ) {
		    $args['selected'] = $user_id;
        }
	    ?>
        <p>
		    <?php wp_dropdown_users( $args ); ?>
        </p>
        <?php
    }

    private static function render_member_basic_info_column( TLR\Registration_Module $register_member )
    {
	    ?>
        <div class="member-data-column member-base">
            <h3><?= __('Basic Information', 'texteller')?></h3>
            <p>
                <label for="mobile_input"><?= __('Mobile Number', 'texteller')?></label>
                <input type='text' id="mobile_input" name='mobile_input' class='tlr-data-fields tlr-ltr-field mobile-input' value="<?= $register_member->get_form_data('mobile' ); ?>">
            </p>

            <?php
            self::render_user_selector( $register_member );
            ?>
            <p>
                <label for="tlr_email"><?= __('Email', 'texteller')?></label>
                <input type="text" id="tlr_email" name="tlr_email" class="tlr-data-fields tlr-ltr-field" value="<?php echo $register_member->get_form_data('email' ); ?>">
            </p>
        </div>
	    <?php
    }

	/**
	 * Build TLR_Member field meta box
	 *
     * @param TLR\Registration_Module $register_member
	 */
	public static function render( TLR\Registration_Module $register_member )
	{
		?>
        <div class='tlr-panel-wrap texteller'>
            <div class="member-data-column-container">
                <?php
                self::render_member_basic_info_column( $register_member );
                ?>
                <div class="member-data-column member-info">
                    <h3><?= __('Personal Info', 'texteller')?></h3>
                    <p>
                        <label for="tlr_first_name"><?= __('First Name', 'texteller')?></label>
                        <input type="text" id="tlr_first_name" name="tlr_first_name" class="tlr-data-fields" value="<?php echo $register_member->get_form_data('first_name' ); ?>">
                    </p>
                    <p>
                        <label for="tlr_last_name"><?= __('Last Name', 'texteller')?></label>
                        <input type="text" id="tlr_last_name" name="tlr_last_name" class="tlr-data-fields" value="<?php echo $register_member->get_form_data('last_name' ); ?>">
                    </p>

                    <p>
                        <?php
                        $value = $register_member->get_form_data('title' );
                        ?>
                        <label for="tlr_title"><?php _e('Title','texteller'); ?></label>
                        <select id='tlr_title' name='tlr_title' class='tlr-data-fields tlr-ltr-field'>
                            <option <?= empty($value) ? 'selected' : ''; ?> value=''><?=__('Title', 'texteller');?></option>
                            <option <?php selected( 'mr', $value ); ?> value='mr'><?= __('Mr', 'texteller');?></option>
                            <option <?php selected( 'mrs', $value ); ?> value='mrs'><?= __('Mrs', 'texteller');?></option>
                            <option <?php selected( 'miss', $value ); ?> value='miss'><?= __('Miss', 'texteller');?></option>
                            <option <?php selected( 'ms', $value ); ?> value='ms'><?= __('Ms', 'texteller');?></option>
                        </select>
                    </p>

                </div>
            </div>
        </div>
		<?php
	}

	/*public static function save( TLR_Member $member, bool $update, array $errors ): array
    {
        $fields = ['mobile', 'user_id', 'first_name', 'last_name', 'email'];

        foreach ($fields as $field) {
            $field_value = isset($_POST['tlr_' . $field]) ? $_POST['tlr_' . $field] : '';
	        $field_value = tlr_sanitize_input($field, $field_value);

	        switch ($field) {
                case 'mobile' :
	                if ($update) {
		                if ( isset($_POST['tlr_mobile']) && !empty($field_value) ) {
			                $new_mobile = $field_value;
			                $old_mobile = $member->get_mobile();

			                if ( $old_mobile != $new_mobile ) {
				                if ( !tlr_is_mobile_valid($new_mobile) ) {
					                $errors[] = 101;
				                } else {
					                $possible_post_id = tlr_get_post_id($new_mobile, 'mobile');
					                if ( $possible_post_id > 0 ) {
						                $errors[] = 102;
					                } else {
						                //the entered mobile number is valid and available
						                $member->update_mobile($new_mobile);
					                }
				                }
			                }
		                }
	                } else {
		                if ( empty($field_value) || !tlr_is_mobile_valid($field_value) ) {
			                // We're inserting new member but we don't have a valid mobile number
			                $errors[] = 101;
		                } else {
			                // Inserting new member
			                $possible_post_id = tlr_get_post_id($field_value, 'mobile');
			                if ( $possible_post_id > 0 ) {
				                $errors[] = 102;
			                } else {
				                //the entered mobile number is valid and available
				                $member->set_mobile($field_value);
			                }
                        }
                    }
                    break;

                case 'email' :
	                if ($update) {
	                    if ( !empty($field_value) && !is_email($field_value) ) {
		                    $errors[] = 103;
                        } else {
	                        $new_email = $field_value;
	                        $old_email = $member->get_email();
	                        if( $new_email != $old_email ) {
		                        $member->set_email($field_value);
		                        $member->save_meta_data('email');
                            }
                        }
	                } else {
		                if ( !empty($field_value) && !is_email($field_value) ) {
			                $errors[] = 103;
		                } else {
			                $member->set_email($field_value);
		                }
                    }
                    break;

                case 'user_id':
	                if ($update) {
		                $new_user_id = (int) $field_value;
		                $old_user_id = $member->get_user_id();
		                if ( $new_user_id > 0 && $old_user_id != $new_user_id ) {
                            $link_user = $member->member_relationship->link_user($new_user_id);
                            if ( $link_user === null ) {
                                $errors[] = 104;
                            } elseif ( $link_user === false ) {
                                $errors[] = 105;
                            } elseif( $link_user === true ) {
                                $member->set_user_id($new_user_id);
                                $member->save_meta_data('user_id');
	                            $member->member_relationship->delete_usermeta($old_user_id);
	                            $member->append_member_tag('tlr-user-linked');
	                            $member->save_member_tags();
                            }
		                } elseif ( !$new_user_id ) {
			                $unlink_user = $member->member_relationship->unlink_user();
			                if ($unlink_user) {
			                    $member->set_user_id(0);
			                    $member->save_meta_data('user_id');
			                    $member->remove_member_tag('tlr-user-linked');
			                    $member->save_member_tags();
                            }
                        }
	                } else {
	                    $user_id = (int) $field_value;
		                $possible_post_id = tlr_get_post_id( $user_id, 'user_id' );
		                if ($possible_post_id > 0) {
			                $errors[] = 104;
		                } else {
		                    $member->member_relationship->set_user_id($user_id);
		                    $member->set_user_id($user_id);
		                }
                    }
                    break;

                default:
	                if ($update) {
	                    $get_method = "get_$field";
	                    $set_method = "set_$field";
	                    if ( method_exists($member, $get_method) ) {
	                        $old_value = $member->$get_method();
	                        if ($field_value != $old_value && method_exists($member, $set_method)) {
	                            $member->$set_method($field_value);
	                            $member->save_meta_data($field);
                            }
                        }
	                } else {
	                    $set_method = "set_$field";
		                if ( method_exists($member, $set_method) ) {
		                    $member->$set_method($field_value);
		                }
                    }
            }
        }

	    return $errors;
    }*/
}