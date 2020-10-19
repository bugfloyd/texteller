<?php
defined( 'ABSPATH' ) || exit;
/**
 * Template: Admin Settings Header
 * Generates admin settings tabs
**/

/**
 * Filters plugin option tabs
 *
 * @see \Texteller\Interfaces\Options::add_option_tabs()
 *
 * @param array Option tabs
 */
$settings_pages = apply_filters( 'texteller_option_pages', [] );

if ( isset($_GET['tab']) && !empty($_GET['tab']) ) {
	$current_tab = $_GET['tab'];
} else {
	$current_tab = 'tlr_texteller';
}

?>
<div class="wrap">
    <div id="icon-options-general" class="icon32"></div>
    <?php settings_errors(); ?>
    <h1><?= __('Texteller Options', 'texteller'); ?></h1>
    <h2 class="nav-tab-wrapper"><?php
        foreach ( $settings_pages as $tab ) { ?>
            <a href="?page=tlr-options&tab=<?= $tab['slug']; ?>" class="nav-tab <?php
                if ( $current_tab == $tab['slug'] ) {
                    echo 'nav-tab-active';
                }
            ?>"><?= $tab['title']; ?></a><?php
            $tab_slugs[] = $tab['slug'];
        } ?>
    </h2>
    <ul class="subsubsub">
        <?php
        /**
         *
         */
        $sections = \Texteller\get_page_sections( $current_tab );
        $sections_keys = array_keys( $sections );
        if ( count($sections_keys) > 1 ) {
	        if ( isset($_GET['section']) && !empty($_GET['section']) ) {
		        $current_section = $_GET['section'];
	        } else {
		        $current_section = $sections_keys[0];
	        }
	        foreach ( $sections as $id => $label ) {
		        echo "<li><a href='" . admin_url( "admin.php?page=tlr-options&tab=$current_tab&section=$id" ) . "' class='" . ( $current_section == $id ? 'current' : '' ) . "'>" . $label . '</a> ' . ( end( $sections_keys ) == $id ? '' : '|' ) . ' </li>';
	        }
        } else {
	        $current_section = $sections_keys[0];
        }
        ?>
    </ul><br class="clear" />
    <div class="tlr-options-container">
        <form method="post" action="<?php echo esc_url( admin_url('options.php' )); ?>" class="tlr-options" enctype="multipart/form-data">
		    <?php
            if ( count($sections_keys) == 1 ) {
                settings_fields($current_section);
                do_settings_sections( $current_tab );
            } else {
                settings_fields($current_section);
                global $wp_settings_sections, $wp_settings_fields;
                if ( $wp_settings_sections[$current_tab][$current_section]['callback'] ) {
                    echo '<br>';
                    call_user_func( $wp_settings_sections[$current_tab][$current_section]['callback'], $current_tab );
                }
                if ( !empty( $wp_settings_fields ) && isset( $wp_settings_fields[ $current_tab ] ) && isset( $wp_settings_fields[ $current_tab ][ $current_section ] ) ) {
                    echo '<table class="form-table">';
                    do_settings_fields( $current_tab, $current_section );
                    echo '</table>';
                }
            }

            do_action('tlr_options_after_fields', $current_section, $current_tab );

            submit_button();
		    ?>
        </form>
    </div>
</div>