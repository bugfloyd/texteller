<?php

defined( 'ABSPATH' ) || exit;

/**
 * Template: Admin Tools
 * Generates admin Tools screen
 *
 * @since 0.1.3
**/

$tools_tabs = [
        [
                'slug' => 'importer',
            'title' => __( 'Member Importer', 'texteller' )
        ],
    [
            'slug' => 'import_logs',
        'title' => __( 'Import Logs', 'texteller' )
    ]
];

$import_args = \Texteller\Admin\Tools::$import_args;

if ( isset($_GET['tab']) && !empty($_GET['tab']) ) {
	$current_tab = $_GET['tab'];
} else {
	$current_tab = 'importer';
}

?>
<div class="wrap">
    <div id="icon-options-general" class="icon32"></div>
    <h1><?= __('Texteller Tools', 'texteller'); ?></h1>
    <h2 class="nav-tab-wrapper"><?php
        foreach ( $tools_tabs as $tab ) { ?>
            <a href="?page=tlr-tools&tab=<?= $tab['slug']; ?>" class="nav-tab <?php
                if ( $current_tab == $tab['slug'] ) {
                    echo 'nav-tab-active';
                }
            ?>"><?= $tab['title']; ?></a><?php
            $tab_slugs[] = $tab['slug'];
        } ?>
    </h2>
    <br>
    <?php
    if ( 'importer' === $current_tab ) {
        ?>
        <div class="tlr-options-container">
            <form method="post" class="tlr-options">
                <?php wp_nonce_field( 'tlr-user-importer', 'tlr_import_nonce' ) ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Import from', 'texteller'); ?></th>
                        <td>
                            <div class="tlr-option-wrap" style="grid-template-columns: auto; grid-template-areas: none;">
                                <label>
                                    <input type="radio" name="import_type" value="plugin"<?php checked($import_args['import_type'], 'plugin' );?>>
                                    <?php esc_html_e('Import members from registered users via other third-party plugins', 'texteller'); ?>
                                    <select id="tlr_import_from" name="tlr_import_from">
                                        <option value=""<?php selected($import_args['import_from'], '' );?>><?php esc_html_e('Select', 'texteller'); ?></option>
                                        <option value="wc-billing-phone"<?php selected($import_args['import_from'], 'wc-billing-phone' );?>>WooCommerce Customers (Billing Phone)</option>
                                        <option value="digits"<?php selected($import_args['import_from'], 'digits' );?>>Digits : WordPress Mobile Number Signup and Login</option>
                                        <option value="melipayamak"<?php selected($import_args['import_from'], 'melipayamak' );?>>Melipayamak</option>
                                    </select>
                                </label>
                                <label>
                                    <input type="radio" name="import_type" value="metadata"<?php checked($import_args['import_type'], 'metadata' );?>>
                                    <?php esc_html_e('Enter custom user meta key to import mobile numbers', 'texteller'); ?>
                                    <input type="text" name="metakey" value="<?= $import_args['import_type'] === 'metadata' ? $import_args['meta_key'] : '' ?>" placeholder="meta_key">
                                </label>

                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Number Verification', 'texteller'); ?></th>
                        <td>
                            <div class="tlr-option-wrap" style="grid-template-columns: auto; grid-template-areas: none;">
                                <label>
                                    <input type="checkbox" name="number_verification" value="yes"<?php checked($import_args['verification'], 'yes') ?>>
                                    <?php esc_html_e("Set member's mobile number as verified, after import", 'texteller'); ?>
                                </label>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('User Role', 'texteller'); ?></th>
                        <td>
                            <div class="tlr-option-wrap" style="grid-template-columns: auto; grid-template-areas: none;">
                                <label>
                                    <?php esc_html_e("Limit import process by user role", 'texteller'); ?>
                                    <select name="user_role">
                                        <option value=""<?php selected($import_args['user_role'], ''); ?>><?php esc_html_e( 'Any Role', 'texteller' ); ?></option>
                                        <?php
                                        global $wp_roles;
                                        $all_roles = $wp_roles->roles;
                                        foreach ($all_roles as $role_slug => $role_data ) {
                                            ?>
                                            <option value="<?= esc_attr($role_slug) ?>"<?php selected($import_args['user_role'], $role_slug); ?>><?= esc_html($role_data['name']) ?></option>
                                            <?php
                                        }
                                        ?>
                                    </select>
                                </label>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Member Group', 'texteller'); ?></th>
                        <td>
                            <div class="tlr-option-wrap" style="grid-template-columns: auto; grid-template-areas: none;">
                                <label>
                                    <?php esc_html_e("Apply a member group to imported members", 'texteller'); ?>
                                    <select name="member_group">
                                        <option value=""<?php selected($import_args['member_group'], ''); ?>><?php esc_html_e( 'None', 'texteller' ); ?></option>
                                        <?php
                                        $member_groups = \Texteller\tlr_get_member_groups();
                                        foreach ( $member_groups as $slug => $name ) {
                                            ?>
                                            <option value="<?= esc_attr($slug); ?>"<?php selected($import_args['member_group'], $slug); ?>><?= esc_html($name); ?></option>
                                        <?php
                                        }
                                        ?>

                                    </select>
                                </label>
                            </div>
                        </td>
                    </tr>
                </table>
                <p>
                    <?= esc_html__('Note:', 'texteller') . ' ' . esc_html__('If the mobile number of a user does not include country code, importer tool will use country codes defined in "Default Country List" option to get the full international number.'); ?>
                </p>
                <?php
                submit_button(  __('Import Members', 'texteller') );
                ?>
            </form>
        </div>
        <div class="tlr-import-result">
            <?php
            if( \Texteller\Admin\Tools::$import_started !== null ) {
                    if ( \Texteller\Admin\Tools::$import_started && \Texteller\Admin\Tools::$user_count > 0 ) {
                        echo '<p>';
                        $message = _n_noop( '%s user found.', '%s users found.', 'texteller' );
                        esc_html(
                                printf(
                                        translate_nooped_plural( $message, \Texteller\Admin\Tools::$user_count, 'texteller' ),
                                        number_format_i18n( \Texteller\Admin\Tools::$user_count )
                                )
                        );
                        echo '</p><p>';
                        esc_html_e('Import process has been started. Check import logs.', 'texteller');
                        echo '</p>';
                    } elseif ( \Texteller\Admin\Tools::$import_started === false && \Texteller\Admin\Tools::$user_count === 0 ) {
                        echo '<p>';
                        esc_html_e('No user found.', 'texteller');
                        echo '</p>';
                    }
            }
            ?>
        </div>
    <?php
    } elseif( 'import_logs' === $current_tab ) {
        ?>
        <div class="tlr-import-logs">
            <div class="log-selector-wrap">
                <form method="post">
                    <?php wp_nonce_field( 'tlr-log-viewer', 'tlr_log_nonce' ) ?>
                    <select name="log_selector" aria-label="Select a log to view">
                        <option <?= empty($_POST['log_selector']) ? 'selected="selected"' : ''  ?>value=""><?php esc_html_e('Select a log to view', 'texteller'); ?></option>
                        <?php
                        $logs = \Texteller\Admin\Tools::list_logs();
                        $selected = !empty($_POST['log_selector']) ? $_POST['log_selector'] : '';

                        foreach ($logs as $log) {
                            $log = str_replace( 'member-importer-', '', $log );
                            $label = substr_replace($log, ' ', 8, 1);
                            $label = substr_replace($label, ':', 11, 1);
                            ?>
                            <option value="<?= esc_attr($log); ?>"<?php selected($selected, $log) ?>><?= esc_html($label); ?></option>
                        <?php
                        }
                        ?>
                    </select>
                    <?php submit_button( __('View Log', 'texteller'), 'primary', 'submit', false ); ?>
                </form>
                <hr>
            </div>
                <?php
                $log_data = \Texteller\Admin\Tools::read_log();
                if ( !empty($log_data) ) {
                    ?>
            <table>
                <tr>
                    <th>User ID</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Phone Number</th>
                    <th>Member ID</th>
                    <th>Status</th>
                </tr>
                    <?php
                    foreach ( (array) $log_data as $log) {
                        ?>
                        <tr>
                            <?php
                            foreach ($log as $item) {
                                ?><td><?= esc_html($item); ?></td><?php
                            }
                            ?>
                        </tr>
                        <?php
                    }
                } else {
                    echo 'Please select a log file to display.';
                }
                ?>
            </table>
            <?php
            ?>
        </div>
    <?php
    }
    ?>
</div>