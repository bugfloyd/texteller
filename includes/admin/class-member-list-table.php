<?php

namespace Texteller\Admin;
use Texteller as TLR;

defined( 'ABSPATH' ) || exit;

class Member_List_Table extends Object_List_Table_Abstract
{
	protected static $instance = null;

	private $reg_origins = [];

	public function __construct()
	{
		$this->set_tabs( [
			'all'           =>  [
			        _x( 'All', 'members table tab', 'texteller' ),
                TLR\Object_Query::get_members_count()
            ],
			'registered'    =>  [
			        _x( 'Registered', 'members table tab', 'texteller' ),
                TLR\Object_Query::get_members_count('registered')
            ],
			'verified'      =>  [
			        _x( 'Verified', 'members table tab', 'texteller' ),
				TLR\Object_Query::get_members_count('verified')
            ],
            'canceled'      =>  [
                    _x( 'Canceled', 'members table tab', 'texteller'),
	            TLR\Object_Query::get_members_count('canceled')
            ]
		] );

		parent::__construct( [
			'plural'	=>	'members',
			'singular'	=>  'member',
			'ajax'		=>  false,
		] );
	}

	protected function get_table_action_args() : array
	{
		return [ 'send', 'verify', 'verified', 'unverify', 'unverified', 'cancel', 'canceled', 'uncancel', 'uncanceled', 'delete', 'deleted' ];
	}

	public function get_columns()
	{
		$table_columns = [
			'mobile_no'     =>  __( 'Mobile', 'texteller' ),
			'email'         =>  __( 'Email', 'texteller' ),
			'name'	        =>  __( 'Name', 'texteller' ),
			'user_id'	    =>  __( 'User Login', 'texteller' ),
			'reg_origin'	=>  __( 'Registration Origin', 'texteller' ),
			'modified_date' =>  __( 'Last Modified', 'texteller' ),
			'status'        =>  __( 'Status', 'texteller' ),
		];
		return $this->tlr_get_columns($table_columns);
	}

	protected function get_sortable_columns()
	{
		$sortable_columns = [
			'ID'            =>  [ 'ID', true ],
			'reg_origin'    =>  [ 'reg_origin', true ],
			'modified_date' =>  [ 'modified_date', true ],
            'status'        =>  [ 'status', true ]
		];
		return $sortable_columns;
	}

	/*
	|--------------------------------------------------------------------------
	| Row and Bulk Actions
	|--------------------------------------------------------------------------
	*/

	public function get_bulk_actions()
	{
		$actions = [
			'send'      =>  __( 'Send Message', 'texteller' ),
            'verify'    =>  __( 'Verify', 'texteller' ),
            'unverify'  =>  __( 'Unverify', 'texteller' ),
            'cancel'    =>  __( 'Cancel Membership', 'texteller' ),
			'uncancel'  =>  __( 'Revoke Cancellation', 'texteller' ),
			'delete'    =>  __( 'Delete', 'texteller' )
		];
		return $actions;
	}

	public function handle_table_actions( string $sendback )
	{
		$the_table_action = $this->current_action();
		$member_ids = $this->object_ids;
		$nonce = wp_unslash( $_REQUEST['_wpnonce'] );

		if ( empty($the_table_action) || empty($member_ids) || empty($nonce) ) {
			return null;
		}

		switch ( $the_table_action ) {
            case 'verify':
                return $this->verify_members( $member_ids, $nonce, $sendback );
                break;
            case 'unverify':
                return $this->unverify_members( $member_ids, $nonce, $sendback );
                break;
            case 'cancel':
                return $this->cancel_members( $member_ids, $nonce, $sendback );
                break;
            case 'uncancel':
	            return $this->uncancel_members( $member_ids, $nonce, $sendback );
                break;
            case 'send':
	            $this->send_message( $member_ids, $nonce );
                break;
		}
		return null;
	}

	private function send_message( array $member_ids, $nonce )
    {
	    if ( ! wp_verify_nonce( $nonce, 'bulk-members' ) ) {
		    $this->add_notices('error', 'An error occurred.' );
		    return;
	    }

	    $url = admin_url('admin.php?page=texteller');
	    foreach ( $member_ids as $member_id ) {
		    $url = add_query_arg( [
			    'member[]'    =>  $member_id,
		    ], $url );
	    }

	    wp_safe_redirect( $url );
	    exit;
    }

	private function verify_members( array $member_ids, string $nonce, string $sendback )
    {
	    if ( ! wp_verify_nonce( $nonce, 'tlr-verify-single-member' )
	         && ! wp_verify_nonce( $nonce, 'bulk-members' )
	    ) {
		    $this->add_notices('error', 'An error occurred.' );
		    return null;
	    }

	    if ( current_user_can('manage_options' ) ) {
	        $di = 0;
		    foreach ( $member_ids as $member_id ) {
			    $member = new TLR\Member( $member_id );
			    if( $member->is_verified() || $member->is_canceled() || $member->get_id() !== $member_id ) {
				    continue;
			    }
			    $member->verify();
			    $di ++;
		    }
		    if ( $di ) {
			    $args = [ 'verified' => $di ];
			    return add_query_arg( $args, $sendback );
		    } else {
		        return null;
            }
	    } else {
	        return add_query_arg( ['verified' => 'not_allowed'], $sendback );
        }
    }

    private function unverify_members( $member_ids, $nonce, $sendback )
    {
	    if ( ! wp_verify_nonce( $nonce, 'tlr-unverify-single-member' )
	         && ! wp_verify_nonce( $nonce, 'bulk-members' )
	    ) {
		    return null;
	    }

	    if ( current_user_can('manage_options' ) ) {
		    $di = 0;
		    foreach ( $member_ids as $member_id ) {
			    $member = new TLR\Member( $member_id );
			    if( ! $member->is_verified() || $member->is_canceled() || $member->get_id() !== $member_id ) {
				    continue;
			    }
			    $member->unverify();
			    $di ++;
		    }
		    if ( $di ) {
			    $args = [ 'unverified' => $di ];
			    return add_query_arg( $args, $sendback );
		    } else {
		        return null;
            }
	    } else {
		    return add_query_arg( ['unverified' => 'not_allowed'], $sendback );
	    }
    }

	private function cancel_members( array $member_ids, string $nonce, string $sendback )
	{
		if ( ! wp_verify_nonce( $nonce, 'tlr-cancel-single-member' )
		     && ! wp_verify_nonce( $nonce, 'bulk-members' )
		) {
			return null;
		}

		if ( current_user_can('manage_options' ) ) {
			$di = 0;
			foreach ( $member_ids as $member_id ) {
				$member = new TLR\Member( $member_id );
				if( $member->is_canceled() || $member->get_id() !== $member_id ) {
					continue;
				}
				$member->cancel();
				$di ++;
			}
			if ( $di ) {
				return add_query_arg( [ 'canceled' => $di ], $sendback );
			} else {
				return null;
			}
		} else {
			return add_query_arg( [ 'canceled' => 'not_allowed' ], $sendback );
		}
	}

	private function uncancel_members( array $member_ids, string $nonce, string $sendback )
	{
		if ( ! wp_verify_nonce( $nonce, 'tlr-uncancel-single-member' )
		     && ! wp_verify_nonce( $nonce, 'bulk-members' )
		) {
			return null;
		}

		if ( current_user_can('manage_options' ) ) {
			$di = 0;
			foreach ( $member_ids as $member_id ) {
				$member = new TLR\Member( $member_id );
				if( ! $member->is_canceled() ) {
					continue;
				}
				$member->uncancel();
				$di ++;
			}

			if ( $di ) {
				return add_query_arg( [ 'uncanceled' => $di ], $sendback );
			} else {
				return null;
			}
		} else {
			return add_query_arg( [ 'uncanceled' => 'not_allowed' ], $sendback );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Get Members
	|--------------------------------------------------------------------------
	*/

	public function prepare_items()
	{
        // Used by WordPress to build and fetch the _column_headers property
		$this->_column_headers = $this->get_column_info();

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
			'object_type' => 'member'
		];

		// Tab (status) query
		if ( ! empty( $_REQUEST['tab'] ) && 'all' !== $_REQUEST['tab'] ) {
			$args['statuses'] = [ sanitize_text_field( $_REQUEST['tab'] ) ];
		}

		// Member group query
		if ( ! empty( $_REQUEST['member_group'] ) ) {
			$args['member_groups'] = [ sanitize_text_field($_REQUEST['member_group']) ];
		}

		// Registration origins
		if ( ! empty( $_REQUEST['reg_origin'] ) ) {
			$args['reg_origins'] = [ sanitize_text_field( $_REQUEST['reg_origin'] ) ];
		}

		// Title
		if ( ! empty( $_REQUEST['title'] ) ) {
			$args['titles'] = [ sanitize_text_field( $_REQUEST['title'] ) ];
		}

		// User linked
		if ( isset( $_REQUEST['user_linked'] ) ) {
			$args['user_linked'] = (bool) $_REQUEST['user_linked'];
		}

		// Mobile query
		/*if ( !empty($_GET['mobile']) ) {
			$where[] = "mobile = '+" . esc_sql( $_GET['mobile'] ) . "'";
		}*/ // todo: use it in tlr_get_member_id function

		$tlr_query = new TLR\Object_Query( $args );
		return $tlr_query->get_members();
	}

	protected function read_notices()
    {
	    parent::read_notices();

	    if ( isset($_REQUEST['verified']) || isset($_REQUEST['verified']) ) {
	        $verified = $_REQUEST['verified'];
		    if ( 'not_allowed' !== $verified && $verified > 0 ) {
			    if( 1 === $verified ) {
				    $notice_content = __( 'One member has been successfully verified.', 'texteller' );
			    } else {
				    $notice_content = sprintf(
					    __( '%d members have been successfully verified.', 'texteller' ),
					    $verified
				    );
			    }
			    $this->add_notices('success', $notice_content);
		    } else {
			    $this->add_notices(
				    'error',
				    __('You are not allowed to verify members.','texteller')
			    );
            }
	    } elseif ( isset($_REQUEST['unverified']) || isset($_REQUEST['unverified']) ) {
		    $unverified = $_REQUEST['unverified'];
		    if ( 'not_allowed' !== $unverified && $unverified > 0 ) {
			    if( 1 === $unverified ) {
				    $notice_content = __( 'One member has been successfully unverified.', 'texteller' );
			    } else {
				    $notice_content = sprintf(
					    __( '%d members have been successfully unverified.', 'texteller' ),
					    $unverified
				    );
			    }
			    $this->add_notices('success', $notice_content);
		    } else {
			    $this->add_notices(
				    'error',
				    __( 'You are not allowed to unverify members.','texteller' )
			    );
		    }
	    } elseif ( isset($_REQUEST['canceled']) || isset($_REQUEST['canceled']) ) {
		    $canceled = $_REQUEST['canceled'];
		    if ( 'not_allowed' !== $canceled && $canceled > 0 ) {
			    if( 1 === $canceled ) {
				    $notice_content = __( 'One membership has been successfully canceled.', 'texteller' );
			    } else {
				    $notice_content = sprintf(
					    __( '%d memberships have been successfully canceled.', 'texteller' ),
					    $canceled
				    );
			    }
			    $this->add_notices('success', $notice_content);
		    } else {
			    $this->add_notices(
				    'error',
				    __( 'You are not allowed to cancel memberships.','texteller' )
			    );
		    }
	    } elseif ( isset($_REQUEST['uncanceled']) || isset($_REQUEST['uncanceled']) ) {
		    $uncanceled = $_REQUEST['uncanceled'];
		    if ( 'not_allowed' !== $uncanceled && $uncanceled > 0 ) {
			    if( 1 === $uncanceled ) {
				    $notice_content = __( 'One membership cancellation has been successfully revoked.', 'texteller' );
			    } else {
				    $notice_content = sprintf(
					    __( '%d membership cancellations have been successfully revoked.', 'texteller' ),
					    $uncanceled
				    );
			    }
			    $this->add_notices('success', $notice_content);
		    } else {
			    $this->add_notices(
				    'error',
				    __( 'You are not allowed to revoke membership cancellations.','texteller' )
			    );
		    }
	    }
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
	    $url = esc_url( add_query_arg( ['action' => 'new', 'page' => 'tlr_edit_member' ], admin_url( 'admin.php' ) ) );
		$new_sms_button = "<a href='$url' class='add-new-h2'>" . __('Add New Member', 'texteller') . '</a>';

		echo '<h1>' . __( 'Members', 'texteller' ) . " $new_sms_button</h1>";
	}

	protected function extra_tablenav( $which )
	{
		if ($which === 'top') {
			?>
			<div class="alignleft actions message-filters-wrap">
				<?php

				/**
				 * Member Group Filter
				 */
				$member_groups = get_terms( [ 'taxonomy'  =>  'member_group' ] );
				if( is_array($member_groups) && !empty($member_groups) ){
					?>
					<div class="member-group-filter-wrap">
						<label for="member-group-filter" class="screen-reader-text">
                            <?= __('Filter members by member group','texteller'); ?>
                        </label>
						<select id="member-group-filter" class="tlr-filter">
							<option value="<?= esc_url( remove_query_arg(['paged', 'member_group']) ); ?>">
                                <?= __('All member groups','texteller'); ?>
                            </option>
							<?php
							foreach( $member_groups as $member_group ){
								$selected = isset($_REQUEST['member_group']) ?
                                    selected( $member_group->slug, $_REQUEST['member_group'], false ) : '';
								?>
                                <option value="<?= esc_url(
                                        remove_query_arg(
                                                'paged',
                                                add_query_arg( ['member_group' => $member_group->slug], $this->base_url )
                                        )
                                ); ?>"<?= $selected; ?>>
                                <?php echo $member_group->name; ?>
                                </option><?php
							}
							?>
						</select>
					</div>
					<?php
				}

				/**
				 * Registration Origin Filter
				 */
				?>
                <div class="reg-origin-filter-wrap">
                    <label for="reg-origin-filter" class="screen-reader-text">
						<?= __('Filter members by registration origin','texteller'); ?>
                    </label>
                    <select id="reg-origin-filter" class="tlr-filter">
                        <option value="<?= esc_url( remove_query_arg(['paged', 'reg_origin']) ); ?>">
							<?= __('All Registration Origins','texteller'); ?>
                        </option>
						<?php
						$origins = apply_filters( 'tlr_registration_origins', [] );
						if ( !empty( $origins ) && is_array( $origins ) ) {
							$this->reg_origins = $origins;
							foreach ( $origins as $key => $title ) {
								$selected = isset($_REQUEST['reg_origin']) ?
									selected( $key, $_REQUEST['reg_origin'], false ) : '';
								$url = esc_url( remove_query_arg(
									'paged',
									add_query_arg( ['reg_origin' => $key], $this->base_url )
								) );
								?>
                                <option value="<?= $url; ?>"<?= $selected; ?>><?= $title; ?></option><?php
							}
						}
						?>
                    </select>
                </div>
                <?php

                /**
                 * Title Filter
                 */
                ?>
                <div class="title-filter-wrap">
                    <label for="title-filter" class="screen-reader-text">
						<?= __('Filter members by title','texteller'); ?>
                    </label>
                    <select id="title-filter" class="tlr-filter">
                        <option value="<?= esc_url( remove_query_arg(['paged', 'title']) ); ?>">
							<?= __('All Titles','texteller'); ?>
                        </option>
						<?php
						$titles = [ 'ms', 'mrs', 'miss', 'mr' ];
						foreach ( $titles as $title ) {
							$selected = isset($_REQUEST['title']) ?
								selected( $title, $_REQUEST['title'], false ) : '';
							$url = esc_url( remove_query_arg('paged', add_query_arg( ['title' => $title], $this->base_url) ) );
							?>
                            <option value="<?= $url; ?>"<?= $selected; ?>><?= ucfirst($title); ?></option><?php
						}
						?>
                    </select>
                </div>
                <?php

                /**
                 * User Filter
                 */
                ?>
                <div class="user-linked-filter-wrap">
                    <label for="user-linked-filter" class="screen-reader-text">
						<?= __('Filter members with or without a user linked to them','texteller'); ?>
                    </label>
                    <select id="user-linked-filter" class="tlr-filter">
                        <option value="<?= esc_url( remove_query_arg([ 'paged','user_linked']) ); ?>">
							<?= __('User Correlation Status','texteller'); ?>
                        </option>
						<?php
						$linked_user = isset($_REQUEST['user_linked']) ? $_REQUEST['user_linked'] : '';
						?>
                        <option value="<?=
						esc_url(remove_query_arg('paged', add_query_arg( ['user_linked' => '0'], $this->base_url ) ) );
						?>"<?php selected( '0', $linked_user); ?>>
							<?= __( 'No user is linked', 'texteller' ); ?>
                        </option>
                        <option value="<?=
						esc_url( remove_query_arg('paged', add_query_arg( ['user_linked' => '1'], $this->base_url ) ) );
						?>"<?php selected( '1', $linked_user); ?>>
							<?= __( 'Linked to a user', 'texteller' ); ?>
                        </option>
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

	protected function column_mobile_no( $item )
	{
	    /**
         * Row Actions
         **/

		// Send
		$send_link = esc_url( add_query_arg( [
			'page'      =>  'texteller',
			'member[]'  =>  $item['ID'],
		], admin_url('admin.php') ) );
		$actions['send'] = '<a href="' . $send_link . '">' . __('Send','texteller') . '</a>';

		if( current_user_can('manage_options') ) {
			// Edit
			$edit_member_link = esc_url( add_query_arg( [
				'page'		=>  'tlr_edit_member',
				'action'	=>  'edit',
				'member'	=>  $item['ID'],
			], admin_url('admin.php') ) );

			$actions['edit'] = '<a href="' . $edit_member_link . '">' . __('Edit','texteller') . '</a>';

			// Verify
			if ( ! in_array( $item['status'], [ 'verified', 'canceled' ], true ) ) {
				$verify_link = esc_url( add_query_arg( [
					'action'	=>  'verify',
					'member'	=>  $item['ID'],
					'_wpnonce'	=>  wp_create_nonce( "tlr-verify-single-member" ),
				], $this->base_url ) );

				$actions['verify'] = "<a href='$verify_link'>" . __( 'Verify', 'texteller' ) . '</a>';
			}

			// Unverify
			if ( 'verified' === $item['status'] ) {
				$cancel_url = esc_url( add_query_arg( [
					'action'	=>  'unverify',
					'member'	=>  $item['ID'],
					'_wpnonce'	=>  wp_create_nonce( "tlr-unverify-single-member" )
				], $this->base_url ) );

				$actions['unverify'] = "<a href='$cancel_url'>" . __( 'Unverify', 'texteller' ) . '</a>';
			}

			// Cancel Membership
			if ( 'canceled' !== $item['status'] ) {
				$cancel_url = esc_url( add_query_arg( [
					'action'	=>  'cancel',
					'member'	=>  $item['ID'],
					'_wpnonce'	=>  wp_create_nonce( "tlr-cancel-single-member" )
				], $this->base_url ) );

				$actions['cancel'] = "<a href='$cancel_url'>" . __( 'Cancel', 'texteller' ) . '</a>';
			} else {
				$revoke_url = esc_url( add_query_arg( [
					'action'	=>  'uncancel',
					'member'	=>  $item['ID'],
					'_wpnonce'	=>  wp_create_nonce( "tlr-uncancel-single-member" )
				], $this->base_url ) );

				$actions['uncancel'] = "<a href='$revoke_url'>" . __( 'Revoke Cancellation', 'texteller' ) . '</a>';
			}
        }

		/**
		 * Row Title
		 */
		$mobile = ! empty( $item['mobile'] ) ? $item['mobile'] : __('(no number)', 'texteller') ;
		if ( isset($edit_member_link) ) {
			$row_title = "<a href='$edit_member_link'><span>$mobile</span></a>";
		} else {
			$row_title = "<span>$mobile</span>";
        }

		return $row_title . $this->tlr_row_actions( $item['ID'], $actions );
	}

	protected function column_name( $item )
	{
		if ( ! empty( $item['first_name'] ) && !empty( $item['last_name'] ) ) {
			return $item['first_name'] . ' ' . $item['last_name'];
		} elseif ( !empty( $item['last_name'] ) ) {
			return $item['last_name'];
		} elseif ( !empty( $item['first_name'] ) ) {
			return $item['first_name'];
		}
		return '';
	}

	protected function column_user_id( $item )
	{
		if ( isset( $item['user_id'] )  && (int) $item['user_id'] > 0 ) {

			$user_url = esc_url( add_query_arg( [ 'user_id' => $item['user_id'] ], admin_url('user-edit.php') ) );

			$user = get_userdata( $item['user_id'] );
			if ( $user ) {
				$text =  $user->user_login;
			} else {
				$text = '-';
			}

			return "<a href='$user_url' target='_blank'>$text</a>";

		} else {
		    return '-';
		}
	}

	protected function column_reg_origin( $item )
	{
	    if ( isset( $this->reg_origins[$item['reg_origin']] ) ) {
	        return $this->reg_origins[$item['reg_origin']];
        } else {
	        return $item['reg_origin'];
        }
	}

	protected function column_status( $item )
	{
		return __( ucfirst( $item['status'] ), 'texteller' );
	}

	protected function column_modified_date( $item )
    {
	    return self::format_datetime($item['modified_date']);
    }
}