<?php

namespace Texteller\Admin\Meta_Boxes;
use Texteller as TLR;

defined( 'ABSPATH' ) || exit;

class Member_Actions
{
    use TLR\Traits\DateTime;

	private function __construct() {}

	/**
	 * Build TLR_Member actions meta box
	 *
	 * @var TLR\Registration_Module $register_member;
	 */
	public static function render( $register_member )
	{
	    $member_actions = [];
	    $status = $register_member->get_member_field_value( 'status' );
		if ( 'verified' === $status ) {
			$member_actions['unverify'] = __( 'Unverify number', 'texteller' );
		} elseif( 'canceled' !== $status) {
			$member_actions['verify'] = __( 'Verify number', 'texteller' );
		}

		if ( 'canceled' !== $status ) {
			$member_actions['cancel'] = __( 'Cancel membership', 'texteller' );
		} else {
			$member_actions['uncancel'] = __( 'Revoke membership cancellation', 'texteller' );
		}

		self::init_datetime_formatter();

		$origins = apply_filters( 'tlr_registration_origins', [] );
		$reg_orig = $register_member->get_member_field_value('reg_origin');
		$reg_orig = isset($origins[$reg_orig]) ? $origins[$reg_orig] : ucfirst($reg_orig);

		if ( $register_member->get_member_field_value('id') > 0 ) {
			?>
            <p>
                <label for="tlr-registered-date"><?= __( 'Registered date', 'texteller' ) ?>:</label>
                <span id="tlr-registered-date" name="tlr_date"
                      class="tlr-data-fields tlr-ltr-field"><?= esc_html( self::format_datetime( $register_member->get_member_field_value('registered_date') ) ) ?></span>
            </p>
            <?php
			if( $register_member->get_member_field_value('registered_date')
                !== $register_member->get_member_field_value('modified_date')
            ) {
			    ?>
                <p>
                    <label for="tlr-modified-date"><?= __( 'Last modified date', 'texteller' ) ?>:</label>
                    <span id="tlr-modified-date" name="tlr_date"
                          class="tlr-data-fields tlr-ltr-field"><?= esc_html( self::format_datetime( $register_member->get_member_field_value('modified_date') ) ) ?></span>
                </p>
                <?php
            }
            ?>
            <p>
                <label for="tlr-reg-origin"><?= __( 'Registration Origin', 'texteller' ) ?></label>
                <span id="tlr-reg-origin" name="tlr_reg_origin"
                      class="tlr-data-fields"><?= esc_html( $reg_orig ) ?></span>
            </p>
            <p>
                <label for="tlr_status"><?= __( 'Status', 'texteller' ) ?></label>
                <span id="tlr_status" name="tlr_status"
                      class="tlr-data-fields tlr-status"><?= esc_html( ucfirst( $register_member->get_member_field_value('status') ) ) ?></span>
            </p>
            <?php
		}
		?>
        <div>
            <ul class="member_actions submitbox">
		        <?php
		        if ( $register_member->get_member_field_value('id') > 0 ) {
			        ?>
                    <li class="wide" id="actions">
                        <label for="tlr_member_action" hidden><?= __( 'Member actions', 'texteller' ); ?></label>
                        <select id="tlr_member_action" name="tlr_member_action">
                            <option value=""><?php esc_html_e( 'Choose an action...', 'texteller' ); ?></option>
					        <?php foreach ( $member_actions as $action => $title ) { ?>
                                <option value="<?= esc_attr( $action ) ?>"><?= esc_html( $title ); ?></option>
					        <?php } ?>
                        </select>
                    </li>
			        <?php
		        }
		        ?>
                <li class="tlr-wide">
	                <?php
	                if ( current_user_can( 'manage_options' ) && $register_member->get_member_field_value('id') > 0 ) {
                        echo '<div id="delete-action">';
                        echo sprintf(
                            '<a id="tlr-delete-member" href="%s" class="submitdelete tlr-delete-member-submit">%s</a>',
	                        esc_url( add_query_arg( ['action' => 'delete', 'member' => $register_member->get_member_field_value('id'), '_wpnonce' => wp_create_nonce('tlr-delete-single-member' ) ], admin_url('admin.php?page=tlr-members') ) ),
                            __( 'Delete Permanently' )
                        );
                        echo '</div>';
                        ?>
                        <script>
                            jQuery(document).ready(function ($) {
                                $('.tlr-delete-member-submit').on('click', function () {
                                    return confirm( '<?php
                                        esc_html_e('This member will be deleted permanently and could not be recovered. Are you sure?', 'texteller');
                                        ?>' );
                                });
                            });
                        </script>
                        <?php
	                }
	                ?>
			        <div>
				        <?php wp_nonce_field( 'tlr_member_manage_nonce', 'tlr_nonce'); ?>
                        <input type="hidden" name="tlr_action" value="tlr_save_member" />
                        <input type="submit" class="button save_member" name="save_member" value="<?= ! $register_member->get_member_field_value('id') ? 'Create' : 'Update'; ?>">
                    </div>
                    <br style="clear: both;">
                </li>
            </ul>
        </div>
		<?php
	}
}