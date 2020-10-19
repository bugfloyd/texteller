<?php

namespace Texteller\Admin\Templates;

defined( 'ABSPATH' ) || exit;

//check access
if (!function_exists('is_admin')) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit();
}
//$title = __( 'Send Message', 'texteller' );

//$taxonomies = ['member_group', 'tlr_member_reg_origin', 'tlr_member_tag'];


//$member_selector = '';
/*$args = [
	'show_option_none'  => __('None', 'texteller'),
	'echo'              => true,
	'id'                => 'tlr_link_user',
	'name'              => 'tlr_link_user'
];*/

$active_gateways        = (array) get_option( 'tlr_active_gateways', [] );
$members                = isset( $_REQUEST['member'] ) && is_array( $_REQUEST['member'] ) ? $_REQUEST['member'] : [];
$numbers                = isset( $_REQUEST['number'] ) && is_array( $_REQUEST['number'] ) ? $_REQUEST['number'] : [''];
$numbers_check = $numbers && '' !== $numbers[0] ? 1 : 0;
$members_check = $members ? 1 : 0;
$content = isset( $_REQUEST['content'] ) ? sanitize_text_field( $_REQUEST['content'] ) : '';

?>
<div class="wrap tlr-manual-send">
    <h1><?= __( 'Send Message', 'texteller' ); ?></h1>
    <form method="post" action="">
        <div class="tlr-section-wrap">
            <div class="tlr-section">
                <div class="tlr-section-label">
                    <label for="gateway" style="display: block; margin-top:5px;"><?= __('Gateway', 'texteller') ?></label>
                </div>
                <div class="tlr-section-content">
                    <select id="gateway">
                        <?php
                        if ( empty($active_gateways) ) {
                            ?>
                            <option disabled="disabled" selected="selected"><?= __( 'Please configure plugin gateways.', 'texteller' ); ?></option>
                            <?php
                        } else {
	                        foreach ( $active_gateways as $active_gateway ) {
                                ?>
                                <option value="<?= $active_gateway; ?>"><?= ucfirst($active_gateway); ?></option>
                                <?php
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="tlr-section">
                <div class="tlr-section-label" >
                    <span><?= __('Recipients', 'texteller') ?></span>
                </div>
                <div class="tlr-section-content">
                    <div class="tlr-recipient-type-wrap">
                        <div class="single-recipient-type-wrap">
                            <div class="tlr-recipient-type">
                                <input type="checkbox" id="select-members" class="has-content"<?php
		                        checked($members_check, '1');?> value="1">
                                <label for="select-members"><?= __( 'Manual Member Selection', 'texteller' ); ?></label>
                            </div>
                            <div class="recipient-type-content-wrap select-members-content">
                                <div style="display: grid;grid-template-columns: auto;">
                                    <label for="selected-members" class="recipient-field-label"><?= __( 'Search and select members', 'texteller' ) ?></label>
                                    <select id='selected-members' multiple="multiple" class="member-selector">
				                        <?php
				                        if ( !empty($members) ) {
					                        foreach ( $members as $member_id ) {
						                        $member = new \Texteller\Member( intval($member_id ) );
						                        if( $member->get_id() ) {
							                        ?>
                                                    <option value='<?= esc_attr( $member->get_id() ) ?>' selected='selected'>
                                                        <?= esc_html( $member->get_name() ) ?>
                                                    </option>
							                        <?php
						                        }
					                        }
				                        }
				                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="single-recipient-type-wrap">
                            <div class="tlr-recipient-type">
                                <input type="checkbox" id="site-staff" class="has-content" value="1">
                                <label for="site-staff"><?= __( 'Site Staff', 'texteller' ); ?></label>
                            </div>
                            <div class="recipient-type-content-wrap site-staff-content">
                                <div style="display: grid;grid-template-columns: auto;">
                                    <label for="selected-staff" class="recipient-field-label"><?= __( 'Select site staff', 'texteller' ) ?></label>
                                    <select id='selected-staff' multiple="multiple" class="staff-selector">
				                        <?php
				                        $staff = get_option('tlr_staff',[]);
				                        if ( !empty($staff) ) {
					                        foreach ( $staff as $member_id ) {
						                        $member = new \Texteller\Member( $member_id );
						                        if( $member->get_id() ) {
							                        ?>
                                                    <option selected='selected' value='<?= esc_attr ($member->get_id() ) ?>'>
                                                        <?= esc_html( $member->get_name() ) ?>
                                                    </option>
							                        <?php
						                        }
					                        }
				                        }
				                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="single-recipient-type-wrap">
                            <div class="tlr-recipient-type">
                                <input type="checkbox" id="filter-members" class="has-content" value="1">
                                <label for="filter-members"><?= __('Filter Members', 'texteller'); ?></label>
                            </div>
                            <div class="recipient-type-content-wrap filter-members-content">
                                <div class="send-filters-wrap">
                                    <div class="filters-container">
                                        <div class="filter-group-container member-group">
					                        <?php
					                        $tax_obj            = get_taxonomy( 'member_group' );
					                        //$all_terms_label    = $tax_obj->labels->all_items;
					                        $member_groups      = get_terms( [
						                        'taxonomy'      => 'member_group',
						                        'hide_empty'    => true,
					                        ] );

					                        if( count($member_groups) > 0 ) {
						                        ?>
                                                <div style='margin-bottom:10px;'>
                                                    <span><?= esc_html( $tax_obj->labels->name ) ?></span>
                                                </div>
                                                <div>
                                                    <input type='checkbox' id='member_group_any' name='member_group[]' value='any' checked="checked">
                                                    <label for='member_group_any' class='recipient-field-label'><?= __('Any', 'texteller'); ?></label>
                                                </div>
						                        <?php
						                        foreach ( $member_groups as $member_group ) {
							                        ?>
                                                    <div>
                                                        <input type='checkbox' id='tlr-member-group-<?= esc_attr( $member_group->slug ) ?>' name='member_group[]' value='<?= esc_attr( $member_group->slug ) ?>'>
                                                        <label for='tlr-member-group-<?= esc_attr( $member_group->slug ) ?>'><?= esc_html( $member_group->name . ' ' . "($member_group->count)" ) ?></label>
                                                    </div>
							                        <?php
						                        }
					                        }
					                        ?>
                                        </div>
                                        <div class="filter-group-container registration-origin">
                                            <div style='margin-bottom:10px;'>
                                                <span><?= __( 'Registration origin', 'texteller' ); ?></span>
                                            </div>
                                            <div>
                                                <input type='checkbox' id='tlr-registration-origin-any' name='registration_origin[]' value='any' checked="checked">
                                                <label for='tlr-registration-origin-any'><?= __('Any', 'texteller'); ?></label>
                                            </div>
					                        <?php
					                        $origins = apply_filters( 'tlr_registration_origins', [] );

					                        if ( !empty( $origins ) && is_array( $origins ) ) {
						                        foreach ( $origins as $key => $title ) {
							                        ?>
                                                    <div>
                                                        <input id="tlr-registration-origin-<?= $key; ?>" type='checkbox' name='registration_origin[]' value='<?= $key; ?>'>
                                                        <label for="tlr-registration-origin-<?= $key; ?>"><?= $title; ?></label>
                                                    </div>
							                        <?php
						                        }
					                        }
					                        ?>
                                        </div>
                                        <div class="filter-group-container user-linked">
                                            <div style='margin-bottom:10px;'>
                                                <span><?= __( 'Linked to a user', 'texteller' ); ?></span>
                                            </div>
                                            <div>
                                                <input type='checkbox' id='tlr-linked-user-any' name='user_linked[]' value='any' checked="checked">
                                                <label for='tlr-linked-user-any'><?= __('Any', 'texteller'); ?></label>
                                            </div>
                                            <div>
                                                <input id="tlr-linked-user-yes" type='checkbox' name='user_linked[]' value='1'>
                                                <label for="tlr-linked-user-yes"><?= __( 'Yes', 'texteller' ) ; ?>
                                                </label>
                                            </div>
                                            <div>
                                                <input id="tlr-linked-user-no" type='checkbox' name='user_linked[]' value='0'>
                                                <label for="tlr-linked-user-no"><?= __( 'No', 'texteller' ) ; ?></label>
                                            </div>
                                        </div>
                                        <div class="filter-group-container status">
                                            <div style='margin-bottom:10px;'>
                                                <span><?= __( 'Status', 'texteller' ); ?></span>
                                            </div>
                                            <div>
                                                <input type='checkbox' id='tlr-status-any' name='status[]' value='any' checked="checked">
                                                <label for='tlr-status-any'><?= __('Any', 'texteller'); ?></label>
                                            </div>
                                            <div>
                                                <input id="tlr-status-registered" type='checkbox' name='status[]' value='registered'>
                                                <label for="tlr-status-registered"><?= __( 'Registered', 'texteller' ) ; ?></label>
                                            </div>
                                            <div>
                                                <input id="tlr-status-verified" type='checkbox' name='status[]' value='verified'>
                                                <label for="tlr-status-verified"><?= __( 'Verified', 'texteller' ) ; ?></label>
                                            </div>
                                            <div>
                                                <input id="tlr-status-canceled" type='checkbox' name='status[]' value='canceled'>
                                                <label for="tlr-status-canceled"><?= __( 'Canceled', 'texteller' ) ; ?></label>
                                            </div>
					                        <?php
					                        ?>
                                        </div>
                                        <div class="filter-group-container title">
                                            <div style='margin-bottom:10px;'>
                                                <span><?= __( 'Title', 'texteller' ); ?></span>
                                            </div>
                                            <div>
                                                <input type='checkbox' id='tlr-title-any' name='title[]' value='any' checked="checked">
                                                <label for='tlr-title-any'><?= __('Any', 'texteller'); ?></label>
                                            </div>
                                            <div>
                                                <input id="tlr-title-mr" type='checkbox' name='title[]' value='mr'>
                                                <label for="tlr-title-mr"><?= __( 'Mr', 'texteller' ) ; ?></label>
                                            </div>
                                            <div>
                                                <input id="tlr-title-mrs" type='checkbox' name='title[]' value='mrs'>
                                                <label for="tlr-title-mrs"><?= __( 'Mrs', 'texteller' ) ; ?></label>
                                            </div>
                                            <div>
                                                <input id="tlr-title-miss" type='checkbox' name='title[]' value='miss'>
                                                <label for="tlr-title-miss"><?= __( 'Miss', 'texteller' ) ; ?></label>
                                            </div>
                                            <div>
                                                <input id="tlr-title-ms" type='checkbox' name='title[]' value='ms'>
                                                <label for="tlr-title-ms"><?= __( 'Ms', 'texteller' ) ; ?></label>
                                            </div>
					                        <?php
					                        ?>
                                        </div>
                                    </div>
                                    <div class="filters-preview-wrap">
                                        <div>
					                        <?php wp_nonce_field('tlr-admin-filter-members', 'filter-nonce', false) ?>
                                            <button id="tlr-filter-members"><?= __('Filter Members', 'texteller'); ?></button>
                                        </div>
                                        <div>
                                            <p class="member-filter-result"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="single-recipient-type-wrap">
                            <div class="tlr-recipient-type">
                                <input type="checkbox" id="custom-numbers" class="has-content"<?php
		                        checked($numbers_check, '1'); ?> value="1">
                                <label for="custom-numbers"><?= __( 'Custom Numbers', 'texteller' ); ?></label>
                            </div>
                            <div class="recipient-type-content-wrap custom-numbers-content">
                                <div class="recipient-type-fields-wrap" style="display: grid;grid-template-columns: auto;">
                                    <span class="recipient-field-label"><?= __( 'Enter numbers', 'texteller' ) ?></span>
			                        <?php
			                        foreach ( $numbers as $key => $stored_number ) {
				                        ?>
                                        <div class="recipient-number">
                                            <input type='text' name="custom_numbers[]" value='<?= esc_attr( $stored_number ) ?>' class="tlr-mobile" aria-label="Enter a custom mobile number">
					                        <?php
					                        if ( 0 === $key) {
						                        ?>
                                                <button class="add-variable add-number" title="<?php
						                        esc_attr_e('Add new', 'texteller') ?>">
							                        <?php esc_html_e('Add New','texteller') ?>
                                                </button>
						                        <?php
					                        } else {
						                        ?>
                                                <button class="remove-variable remove-number" title="<?php
                                                esc_attr_e( 'Remove','texteller' )?>">
							                        <?php esc_html_e( 'Remove','texteller' ) ?>
                                                </button>
						                        <?php
					                        }
					                        ?>
                                        </div>
				                        <?php
			                        }
			                        ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tlr-section" style="display:none" id="recipient-list"></div>
            <div class="tlr-section">
                <div class="tlr-section-label">
                    <label for="message-content" style="display: block; margin-top:5px;"><?= __( 'Message Content', 'texteller' ); ?></label>
                </div>
                <div class="tlr-section-content">
                    <div class="message-content-wrap">
                        <textarea id="message-content" class="tlr-count" cols="100" rows="10" required="required"><?=
	                        esc_html($content);
	                        ?></textarea>
                    </div>
                    <div class="message-tags" style="padding-top:5px;">
                        <span><?=__('Available tags', 'texteller')?></span>
                        <hr class="tlr-separator">
                        <ul style="column-count: 3;">
			                <?php
			                $message_tags = \Texteller\Admin\Manual_Send::get_base_tags_array();
			                foreach ( (array) $message_tags as $tag_id => $tag_title ) {
				                ?>
                                <li>
                                    <span><?= esc_html($tag_title) ?>:</span>
                                    <code class="tlr-tag">{<?= esc_html($tag_id) ?>}</code>
                                </li>
				                <?php
			                }
			                ?>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="tlr-section" style="background:none">
                <div class="tlr-section-label">
                    <div class="tlr-submit">
	                    <?php wp_nonce_field('tlr-admin-manual-send', 'send-nonce') ?>
                        <input type="hidden" name="mcount" id="mcount" value="0" />
						<?php wp_nonce_field('mpsaction', 'mpsactionf'); ?>
                        <button id="tlr-send" disabled="disabled"><?= __( 'Send', 'texteller' ); ?></button>
                    </div>
                </div>
                <div id="send-result-wrap" class="tlr-section-content">
                    <div>
                        <span class="send-result"></span>
                    </div>
                </div>
            </div>
            <div>
                <span><?= __( "Note: Digits and short links conversion and automatic signature insertion won't affect this message.", 'texteller' ); ?></span>
            </div>
        </div>
    </form>
</div>
<?php
if (isset($_GET['to'])) {
	echo '<script>jQuery("#mpst").val("custom").attr("data-to","' . $_GET['to'] . '");</script>';
}
?>
