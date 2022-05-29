<?php

namespace Texteller\Admin;
use Texteller as TLR;

defined( 'ABSPATH' ) || exit;

class Message_List_Table extends Object_List_Table_Abstract
{

    protected static $instance = null;

    private $triggers = [];
    private $gateways = [];

	/**
	 * Call the parent constructor to override the defaults $args
	 **/
	public function __construct()
    {
        $this->hidden_columns[] = 'member_id';


		$this->set_tabs( [
			'all'       =>  _x( 'All', 'messages table tab', 'texteller' ),
			'inbox'     =>  _x( 'Inbox', 'messages table tab', 'texteller' ),
			'sent'      =>  _x( 'Sent', 'messages table tab', 'texteller' ),
			'pending'   =>  _x( 'Pending', 'messages table tab', 'texteller' ),
			'failed'    =>  _x( 'Failed', 'messages table tab', 'texteller' ),
			'delivered' =>  _x( 'Delivered', 'messages table tab', 'texteller' ),
		] );

	    $this->set_triggers();
	    $this->set_gateways();

		parent::__construct( [
			'plural'	=>	'messages',
			'singular'	=>	'message',
			'ajax'		=>	false,
		] );
	}

	protected function get_table_action_args() : array
	{
		return [ 'reply', 'resend', 'success_resent', 'failed_resent', 'delete', 'deleted' ];
	}

	public function get_columns()
	{
		$table_columns = [
			'trigger'           =>  __( 'Trigger', 'texteller' ),
			'content'	        =>  __( 'Content', 'texteller' ),
			'gateway'	        =>  __( 'Gateway', 'texteller' ),
			'interface'	        =>  __( 'Interface', 'texteller' ),
            'interface_number'  =>  __( 'Interface Number', 'texteller' ),
			'recipient'         =>  __( 'Recipient', 'texteller' ),
			'status'            =>  __( 'Status', 'texteller' ),
			'date'              =>  __( 'Date', 'texteller' ),
		];
		return $this->tlr_get_columns($table_columns);
	}

	protected function get_sortable_columns()
	{
		$sortable_columns = [
			'ID'        =>  [ 'ID', true ],
			'date'      =>  [ 'date', true ],
            'member_id' =>  [ 'member_id', true ]
		];

		return $sortable_columns;
	}

	private function set_triggers()
	{
		$this->triggers  = TLR\get_notification_triggers();
	}

	private function set_gateways()
    {
	    $gateways = [
		    'bulksms'       =>  __( 'BulkSMS', 'texteller' ),
		    'gatewayapi'    =>  __( 'GatewayAPI', 'texteller' ),
		    'melipayamak'   =>  __( 'Melipayamak', 'texteller' ),
		    'sabanovin'     =>  __( 'SabaNovin', 'texteller' ),
		    'spryng'        =>  __( 'Spryng', 'texteller' ),
		    'textlocal'     =>  __( 'Textlocal', 'texteller' ),
		    'twilio'        =>  __( 'Twilio', 'texteller' ),
	    ];

	    /**
	     * Filter available gateways list
	     *
	     * @param array $available_gateways
	     * @since 0.1.3
	     */
	    $gateways = apply_filters( 'tlr_available_gateways', $gateways );

	    $interfaces = [];
	    foreach ($gateways as $gateway_slug => $gateway_title ) {
	        $this->gateways[$gateway_slug] = [];
		    $this->gateways[$gateway_slug]['title'] = $gateway_title;
		    $this->gateways[$gateway_slug]['interfaces'] = [];

		    $gateway_class = TLR\tlr_get_gateway_class($gateway_slug);
		    if ( $gateway_class ) {
			    /**
			     * @var TLR\Interfaces\Gateway $gateway_class
			     */
			    $gateway_interfaces =  $gateway_class::get_interfaces();
			    $this->gateways[$gateway_slug]['interfaces'] = array_merge( $interfaces, $gateway_interfaces );
		    }
	    }
    }

	/*
	|--------------------------------------------------------------------------
	| Row and Bulk Actions
	|--------------------------------------------------------------------------
	*/

	public function get_bulk_actions()
	{
		$actions = [
            'reply'             =>  __( 'Reply','texteller' ),
            'resend'            =>  __( 'Resend', 'texteller' ),
            'delete'            =>  __( 'Delete', 'texteller' )
		];
		return $actions;
	}

	public function handle_table_actions( string $sendback )
	{
		$the_table_action = $this->current_action();
		$message_ids = $this->object_ids;
		$nonce = wp_unslash( $_REQUEST['_wpnonce'] );

		if ( empty($the_table_action) || empty($message_ids) || empty($nonce) ) {
			return null;
		}

		switch ( $the_table_action ) {
            case 'reply':
	            return $this->reply( $message_ids );
                break;
            case 'resend':
	            return $this->resend( $message_ids, $nonce, $sendback );
                break;

		}
		return null;
	}

	private function reply( $message_ids  )
    {
	    $members = [];
        $numbers = [];
	    foreach ( $message_ids as $message_id ) {
		    $message = new TLR\Message($message_id);

		    if ( $message->get_member_id() ) {
			    $members[] =  $message->get_member_id();
            } else {
			    $numbers[] = $message->get_recipient();
            }
	    }

	    $send_new_args = [
		    'page'      =>  'texteller'
	    ];

	    if ( empty($members) && empty($numbers) ) {
	        return;
        }
	    if ( !empty($members) ) {
		    $members = array_unique($members);
		    $send_new_args['member'] = $members;
	    }
	    if( !empty($numbers) ) {
		    $numbers = array_unique($numbers);
		    $send_new_args['number'] = $numbers;
	    }
	    $send_new_link = add_query_arg( $send_new_args, admin_url('admin.php') );
	    wp_safe_redirect( $send_new_link );
	    exit;
    }

	private function resend( $message_ids, $nonce, $sendback )
	{
		if ( ! wp_verify_nonce( $nonce, 'tlr-resend-single-message' )
		     && ! wp_verify_nonce( $nonce, 'bulk-messages' )
		) {
			return null;
		}

		if ( current_user_can('manage_options' ) ) {
			$i = 0;
			$j = 0;
			foreach ( $message_ids as $message_id ) {
				$message = new TLR\Message( intval($message_id) );

				$sent = TLR\Gateway_Manager::send(
					$message->get_content(),
					$message->get_recipient(),
                    $message->get_trigger(),
					$message->get_gateway(),
					$message->get_interface(),
                    true,
					$message->get_member_id()
                );
				if ($sent) {
					$i ++;
                } else {
				    $j ++;
                }
			}

			$args = [];
			if ( $i ) {
			    $args['success_resent'] = $i;
            }
			if ( $j ) {
				$args['failed_resent'] = $j;
            }

			return add_query_arg( $args, $sendback );

		} else {
			return add_query_arg(
				[ 'failed_resent' => 'not_allowed' ],
				$sendback
			);
		}
	}

	protected function read_notices()
    {
        parent::read_notices();

	    if ( isset($_REQUEST['success_resent']) || isset($_REQUEST['failed_resent']) ) {
		    $success_resent = isset( $_REQUEST['success_resent']) ? sanitize_text_field( wp_unslash($_REQUEST['success_resent']) ) : 0;
		    $failed_resent = isset( $_REQUEST['failed_resent']) ? sanitize_text_field( wp_unslash($_REQUEST['failed_resent']) ) : 0;

		    if ( 'not_allowed' !== $failed_resent && ( $success_resent > 0 || $failed_resent > 0 ) ) {

			    $sent_notice = '';
			    if ( $success_resent ) {
				    if ( 1 === $success_resent ) {
					    $sent_notice = __( 'One message has been successfully sent.', 'texteller' );
				    } else {
					    $sent_notice = sprintf(
						    __( '%d messages have been successfully sent.', 'texteller' ),
						    $success_resent
					    );
				    }
			    }

			    $failed_notice = '';
			    if ( $failed_resent ) {
				    if ( 1 === $failed_resent ) {
					    $failed_notice = __( 'Failed to send one message.', 'texteller' );
				    } else {
					    $failed_notice = sprintf(
						    __( 'Failed to send %d messages.', 'texteller' ),
						    $failed_resent
					    );
				    }
			    }

			    if ( $sent_notice && $failed_notice ) {
				    $this->add_notices( 'warning', $sent_notice . ' | ' . $failed_notice);
			    } elseif( $sent_notice ) {
				    $this->add_notices( 'success', $sent_notice);
			    } elseif( $failed_notice ) {
				    $this->add_notices( 'error', $failed_notice);
                }
		    } else {
			    $this->add_notices(
				    'error',
				    __('You are not allowed to resend messages.','texteller')
			    );
		    }
	    }

    }

	/*
	|--------------------------------------------------------------------------
	| Get Messages
	|--------------------------------------------------------------------------
	*/

	/**
	 * Query, filter data, handle sorting, pagination, and any other data-manipulation required prior to rendering
	 */
	public function prepare_items()
	{
		// Used by WordPress to build and fetch the _column_headers property
		$this->_column_headers = $this->get_column_info();
		$this->_column_headers[1] = [ 'ID' => 'ID' ];
		$this->_column_headers[4] = [ 'recipient' => 'recipient' ];

		// Handle data operations like sorting and filtering
		$table_data = $this->fetch_table_data();

		// Filter the data in case of a search
		$user_search_key = $this->get_request_search();
		if( $user_search_key ) {
			$table_data = $this->filter_table_data( $table_data, $user_search_key );
		}

		// Pagination
		$option = $this->screen->get_option('per_page', 'option');
		$messages_per_page = $this->get_items_per_page( $option, 20 );
		$table_page = $this->get_pagenum();

		// provide the ordered data to the List Table
		// slice the data based on the current pagination
		$this->items = array_slice( $table_data, ( ( $table_page - 1 ) * $messages_per_page ), $messages_per_page );

		// set the pagination arguments
		$total_messages = count( $table_data );
		$this->set_pagination_args( array (
			'total_items' => $total_messages,
			'per_page'    => $messages_per_page,
			'total_pages' => ceil( $total_messages/$messages_per_page )
		) );
	}

	public function fetch_table_data()
	{
		$args = [
			'object_type' => 'message'
		];

		// Status (Tab) query
		if ( ! empty( $_REQUEST['tab'] ) && 'all' !== $_REQUEST['tab'] ) {
		    if ( 'inbox' === $_REQUEST['tab'] ) {
			    $args['statuses'] = ['received'];
            } else {
			    $args['statuses'] = [ sanitize_text_field( $_REQUEST['tab'] ) ];
		    }
		}

		// Trigger query
		if ( ! empty( $_REQUEST['trigger'] ) ) {
			$args['triggers'] = [ sanitize_text_field( $_REQUEST['trigger'] ) ];
		}

		// Member ID query
		if ( ! empty( $_REQUEST['member_id'] ) ) {
			$args['member_ids'] = [ intval( $_REQUEST['member_id'] ) ];
		}

		// Gateway query
		if ( ! empty( $_REQUEST['gateway'] ) ) {
			$args['gateways'] = [ sanitize_text_field( $_REQUEST['gateway'] ) ];
		}

		// Interface query
		if ( ! empty( $_REQUEST['interface'] ) ) {
			$args['interfaces'] = [ sanitize_text_field( $_REQUEST['interface'] ) ];
		}

		// Query
		$tlr_query = new TLR\Object_Query( $args );

		return $tlr_query->get_messages();
	}

	/*
	|--------------------------------------------------------------------------
	| Render Methods: Header & extra navs
	|--------------------------------------------------------------------------
	*/

	/**
	 * Display the table heading and search query, if any
	 */
	public function display_header()
	{
	    $url = esc_url( admin_url( 'admin.php?page=texteller' ) );
		$new_sms_button = "<a href='$url' class='add-new-h2'>" . __( 'Send New Message', 'texteller' ) .  '</a>';
		echo '<h1>' . __( 'Messages', 'texteller' ) . " $new_sms_button</h1>";
	}

	protected function extra_tablenav( $which )
	{
		if ($which === 'top') {
			?>
            <div class="alignleft actions message-filters-wrap">
				<?php
				if( !empty( $this->triggers ) ){
					?>
                    <div class="trigger-filter-wrap">
                        <label for="trigger-filter" class="screen-reader-text">
							<?= __('Filter messages by trigger','texteller'); ?>
                        </label>
                        <select id="trigger-filter" class="tlr-filter">
                            <option value="<?= esc_url( remove_query_arg(['paged', 'trigger']) ); ?>">
								<?= __('All Notification Triggers','texteller'); ?>
                            </option>
							<?php
                            foreach ( $this->triggers as $key => $trigger ) {
                                $selected = isset($_REQUEST['trigger']) ?
                                    selected( $key, $_REQUEST['trigger'], false ) : '';
                                $url = esc_url( remove_query_arg(
                                    'paged',
                                    add_query_arg( ['trigger' => $key], $this->base_url )
                                ) );
                                ?>
                                <option value="<?= $url; ?>"<?= $selected; ?>><?= $trigger; ?></option><?php
                            }
							?>
                        </select>
                    </div>
					<?php
				}
				?>
                <div class="gateway-filter-wrap">
                    <label for="gateway-filter" class="screen-reader-text">
			            <?= __('Filter messages by gateway','texteller'); ?>
                    </label>
                    <select id="gateway-filter" class="tlr-filter">
                        <option value="<?= esc_url( remove_query_arg(['paged', 'gateway']) ); ?>">
				            <?= __('All Gateways','texteller'); ?>
                        </option>
			            <?php
			            foreach ( $this->gateways as $gateway_slug => $gateway_data ) {
				            $selected = isset($_REQUEST['gateway']) ?
					            selected( $gateway_slug, $_REQUEST['gateway'], false ) : '';
				            $url = esc_url( remove_query_arg(
					            'paged',
					            add_query_arg( ['gateway' => $gateway_slug], $this->base_url )
				            ) );
				            ?>
                            <option value="<?= $url; ?>"<?= $selected; ?>><?= $gateway_data['title']; ?></option><?php
			            }
			            ?>
                    </select>
                </div>
                <div class="interface-filter-wrap">
                    <label for="interface-filter" class="screen-reader-text">
			            <?= __('Filter messages by gateway','texteller'); ?>
                    </label>
                    <select id="interface-filter" class="tlr-filter">
                        <option value="<?= esc_url( remove_query_arg(['paged', 'interface']) ); ?>">
				            <?= __('All Interfaces','texteller'); ?>
                        </option>
			            <?php

			            foreach ( $this->gateways as $key => $interfaces ) {
			                if ( 'interfaces' !== $key ) {
			                    continue;
                            }

			            }
			            $interfaces = wp_list_pluck( $this->gateways, 'interfaces' );

			            foreach ( $interfaces as $gateway => $gateway_interfaces ) {
			                ?>
                            <optgroup label="<?= $this->gateways[$gateway]['title'] ?>">
	                            <?php
	                            foreach ( $gateway_interfaces as $gateway_slug => $gateway_title ) {
		                            $selected = isset($_REQUEST['interface']) ?
			                            selected( $gateway_slug, $_REQUEST['interface'], false ) : '';
		                            $url = esc_url( remove_query_arg(
			                            'paged',
			                            add_query_arg( [ 'interface' => $gateway_slug ], $this->base_url )
		                            ) );
		                            ?>
                                    <option value="<?= $url; ?>"<?= $selected; ?>>
			                            <?= $gateway_title; ?>
                                    </option>
		                            <?php
	                            }
	                            ?>
                            </optgroup>
                            <?php
			            }
			            ?>
                    </select>
                </div>
            </div>
            <script>
                jQuery(document).ready( function($) {
                    let filter = $('.tlr-filter');
                    if ( filter.length ) {
                        filter.on( 'change', function () {
                            $(location).attr( 'href', $(this).val() );
                        })
                    }
                });
            </script>
			<?php
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Render Methods: Columns
	|--------------------------------------------------------------------------
	*/

	public function column_trigger( $item )
	{
		/**
		 * Row Actions
		 */
		// Reply
        $reply_args = [
	        'page'      =>  'texteller'
        ];
		$reply_args['number'] = [$item['message_recipient']];

		$reply_link = esc_url( add_query_arg( $reply_args, admin_url('admin.php') ) );
        $actions['reply'] = '<a href="' . $reply_link . '">' . __( 'Reply', 'texteller' ) . '</a>';

        // Forward
		$forward_args = [
			'page'      =>  'texteller',
            'content'   =>  $item['message_content']
		];
		$send_new_link = esc_url( add_query_arg( $forward_args, admin_url('admin.php') ) );
		$actions['forward'] = '<a href="' . $send_new_link . '">' . __( 'Forward', 'texteller' ) . '</a>';

        // Re-Send
        $resend_args = [
                '_wpnonce'  =>  wp_create_nonce('tlr-resend-single-message'),
                'action'    =>  'resend',
                'message'   =>  $item['ID']
        ];
        $resend_link = esc_url( add_query_arg( $resend_args, $this->base_url ) );
		$actions['resend'] = '<a href="' . $resend_link . '">' . __( 'Resend', 'texteller' ) . '</a>';

		// todo: gateway-actions (e.g. delivery)

		/**
		 * Row Title
		 */
		$triggers = $this->triggers;
		if ( isset($triggers[ $item['message_trigger'] ]) ) {
			$row_title = $triggers[ $item['message_trigger'] ];
		} else {
			$row_title = $item['message_trigger'];
		}

		return $row_title . $this->tlr_row_actions( $item['ID'], $actions );
	}

	/**
     * Method for rendering the recipient column.
     * Adds row action links to the recipient column.
     *
     * @param array $item
     * @return string
     */
	protected function column_recipient( $item )
	{
		$member_id = intval($item['message_member_id']);

		$row_value = "<span>{$item['message_recipient']}</span>";
		if ( $member_id > 0 ) {
			$member_url = esc_url( admin_url( "admin.php?page=tlr_edit_member&action=edit&member=$member_id" ) );
			$row_value = "<a href='$member_url' target='_blank'>$row_value</span></a>";
		}

		return $row_value;
	}

	protected function column_gateway( $item )
    {
        return isset($this->gateways[$item['message_gateway']]['title'])
            ? $this->gateways[$item['message_gateway']]['title'] : $item['message_gateway'];
    }

    protected function column_interface( $item )
    {
        if ( isset( $this->gateways[$item['message_gateway']]['interfaces'][$item['message_interface']] ) ) {
            return $this->gateways[$item['message_gateway']]['interfaces'][$item['message_interface']];
        }
	    return $item['message_interface'];
    }

	protected function column_status( $item )
    {
        $statuses = [
                'pending'   =>  _x( 'Pending', 'message status', 'texteller' ),
                'sent'      =>  _x( 'Sent', 'message status', 'texteller' ),
                'failed'    =>  _x( 'Failed', 'message status', 'texteller' ),
                'delivered' =>  _x( 'Delivered', 'message status', 'texteller' ),
                'received'  =>  _x( 'Received', 'message status', 'texteller' )
        ];

        return isset($statuses[$item['message_status']]) ? $statuses[$item['message_status']] : ucfirst($item['message_status']);
    }

    protected function column_default( $item, $column_name )
	{
		return 'ID' === $column_name ? $item[$column_name] : $item['message_'.$column_name];
	}

	protected function column_date( $item )
    {
	    return self::format_datetime($item['message_date']);
    }
}