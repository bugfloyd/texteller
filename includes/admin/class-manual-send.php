<?php

namespace Texteller\Admin;
use Texteller as TLR;
use Texteller\Member;
use Texteller\Tags;
use function Texteller\tlr_get_base_tags_values_array;

defined( 'ABSPATH' ) || exit;

class Manual_Send
{
	use TLR\Traits\Options_Base;

	private function __construct() {}

	public static function manual_send()
	{
		if (
			!isset( $_REQUEST['tlrSecurity'] )
			|| ! check_ajax_referer( 'tlr-admin-manual-send', 'tlrSecurity', false )
			|| empty( $_REQUEST['content'] )
			|| empty( $_REQUEST['gateway'] )
			|| ! current_user_can('manage_options' )
		) {
			wp_send_json_error( __( 'Invalid request','texteller' ) );
		}

		$gateway = in_array( $_REQUEST['gateway'], (array) get_option( 'tlr_active_gateways', [] ) ) ?
			$_REQUEST['gateway'] : ''; //todo

		$raw_text = sanitize_textarea_field( $_POST['content'] );
		$member_ids = [];
		$numbers = [];

		if ( !empty($_REQUEST['membersCheck']) ) {
			$selected_member_ids = isset($_POST['selectedMembers']) ? (array) $_POST['selectedMembers'] : [];
			$member_ids = array_merge( $member_ids, $selected_member_ids );
		}

		if ( !empty($_REQUEST['staffCheck']) ) {
			$selected_staff_ids = isset($_POST['selectedStaff']) ? (array) $_POST['selectedStaff'] : [];
			$member_ids = array_merge( $member_ids, $selected_staff_ids );
		}

		if ( !empty($_REQUEST['filterMembersCheck']) ) {
			$args = self::get_query_args();
			$tlr_query = new TLR\Object_Query( $args );
			$filtered_member_ids = $tlr_query->get_members();
			$member_ids = array_merge( $member_ids, $filtered_member_ids );
		}

		if ( !empty($_REQUEST['numbersCheck']) ) {
			$numbers = isset($_POST['customNumbers']) ? (array) $_POST['customNumbers'] : [];
		}

		// Sanitize member IDs
		$member_ids = array_map( function( $member_id ) {
			return intval( $member_id );
		}, $member_ids );
		$member_ids = array_unique($member_ids);

		// Sanitize custom numbers
		$numbers = array_map( function( $number ) {
			return sanitize_text_field($number);
		}, $numbers );
		$numbers = array_unique($numbers);

		if( empty($member_ids) && empty($numbers) ) {
			wp_send_json([
				'responseText' => __('Failed to start the send process. Recipients list is empty.', 'texteller')
			]);
		}
		$recipients = [
			'members'   =>  $member_ids,
			'numbers'   =>  $numbers
		];

		$set_cron = self::set_cron_send( $recipients, $raw_text, $gateway, $_REQUEST['tlrSecurity'] );

		if ( $set_cron ) {
			wp_send_json(
				[ 'responseText' => __( 'Sending process has been successfully started.', 'texteller' ) ]
			);
		} else {
			wp_send_json(
				[ 'responseText' => __( 'Failed to start the send process.', 'texteller' ) ]
			);
		}
	}

	private static function set_cron_send( $recipients, $raw_text, $gateway, $nonce )
	{
		if (
			! wp_next_scheduled( 'tlr_cron_send', [ $recipients, $raw_text, $gateway, 'tlr_manual_send', $nonce ] )
			|| ! get_transient( 'tlr_manual_send_' . $nonce )
		) {
			$db_save = 'yes' === get_option( 'tlr_manual_send_db_save', 'yes' );
			wp_schedule_single_event(
				time(),
				'tlr_cron_send',
				[ $recipients, $raw_text, $gateway, 'tlr_manual_send', $nonce, $db_save ]
			);
			$transient = [
				'pending_members'   =>  $recipients['members'],
				'pending_numbers'   =>  $recipients['numbers'],
				'sent'              =>  [],
				'failed'            =>  []
			];
			set_transient('tlr_manual_send_' . $nonce, $transient,HOUR_IN_SECONDS );
			return true;
		} else {
			return false;
		}
	}

    public static function cron_send( array $recipients, string $text, string $gateway, string $trigger, string $nonce, bool $db_save = true )
    {
        $transient =  get_transient('tlr_manual_send_' . $nonce );
        if(
            empty( $transient )
            || ! is_array( $transient )
            || ( empty( $transient['pending_members'] ) && empty( $transient['pending_numbers']) )
            || ( empty( $recipients['members'] ) && empty( $recipients['numbers']) )
        ) {
            return;
        }

        if (
            ! empty( $recipients['members'] )
            && ( count($recipients['members'] ) === count( $transient['pending_members'] ) )
        ) {
            $members = [];
            foreach ( (array) $recipients['members'] as $member_id ) {
                $members[$member_id] = new Member( $member_id );
            }

            $i = 0;
            foreach ( $members as $member ) {
                $member_id = $member->get_id();
                $tags = TLR\Gateway_Manager::get_tags( self::class, 'member', $member );
                $final_text = $text;
                TLR\Gateway_Manager::process_tags( $final_text, 'member', $tags );

                if ( $member->get_mobile() ) {
                    $sent = TLR\Gateway_Manager::send(
                        $final_text,
                        $member->get_mobile(),
                        $trigger,
                        $gateway,
                        '',
                        $db_save,
                        $member_id
                    );
                } else {
                    $sent = false;
                }

                if ( false !== $key = array_search( $member_id, $transient['pending_members'], false ) ) {
                    unset( $transient['pending_members'][$key] );
                }

                if ( $sent ) {
                    $transient['sent_members'][] = $member_id;
                } else {
                    $transient['failed_members'][] = $member_id;
                }
                if( 0 === ++$i % 5 ) {
                    delete_transient( 'tlr_manual_send_' . $nonce );
                    set_transient( 'tlr_manual_send_' . $nonce, $transient,HOUR_IN_SECONDS );
                }
            }
        }

        if (
            ! empty( $recipients['numbers'] )
            && ( count( $recipients['numbers'] ) === count( $transient['pending_numbers'] ) )
        ) {
            $maybe_members = [];

            foreach ( (array) $recipients['numbers'] as $number ) {
                if ( $member_id = TLR\tlr_get_member_id( $number, 'mobile' ) ) {
                    $maybe_members[$number] = new Member( $member_id );
                } else {
                    /**
                     * Initiates a temp member to be used in Gateway_Manager::get_tags()
                     */
                    $tetlr_member = new Member();
                    $tetlr_member->set_mobile($number);
                    $maybe_members[$number] = $tetlr_member;
                }
            }

            $i = 0;
            foreach ( $maybe_members as $member ) {
                $tags = TLR\Gateway_Manager::get_tags( self::class, 'member', $member );
                $final_text = $text;
                TLR\Gateway_Manager::process_tags( $final_text, 'member', $tags );

                if ( $member->get_mobile() ) {
                    $sent = TLR\Gateway_Manager::send(
                        $final_text,
                        $member->get_mobile(),
                        $trigger,
                        $gateway,
                        '',
                        $db_save,
                        $member->get_id()
                    );
                } else {
                    $sent = false;
                }

                if ( false !== $key = array_search( $member->get_mobile(), $transient['pending_numbers'], false ) ) {
                    unset( $transient['pending_numbers'][$key] );
                }
                if ( $sent ) {
                    $transient['sent_numbers'][] = $member->get_mobile();
                } else {
                    $transient['failed_numbers'][] = $member->get_mobile();
                }
                if( 0 === ++$i % 5 ) {
                    delete_transient( 'tlr_manual_send_' . $nonce );
                    set_transient( 'tlr_manual_send_' . $nonce, $transient,HOUR_IN_SECONDS );
                }
            }
        }

        delete_transient( 'tlr_manual_send_' . $nonce );
        set_transient( 'tlr_manual_send_' . $nonce, $transient,HOUR_IN_SECONDS );
    }

	public static function check_manual_send_status()
	{
		if (
			!isset( $_REQUEST['tlr_security'] )
			|| ! check_ajax_referer( 'tlr-admin-manual-send-status', 'tlr_security', false )
			|| empty( $_REQUEST['send_nonce'] )
		) {
			wp_send_json_error( __( 'Invalid request','texteller' ) );
		}

		$transient =  get_transient('tlr_manual_send_' . sanitize_text_field( $_REQUEST['send_nonce'] ) );

		$pending_members = isset( $transient['pending_members'] ) ? count( $transient['pending_members'] ) : 0;
		$sent_members = isset( $transient['sent_members'] ) ? count( $transient['sent_members']) : 0;
		$failed_members = isset( $transient['failed_members'] ) ? count( $transient['failed_members'] ) : 0;

		$pending_numbers = isset( $transient['pending_numbers'] ) ? count( $transient['pending_numbers'] ) : 0;
		$sent_numbers = isset( $transient['sent_numbers'] ) ? count( $transient['sent_numbers']) : 0;
		$failed_numbers = isset( $transient['failed_numbers'] ) ? count( $transient['failed_numbers'] ) : 0;

		$pending = $pending_members + $pending_numbers;
		$sent = $sent_members + $sent_numbers;
		$failed = $failed_members + $failed_numbers;
		$status = 0 === $pending ? 'done' : 'sending';

		$response = [
			'responseStatus'    =>  $status,
			'responseText'      =>  __( 'Sent:', 'texteller' ) . ' ' . $sent
			                        . ' | ' . __( 'Remaining:', 'texteller' ) . ' ' . $pending
			                        . ' | ' . __( 'Failed:', 'texteller' ) . ' ' . $failed
		];

		wp_send_json( $response );
	}

	public static function filter_members()
	{
		if ( ! check_ajax_referer( 'tlr-admin-filter-members', 'tlrSecurity' ) ) {
			wp_send_json_error( 'Invalid request sent.','200' );
		}

		$args = self::get_query_args();
		$tlr_query = new TLR\Object_Query( $args );
		$member_rows = $tlr_query->get_members();

		$member_found = count( $member_rows );

		echo( json_encode([
			'responseText'  => sprintf( __('%s member(s) found!', 'texteller'), $member_found )
		]));
		wp_die();
	}

	private static function get_query_args()
	{
		$args = [
			'object_type'   =>  'member',
			'field'         =>  'ID'
		];

		// Member groups
		$member_groups = isset( $_REQUEST['member_groups'] ) ? $_REQUEST['member_groups'] : ['any'];
		if ( is_array( $member_groups ) && !in_array( 'any', $member_groups, true ) ) {
			$args['member_groups'] = TLR\tlr_sanitize_text_field_array( $member_groups );
		}

		// Statuses
		$statuses = isset( $_REQUEST['statuses'] ) ? $_REQUEST['statuses'] : ['any'];
		if ( is_array( $statuses ) && !in_array( 'any', $statuses, true ) ) {
			$args['statuses'] = $statuses;
		}

		// Registration origins
		$reg_origins = isset( $_REQUEST['member_reg_origin'] ) ? $_REQUEST['member_reg_origin'] : ['any'];
		if ( is_array( $reg_origins ) && !in_array( 'any', $reg_origins, true ) ) {
			$args['reg_origins'] = TLR\tlr_sanitize_text_field_array( $reg_origins );
		}

		// Title
		$titles = isset( $_REQUEST['title'] ) ? $_REQUEST['title'] : ['any'];
		if ( is_array( $titles ) && !in_array( 'any', $titles, true ) ) {
			$args['titles'] = TLR\tlr_sanitize_text_field_array( $titles );;
		}

		// User linked
		$user_linked = isset( $_REQUEST['user_linked'] ) ? $_REQUEST['user_linked'] : ['any'];
		if ( is_array( $user_linked ) && !in_array( 'any', $user_linked, true ) && 1 === count($user_linked) ) {
			$args['user_linked'] = (bool) $user_linked[0];
		}

		return $args;
	}

    /**
     * Generates and returns tag values for the tag type
     *
     * @see \Texteller\Gateway_Manager::get_tags()
     *
     * @param string $tag_type
     * @param \Texteller\Tags $tags Current Tags instance to be processed
     * @param mixed $object Current object to use while generating tag values
     * @param array $extra_tags Custom tags added by the trigger
     */
    public static function set_tags_values( string $tag_type, Tags &$tags, $object, array $extra_tags )
    {
        if ( 'member' === $tag_type && is_a($object, '\Texteller\Member') ) {
            $member_tags_values = tlr_get_base_tags_values_array('member', $object );
            $tags->add_tag_type_data('member', $member_tags_values );
        }
    }

	public static function render()
	{
		include TLR_INC_PATH . '/admin/templates/admin-send.php';
	}
}