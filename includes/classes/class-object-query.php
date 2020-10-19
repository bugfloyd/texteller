<?php

namespace Texteller;

defined( 'ABSPATH' ) || exit;

class Object_Query {

	private $member_args = [
		'statuses'      =>  [],
		'member_ids'    =>  [],
		'member_groups' =>  [],
		'reg_origins'   =>  [],
		'titles'        =>  [],
		'user_linked'   =>  null,
		'field'         =>  ''
	];

	private $message_args = [
		'triggers'      =>  [],
		'statuses'      =>  [],
		'gateways'      =>  [],
		'interfaces'    =>  [],
		'member_ids'    =>  [],
		'field'         =>  ''
	];

	public function __construct( $args )
	{
		if ( isset( $args['object_type'] ) ) {
			if ( 'member' === $args['object_type'] ) {
				$this->set_member_args( $args );
			} elseif ( 'message' === $args['object_type'] ) {
				$this->set_message_args( $args );
			}
		}
	}

	private function set_member_args( $args )
	{
		$available_statuses = [ 'registered', 'verified', 'canceled' ];
		if( !empty( $args['statuses'] ) && is_array( $args['statuses'] ) ) {
			foreach ( $args['statuses'] as $key => $status ) {
				if( ! in_array( $status, $available_statuses, true ) ) {
					unset( $args['statuses'][$key] );
				}
			}
			$this->member_args['statuses'] = $args['statuses'];
		} else {
			$this->member_args['statuses'] = $available_statuses;
		}

		if( isset( $args['member_ids'] ) && is_array( $args['member_ids'] ) ) {
			$this->member_args['member_ids'] = array_map( function($member_id){ return intval($member_id); }, $args['member_ids'] );
		}

		if( isset( $args['member_groups'] ) && is_array( $args['member_groups'] ) ) {
			foreach ( $args['member_groups'] as $key => $member_group ) {
				if( ! term_exists( $member_group, 'member_group' ) ) {
					unset( $args['member_groups'][$key] );
				}
			}
			$this->member_args['member_groups'] = $args['member_groups'];
		}

		if( isset( $args['reg_origins'] ) && is_array( $args['reg_origins'] ) ) {
			$this->member_args['reg_origins'] = esc_sql( $args['reg_origins'] );
		}

		if( isset( $args['titles'] ) && is_array( $args['titles'] ) ) {
			$this->member_args['titles'] = esc_sql( $args['titles'] );
		}

		if( isset( $args['user_linked'] ) ) {
			$this->member_args['user_linked'] = (bool) $args['user_linked'];
		}

		$fields = [ 'ID', 'user_id', 'mobile', 'email' ];
		if( ! empty( $args['field'] ) && in_array( $args['field'], $fields, true ) ) {
			$this->member_args['field'] = $args['field'];
		} else {
			$this->member_args['field'] = '*';
		}
	}

	private function set_message_args( $args )
	{
		$available_statuses = [ 'received', 'pending', 'sent', 'failed', 'delivered', 'read' ];
		if( !empty( $args['statuses'] ) && is_array( $args['statuses'] ) ) {
			foreach ( $args['statuses'] as $key => $status ) {
				if( ! in_array( $status, $available_statuses, true ) ) {
					unset( $args['statuses'][$key] );
				}
			}
			$this->message_args['statuses'] = $args['statuses'];
		} else {
			$this->message_args['statuses'] = $available_statuses;
		}

		if( isset( $args['member_ids'] ) && is_array( $args['member_ids'] ) ) {
			$this->message_args['member_ids'] = array_map( function($member_id){ return intval($member_id); }, $args['member_ids'] );
		}

		if( isset( $args['triggers'] ) && is_array( $args['triggers'] ) ) {
			$this->message_args['triggers'] = esc_sql( $args['triggers'] );
		}

		if( isset( $args['gateways'] ) && is_array( $args['gateways'] ) ) {
			$this->message_args['gateways'] = esc_sql( $args['gateways'] );
		}

		if( isset( $args['interfaces'] ) && is_array( $args['interfaces'] ) ) {
			$this->message_args['interfaces'] = esc_sql( $args['interfaces'] );
		}

		if( isset( $args['gateway_data'] ) && is_string( $args['gateway_data'] ) ) {
			$this->member_args['gateway_data'] = esc_sql( $args['gateway_data'] );
		}

		$fields = [ 'ID', 'message_trigger', 'message_recipient', 'message_gateway', 'message_interface' ];
		if( ! empty( $args['field'] ) && in_array( $args['field'], $fields, true ) ) {
			$this->message_args['field'] = $args['field'];
		} else {
			$this->message_args['field'] = '*';
		}
	}

	public function get_members()
	{
		global $wpdb;
		$members_table = $wpdb->prefix . 'tlr_members';
		$where = [];
		$join = '';

		// Status query
		$statuses = $this->member_args['statuses'];
		if ( ! empty( $statuses ) ) {
			foreach ( $statuses as $key => $status ) {
				$statuses[$key] = "'$status'";
			}
			$statuses = implode(',', $statuses );
			$where[] = "status IN ($statuses)";
		}


		// ID query
		$member_ids = $this->member_args['member_ids'];
		if ( ! empty( $member_ids ) ) {
			foreach ( $member_ids as $key => $member_id ) {
				$member_ids[$key] = $member_id;
			}
			$member_ids = implode(',', $member_ids );
			$where[] = " AND ID IN ($member_ids)";
		}

		// Member group query
		$member_groups = $this->member_args['member_groups'];
		if ( ! empty( $member_groups ) ) {
			$tax_args = [
				'relation'  =>  '',
				'tax_query' =>  [
					'taxonomy'          =>  'member_group',
					'terms'             =>  $member_groups,
					'field'             =>  'slug',
					'operator'          =>  'IN',
					'include_children'  =>  true
				]
			];
			$tax_query = get_tax_sql( $tax_args, $members_table, 'ID');
			$join = $tax_query['join'];
			$where[] = $tax_query['where'];
		}

		// Registration origin query
		$reg_origins = $this->member_args['reg_origins'];
		if ( ! empty( $reg_origins ) ) {
			foreach ( $reg_origins as $key => $reg_origin ) {
				$reg_origins[$key] = "'$reg_origin'";
			}
			$reg_origins = implode(',', $reg_origins );
			$where[] = " AND reg_origin IN ($reg_origins)";
		}

		// Title query
		$titles = $this->member_args['titles'];
		if ( ! empty( $titles ) ) {
			foreach ( $titles as $key => $title ) {
				$titles[$key] = "'$title'";
			}
			$titles = implode(',', $titles );
			$where[] = " AND title IN ($titles)";
		}

		// User query
		$user_linked = $this->member_args['user_linked'];
		if( !is_null( $user_linked ) ) {
			if ( $user_linked ) {
				$where[] = " AND user_id > 0";
			} else {
				$where[] = " AND user_id = 0";
			}
		}

		// Generate the "WHERE" query
		if ( ! empty( $where ) ) {
			$where = implode( '', $where );
			$where = "WHERE $where ";
		} else {
			$where = '';
		}

		// Order queries
		$orderby = 'ID';
		$order = 'DESC';

		// Generate the complete query
		$field = $this->member_args['field'];
		$member_query = "SELECT $field FROM $members_table $join $where ORDER BY $members_table.$orderby $order";

		// Query
		$query_results = $wpdb->get_results( $member_query, ARRAY_A  );

		return '*' !== $field ? wp_list_pluck( $query_results, $field ) : $query_results;
	}

	public static function get_members_count( $status = '' )
	{
		global $wpdb;
		$members_table = $wpdb->prefix . 'tlr_members';
		if ( $status ) {
			$count = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(ID) FROM $members_table WHERE status = %s",
				$status
			));
		} else {
			$count = $wpdb->get_var( "SELECT COUNT(ID) FROM $members_table" );
		}

		return (int) $count;
	}

	public function get_messages( int $limit = 0 )
	{
		global $wpdb;
		$messages_table = $wpdb->prefix . 'tlr_messages';
		$where = [];

		// Status query
		$statuses = $this->message_args['statuses'];
		if ( ! empty( $statuses ) ) {
			$statuses = array_map( function( $status ) {
				return "'$status'";
			}, $statuses );
			$statuses = implode(',', $statuses );
			$where[] = "message_status IN ($statuses)";
		}

		// Member ID query
		if ( ! empty( $this->message_args['member_ids'] ) ) {
			$member_ids = $this->message_args['member_ids'];
			$member_ids = implode(',', $member_ids );
			$where[] = " AND message_member_id IN ($member_ids)";
		}

		// Trigger query
		if ( ! empty( $this->message_args['triggers'] ) ) {
			$triggers = $this->message_args['triggers'];
			$triggers = array_map( function( $trigger ) {
				return "'$trigger'";
			}, $triggers );

			$triggers = implode(',', $triggers );
			$where[] = " AND message_trigger IN ($triggers)";
		}

		// Gateway query
		if ( ! empty( $this->message_args['gateways'] ) ) {
			$gateways = $this->message_args['gateways'];
			$gateways = array_map( function( $gateway ) {
				return "'$gateway'";
			}, $gateways );

			$gateways = implode(',', $gateways );
			$where[] = " AND message_gateway IN ($gateways)";
		}

		// Interface query
		if ( ! empty( $this->message_args['interfaces'] ) ) {
			$interfaces = $this->message_args['interfaces'];
			$interfaces = array_map( function( $interface ) {
				return "'$interface'";
			}, $interfaces );

			$interfaces = implode(',', $interfaces );
			$where[] = " AND message_interface IN ($interfaces)";
		}

		// Gateway data query
		if ( ! empty( $this->message_args['gateway_data'] ) ) {
			$gateway_data = $this->message_args['gateway_data'];
			$where[] = " AND message_gateway_data LIKE %$gateway_data%";
		}

		// Generate the "WHERE" query
		if ( ! empty( $where ) ) {
			$where = implode( '', $where );
			$where = "WHERE $where ";
		} else {
			$where = '';
		}

		// Order queries
		$orderby = 'ID';
		$order = 'DESC';

		// Generate the complete query
		$field = $this->message_args['field'];
		$limit = $limit ? " LIMIT 0, $limit " : '';

		$message_query = "SELECT $field FROM $messages_table $where ORDER BY $messages_table.$orderby $order" . $limit;

		// Query
		$query_results = $wpdb->get_results( $message_query, ARRAY_A  );

		return '*' !== $field ? wp_list_pluck( $query_results, $field ) : $query_results;
	}

	public static function get_messages_count( int $member_id )
	{
		global $wpdb;
		$messages_table = $wpdb->prefix . 'tlr_messages';
		$query = $wpdb->prepare(
			"SELECT COUNT(ID) FROM $messages_table WHERE message_member_id = %d ORDER BY ID DESC",
			$member_id
		);
		return (int) $wpdb->get_var( $query );
	}
}