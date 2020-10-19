<?php

namespace Texteller\Admin;
use Texteller as TLR;
use function Texteller\tlr_get_full_number;

defined( 'ABSPATH' ) || exit;

/**
 * Class Importer
 * @package Texteller\Admin
 * @since 0.1.3
 */
class Tools {

    /**
     * Is import started?
     *
     * @var null|bool
     */
    public static $import_started = null;

    public static $user_count = 0;

    private static $date = '';

    public static $current_log = '';

    public static $import_args = [
        'import_type'   =>  'plugin',
        'import_from'   =>  '',
        'meta_key'      =>  '',
        'verification'  =>  '',
        'user_role'     =>  '',
        'member_group'  =>  ''
    ];

    public static function init()
    {
        if ( empty( $_REQUEST['submit'] ) || ! current_user_can('manage_options' ) ) {
            return;
        }

        // Set Import parameters and schedule the cron job
        if (
            isset( $_REQUEST['tlr_import_nonce'] )
            && wp_verify_nonce($_REQUEST['tlr_import_nonce'],'tlr-user-importer')
        ) {
            self::set_import_args();
            self::set_cron_import();
        }

        // Set requested log to be displayed
        if (
            isset( $_REQUEST['tlr_log_nonce'] )
            && wp_verify_nonce($_REQUEST['tlr_log_nonce'],'tlr-log-viewer')
            && isset($_POST['log_selector'])
        ) {
            self::$current_log = sanitize_text_field($_POST['log_selector']);
        }
    }

    private static function set_import_args()
    {
        $import_args = [];
        $import_args['import_type'] = isset($_POST['import_type']) ? sanitize_text_field($_POST['import_type']) : '';

        if ( 'plugin' === $import_args['import_type'] ) {
            $import_args['import_from'] = isset($_POST['tlr_import_from']) ?
                sanitize_text_field($_POST['tlr_import_from']) : '';

            switch ($import_args['import_from']) {
                case 'wc-billing-phone':
                    $import_args['meta_key'] = 'billing_phone';
                    break;
                case 'digits':
                    $import_args['meta_key'] = 'digits_phone';
                    break;
                case 'melipayamak':
                    $import_args['meta_key'] = 'mpmobile';
                    break;
            }
        } elseif( 'metadata' === $import_args['import_type'] ) {
            $import_args['meta_key'] = isset($_POST['metakey']) ? sanitize_text_field($_POST['metakey']) : '';
        }

        $import_args['verification'] = isset($_POST['number_verification']) && 'yes' === $_POST['number_verification'] ? 'yes' : '';
        $import_args['user_role'] = isset($_POST['user_role']) ? sanitize_text_field($_POST['user_role']) : '';
        $import_args['member_group'] = isset($_POST['member_group']) ? sanitize_text_field($_POST['member_group']) : '';

        self::$import_args = $import_args;
    }

    private static function set_cron_import()
    {
        if ( empty(self::$import_args['meta_key']) ) {
            return;
        }

        $args = [
            'meta_key'      =>  self::$import_args['meta_key'],
            'meta_compare'  =>  'EXISTS'
        ];

        if ( !empty(self::$import_args['user_role']) ) {
            $args['role'] = self::$import_args['user_role'];
        }

        $user_query = new \WP_User_Query( $args );
        $users = $user_query->get_results();
        $user_count = count($users);
        if ( $user_count > 0 ) {
            if (
            ! wp_next_scheduled( 'tlr_cron_import', [ self::$import_args, $users ] )
            ) {
                wp_schedule_single_event(
                    time(),
                    'tlr_cron_import',
                    [ self::$import_args, $users ]
                );
                self::$import_started = true;
                self::$user_count = $user_count;
                return;
            }
        }
        self::$import_started = false;
    }

    /**
     * @param array $import_args
     * @param \WP_User[] $users
     */
    public static function cron_import( $import_args, $users )
    {
        self::$date = current_time( 'y-m-d-H-i', false );

        $i = 0;
        $results = [];

        foreach ( (array) $users as $user) {

            // Get the mobile number
            $maybe_number = get_user_meta( $user->ID, $import_args['meta_key'], true );
            if ( !empty($maybe_number) ) {
                if ( TLR\tlr_is_mobile_valid($maybe_number) ) {
                    $mobile = $maybe_number;
                } else {
                    $default_countries = get_option( 'tlr_frontend_default_countries', ['US'] );
                    foreach ( $default_countries as $default_country ) {
                        $mobile = TLR\tlr_get_full_number( $maybe_number, $default_country );
                        if ($mobile) {
                            break;
                        }
                    }
                }

                // Insert a new member
                if ( !empty($mobile) ) {
                    $possible_member_id = TLR\tlr_get_member_id($mobile, 'mobile');
                    if ( !$possible_member_id ) {
                        $member = new TLR\Member();
                        $member->set_member_data( [
                            'first_name'    =>  $user->first_name,
                            'last_name'     =>  $user->last_name,
                            'mobile'        =>  $mobile,
                            'email'         =>  $user->user_email,
                            'user_id'       =>  $user->ID,
                            'reg_origin'    =>  'tlr-importer',
                            'status'        =>  $import_args['verification'] === 'yes' ? 'verified' : 'registered',
                            'member_group'  =>  !empty($import_args['member_group']) && term_exists($import_args['member_group'], 'member_group') ? [$import_args['member_group']] : []
                        ] );
                        $member_id = $member->save();

                        $results[] = [
                            'first_name'    =>  $user->first_name,
                            'last_name'     =>  $user->last_name,
                            'user_id'       =>  $user->ID,
                            'mobile'        =>  $mobile,
                            'member_id'     =>  $member_id,
                            'status'        =>  'Imported'
                        ];
                    } else {
                        $results[] = [
                            'first_name'    =>  $user->first_name,
                            'last_name'     =>  $user->last_name,
                            'user_id'       =>  $user->ID,
                            'mobile'        =>  $mobile,
                            'member_id'     =>  $possible_member_id,
                            'status'        =>  'Exists'
                        ];
                    }

                } else {
                    $results[] = [
                        'first_name'    =>  $user->first_name,
                        'last_name'     =>  $user->last_name,
                        'user_id'       =>  $user->ID,
                        'mobile'        =>  $maybe_number,
                        'member_id'     =>  0,
                        'status'        =>  'Failed'
                    ];
                }
            }

            if( 0 === ++$i % 5 ) {
                self::write_log($results);
                $results = [];
            }
        }

        self::write_log($results);
    }

    private static function write_log( $results )
    {
        if ( empty($results) ) {
            return;
        }

        require_once( ABSPATH . 'wp-admin/includes/file.php' );

        $upload_dir = wp_upload_dir();
        $dir = trailingslashit( $upload_dir['basedir'] ) . 'texteller/log';
        $date = self::$date;
        $content = '';

        // Make directories if they don't exist
        if( !is_dir( $dir ) ) {
            mkdir( $dir, 0750, true );
        }

        foreach ( (array) $results as $result) {
            $content .= "{$result['user_id']},{$result['first_name']},{$result['last_name']},{$result['mobile']},{$result['member_id']},{$result['status']}\n";
        }

        file_put_contents(
            $dir. "/member-importer-$date.log",
            $content,
            FILE_APPEND | LOCK_EX
        );
    }

    public static function list_logs()
    {
        $upload_dir = wp_upload_dir();
        $dir = trailingslashit( $upload_dir['basedir'] ) . 'texteller/log';
        $files = glob($dir . "/member-importer*.log");
        foreach ($files as $i => $file) {
            $files[$i] = basename($file, ".log");
        }
        return $files;
    }

    public static function read_log()
    {
        $upload_dir = wp_upload_dir();
        $dir = trailingslashit( $upload_dir['basedir'] ) . 'texteller/log';
        $log = self::$current_log;

        if ( !empty($log) && file_exists( $dir . "/member-importer-{$log}.log" ) ) {
            $log_csv = file_get_contents( $dir . "/member-importer-$log.log" );
            $log_arr = explode("\n", $log_csv );
            $log_arr = array_map( function( $row ) {
                return explode(',', $row );
            }, $log_arr );
            array_pop($log_arr);
            return $log_arr;
        }
        return [];
    }

	public static function render()
	{
        TLR\get_admin_template('admin-tools' );
	}
}