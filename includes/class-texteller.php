<?php

namespace Texteller;

use Exception;

defined("ABSPATH") || exit();

/**
 * Class Texteller
 *
 * Main Texteller class, add filters and handling all the other files
 *
 * @class		Texteller
 * @version		1.0.0
 * @author		Yashar Hosseinpour
 */
final class Texteller
{
    /**
     * Instance of Texteller
     * @access protected
     * @var ?Texteller $instance The instance of Texteller.
     */
    protected static ?Texteller $instance = null;

    public static string $version = "1.3.0";

    /**
     * Cloning is forbidden.
     *
     * @since 2.1
     */
    public function __clone()
    {
        _doing_it_wrong(__FUNCTION__, "Cloning is forbidden.", "3.0");
    }

    /**
     * Un-serializing instances of this class is forbidden.
     *
     */
    public function __wakeup()
    {
        _doing_it_wrong(
            __FUNCTION__,
            "Un-serializing instances of this class is forbidden.",
            "0.1"
        );
    }

    /**
     * Constructor.
     *
     */
    private function __construct()
    {
        $this->define_constants();

        // Register plugin autoload
        try {
            spl_autoload_register([self::class, "autoload"], false);
        } catch (Exception $e) {
            error_log($e->getMessage());
        }

        // Register composer autoload
        require_once TLR_ABSPATH . "/vendor/autoload.php";

        // Activating the plugin
        register_activation_hook(TLR_PLUGIN_FILE, [self::class, "install"]);
        add_filter("plugin_action_links_" . plugin_basename(TLR_PLUGIN_FILE), [
            self::class,
            "plugin_action_links",
        ]);
        add_filter(
            "plugin_row_meta",
            [self::class, "plugin_desc_links"],
            10,
            2
        );

        // add plugin update notice
        add_action(
            "in_plugin_update_message-texteller/texteller.php",
            [self::class, "show_upgrade_notice"],
            10,
            2
        );

        $this->includes();

        add_action("wp_enqueue_scripts", [
            self::class,
            "register_mobile_field_assets",
        ]);

        $this->init();
        $this->init_modules();
    }

    /**
     * Show description links on the plugins screen.
     *
     * @param mixed $links Plugin Row Meta.
     * @param mixed $file  Plugin Base file.
     *
     * @return array
     */
    public static function plugin_desc_links(array $links, string $file): array
    {
        if (plugin_basename(TLR_PLUGIN_FILE) === $file) {
            $row_links = [
                "website" =>
                    '<a href="' .
                    esc_url("https://www.texteller.com/") .
                    '" aria-label="' .
                    esc_attr__("View Texteller official website", "texteller") .
                    '">' .
                    esc_html__("Official Website", "texteller") .
                    "</a>",
            ];

            return array_merge($links, $row_links);
        }
        return (array) $links;
    }

	/**
	 * Render important update notice when there is some
	 * @since 1.0
	 * @param $currentPluginMetadata
	 * @param $newPluginMetadata
	 *
	 * @return void
	 */
	public static function show_upgrade_notice(
        $currentPluginMetadata,
        $newPluginMetadata
    ) {
        if (
            isset($newPluginMetadata->upgrade_notice) &&
            strlen(trim($newPluginMetadata->upgrade_notice)) > 0
        ) {
			$notice = $newPluginMetadata->upgrade_notice;
            echo '<p style="background-color: #d54e21; padding: 10px; color: #f9f9f9; margin-top: 10px"><strong>' .
                esc_html__("Important Upgrade Notice:", "texteller") .
                "</strong> ";
            echo esc_html($notice), "</p>";
        }
    }

    /**
     * Show action links on the plugins screen.
     *
     * @param mixed $links Plugin Action links.
     *
     * @return array
     */
    public static function plugin_action_links(array $links): array
    {
        $action_links = [
            "settings" =>
                '<a href="' .
                admin_url("admin.php?page=tlr-options") .
                '" aria-label="' .
                esc_attr__("Texteller Options", "texteller") .
                '">' .
                esc_html__("Options", "texteller") .
                "</a>",
        ];
        return array_merge($action_links, $links);
    }

    public static function register_mobile_field_assets(): void
    {
        // Intl Tel Input
        wp_register_script(
            "tlr-intl-tel-input",
            TLR_LIBS_URI . "/intl-tel-input/build/js/intlTelInput.min.js",
            [],
            "17.0.0",
            true
        );
        wp_register_style(
            "tlr-intl-tel-input",
            TLR_LIBS_URI . "/intl-tel-input/build/css/intlTelInput.min.css",
            [],
            "17.0.0"
        );
        wp_register_script(
            "tlr-mobile-field",
            TLR_ASSETS_URI . "/tlr-mobile-input.js",
            ["jquery", "tlr-intl-tel-input"],
            null,
            true
        );
    }

    /**
     * Init.
     *
     * Initialize plugin parts.
     *
     * @since 1.0.1
     */
    public function init(): void
    {
        Member_Group::init();
        Ajax::init();
        self::init_cron_jobs();
        REST_Controller::init();

        new Admin\Admin_Base();
    }

    /**
     * Plugin activation logic
     * Default dates:
     */
    public static function install(): void
    {
        if (is_multisite()) {
            deactivate_plugins(TLR_PLUGIN_FILE);
            die("Texteller: Multi-site support is not available yet.");
        }

        $compatibility_status = self::is_environment_compatible();
        if (true !== $compatibility_status) {
            deactivate_plugins(TLR_PLUGIN_FILE);
            if ($compatibility_status === -1) {
                die(__("Texteller requires PHP 7.4 or higher.", "texteller"));
            } elseif ($compatibility_status === -2) {
                die(
                    __(
                        "Texteller requires WordPress 5.3 or higher.",
                        "texteller"
                    )
                );
            }
        } else {
            self::create_tables();
            self::create_files();
            self::update_versions();
        }
    }

    private static function update_versions(): void
    {
        delete_option("texteller_version");
        add_option("texteller_version", self::$version);
        delete_option("texteller_db_version");
        add_option("texteller_db_version", self::$version);
    }

    private static function create_files(): void
    {
        require_once ABSPATH . "wp-admin/includes/file.php";

        global $wp_filesystem;

        $upload_dir = wp_upload_dir();
        $dir = trailingslashit($upload_dir["basedir"]) . "texteller";
        if (file_exists($dir . "/newsletter/tlr-newsletter.css")) {
            return;
        }

        $url = wp_nonce_url("plugins.php");
        $credentials = request_filesystem_credentials($url);

        if (
            false === $credentials ||
            !WP_Filesystem($credentials) ||
            !is_object($wp_filesystem) ||
            (is_wp_error($wp_filesystem->errors) &&
                $wp_filesystem->errors->has_errors())
        ) {
            return;
        }

        // Make directories if they don't exist
        if (!$wp_filesystem->is_dir($dir)) {
            $wp_filesystem->mkdir($dir);
        }
        if (!$wp_filesystem->is_dir($dir . "/newsletter")) {
            $wp_filesystem->mkdir($dir . "/newsletter");
        }

        // Write the file
        $css = $wp_filesystem->get_contents(
            TLR_ASSETS_PATH . "/newsletter/tlr-newsletter.css"
        );
        if (!empty($css)) {
            $wp_filesystem->put_contents(
                $dir . "/newsletter/tlr-newsletter.css",
                $css,
                FS_CHMOD_FILE
            );
        }
    }

    private static function is_environment_compatible()
    {
        if (version_compare(PHP_VERSION, "7.4", "<")) {
            return -1;
        }
        if (version_compare($GLOBALS["wp_version"], "5.3", "<=")) {
            return -2;
        }
        return true;
    }

    /**
     * @since 0.1.3
     */
    private static function init_cron_jobs(): void
    {
        add_action(
            "tlr_cron_send",
            ["Texteller\Admin\Manual_Send", "cron_send"],
            10,
            5
        );
        add_action(
            "tlr_cron_import",
            ["Texteller\Admin\Tools", "cron_import"],
            10,
            2
        );
    }

    private static function create_tables(): void
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $messages_table = $wpdb->prefix . "tlr_messages";
        $sql = "CREATE TABLE IF NOT EXISTS $messages_table (
		  ID bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		  message_trigger varchar(255) NOT NULL,
		  message_gateway varchar(200) NOT NULL,
		  message_interface varchar(200) NOT NULL,
		  message_interface_number varchar(100),
		  message_recipient varchar(100) NOT NULL,
          message_date datetime DEFAULT '1970-00-00 00:00:00' NOT NULL,
          message_content longtext,
          message_status varchar(40) NOT NULL,
          message_gateway_data longtext NOT NULL,
          message_member_id bigint(20) UNSIGNED, 
          PRIMARY KEY  (ID),
          KEY message_trigger (message_trigger),
          KEY message_gateway (message_gateway),
          KEY message_interface (message_interface),
          KEY message_interface_number (message_interface_number),
          KEY message_status (message_status),
          KEY message_member_id (message_member_id)
          ) $charset_collate;";

        $members_table = $wpdb->prefix . "tlr_members";
        $members_relationships_sql = "CREATE TABLE IF NOT EXISTS $members_table (
  		  ID bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		  user_id bigint(20) UNSIGNED DEFAULT 0 NOT NULL,
		  mobile varchar(100),
		  email varchar(100),
		  registered_date datetime DEFAULT '2005-07-02 00:00:00' NOT NULL,
		  modified_date datetime DEFAULT '2005-07-02 00:00:00' NOT NULL,
		  status varchar(100) DEFAULT 'registered' NOT NULL,
		  reg_origin varchar(100) NOT NULL,
		  first_name varchar(250),
		  last_name varchar(250),
		  title tinytext,
		  verification_key varchar(255),
		  description text,
          PRIMARY KEY  (ID),
          KEY mobile (mobile),
          KEY user_id (user_id),
          KEY email (email),
          KEY `status` (status),
          KEY reg_origin (reg_origin)
          ) $charset_collate;";

        require_once ABSPATH . "wp-admin/includes/upgrade.php";
        dbDelta($sql);
        dbDelta($members_relationships_sql);
    }

    protected function define_constants(): void
    {
        if (!defined("TLR_ABSPATH")) {
            define("TLR_ABSPATH", dirname(TLR_PLUGIN_FILE));
        }
        if (!defined("TLR_INC_PATH")) {
            define("TLR_INC_PATH", TLR_ABSPATH . "/includes");
        }
        if (!defined("TLR_LIBS_PATH")) {
            define("TLR_LIBS_PATH", TLR_ABSPATH . "/libs");
        }
        if (!defined("TLR_ASSETS_PATH")) {
            define("TLR_ASSETS_PATH", TLR_ABSPATH . "/assets");
        }
        if (!defined("TLR_MODULES_PATH")) {
            define("TLR_MODULES_PATH", TLR_ABSPATH . "/modules");
        }
        if (!defined("TLR_MODULES_URI")) {
            define(
                "TLR_MODULES_URI",
                plugin_dir_url(TLR_PLUGIN_FILE) . "modules"
            );
        }
        if (!defined("TLR_ASSETS_URI")) {
            define(
                "TLR_ASSETS_URI",
                plugin_dir_url(TLR_PLUGIN_FILE) . "assets"
            );
        }
        if (!defined("TLR_LIBS_URI")) {
            define("TLR_LIBS_URI", plugin_dir_url(TLR_PLUGIN_FILE) . "libs");
        }
    }

    /**
     * Include required core files used in admin and on the frontend.
     */
    protected function includes(): void
    {
        require_once TLR_INC_PATH . "/functions.php";
    }

    private function init_modules(): void
    {
        new Core_Modules\Base_Module\Base();
        new Core_Modules\Newsletter\Base();
        new Core_Modules\WordPress\Base();

        new Modules\WooCommerce\Base();
    }

    public static function autoload(string $class): void
    {
        if (!str_contains($class, "Texteller")) {
            return;
        }

        // Split the class name into an array to read the namespace and class.
        $file_parts = explode("\\", $class);
        unset($file_parts[0]);
        $file_parts = array_values($file_parts);

        $parts_count = count($file_parts);
        $file_name = "";
        $dir_path = "";
        $path = TLR_INC_PATH;

        // Do a reverse loop through $file_parts to build the path to the file.
        for ($i = $parts_count - 1; $i >= 0; $i--) {
            // Read the current component of the file part.
            $current = strtolower($file_parts[$i]);
            $current = str_ireplace("_", "-", $current);

            // If we're at the first entry, then we're at the filename.
            if ($parts_count - 1 === $i) {
                $file_name = "class-$current.php";
            } else {
                // Modify file names for traits and interfaces
                if ("interfaces" === $current) {
                    $file_name = str_ireplace(
                        "class-",
                        "interface-",
                        $file_name
                    );
                } elseif ("traits" === $current) {
                    $file_name = str_ireplace("class-", "trait-", $file_name);
                }

                if ("modules" === $current) {
                    $path = TLR_ABSPATH;
                }

                $dir_path = "/" . $current . $dir_path;
            }
        }

        if (empty($dir_path)) {
            $dir_path = "/classes";
        }

        // Now build a path to the file using mapping to the file location.
        $filepath = trailingslashit($path . $dir_path) . $file_name;

        // If the file exists in the specified path, then include it.
        if (file_exists($filepath)) {
            include_once $filepath;
        }
    }

    /**
     * Instance
     * Used to retrieve the instance to use on other files/plugins/themes.
     *
     * @return Texteller instance of the class
     */
    public static function getInstance(): Texteller
    {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
