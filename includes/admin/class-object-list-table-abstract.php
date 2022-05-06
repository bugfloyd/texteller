<?php

namespace Texteller\Admin;
use Texteller as TLR;

defined( 'ABSPATH' ) || exit;

require_once TLR_LIBS_PATH . '/class-wp-list-table.php';

abstract class Object_List_Table_Abstract extends TLR\WP_List_Table implements TLR\Interfaces\Object_List_Table
{
	use TLR\Traits\DateTime;

	protected static $instance = null;

	protected $tabs = [];

	protected $notices = [];

	protected $hidden_columns = [ 'ID' ];

	protected $object_ids = [];

	protected $base_url = '';

	public function __construct( $args = [] )
	{
		parent::__construct( $args );
		add_filter( 'default_hidden_columns', [ $this, 'hide_columns' ], 10, 2 );

		$this->set_base_url();
		$this->init_actions();
		self::init_datetime_formatter();
	}

	protected function set_base_url()
    {
        if ( false !== strpos($_SERVER['QUERY_STRING'], "page=tlr-{$this->_args['plural']}" ) ) {
            $base_url = "admin.php?{$_SERVER['QUERY_STRING']}";
        } else {
	        $base_url =  "admin.php?page=tlr-{$this->_args['plural']}";
        }

	    $args = array_merge( [ $this->_args['singular'], 'action', 'action2', '_wpnonce', '_wp_http_referer' ], $this->get_table_action_args() );
	    //$referer  = strtolower( wp_get_referer() );
	    $sendback = remove_query_arg( $args, admin_url( $base_url ) );

	    $sendback = add_query_arg( 'paged', $this->get_pagenum(), $sendback );
	    $this->base_url = $sendback;
    }

	/**
     * @return array
	 */
	protected function get_table_action_args() : array
    {
	    return [];
    }

	public function hide_columns( $hidden, $screen )
	{
		if( $this->screen->id === $screen->id ){
			$hidden =  array_merge( $hidden, $this->hidden_columns );
		}
		return $hidden;
	}

	public function init_actions()
    {
        if (
                ! $this->current_action()
                || ! in_array( $this->current_action(), $this->get_table_action_args() )
                || ! $this->base_url
        ) {
            return;
        }

	    $sendback = $this->base_url;
	    $object_ids = $this->get_object_ids();
	    if ( empty( $object_ids ) ) {
		    wp_redirect( $sendback );
		    exit;
	    }

	    $deleted_args = $this->handle_objects_delete($sendback);
	    if ( null !== $deleted_args ) {
		    $sendback = $deleted_args;
        } else {
		    $modified_args = $this->handle_table_actions($sendback); // Defined in final classes
		    if ( null !== $modified_args ) {
			    $sendback = $modified_args;
		    }
        }

	    wp_redirect( $sendback );

    }

	public function render_table()
	{
		/**
		 * Get items
		 */
		// query, filter, and sort the data
		$this->prepare_items(); // Defined in final classes
        $this->read_notices();
		/**
		 * Render the table
		 */
		$this->display_notices();

		echo '<div class="wrap">';
		$this->display_header(); // Defined in final classes
		$this->display_search_result_label();
		$this->views();
		$this->display_table();
		echo '</div>';
	}

	protected function read_notices()
    {
	    if ( isset( $_REQUEST['deleted']) ) {
		    $deleted = isset( $_REQUEST['deleted']) ? sanitize_text_field( wp_unslash($_REQUEST['deleted']) ) : 0;

		    if ( 'not_allowed' !== $deleted && $deleted > 0 ) {
			    if( 1 == $deleted ) {
				    $notice_content = sprintf(
					    __( 'One %s has been successfully deleted.', 'texteller' ), $this->_args['singular']
				    );
			    } else {
				    $notice_content = sprintf(
					    __( '%d %s has been successfully deleted.', 'texteller' ),
					    $deleted,
					    $this->_args['plural']
				    );
			    }
			    $this->add_notices('success', $notice_content);
		    } elseif( 'not_allowed' === $deleted ) {
			    $this->add_notices(
				    'error',
				    sprintf( __('You are not allowed to delete %s.','texteller'), $this->_args['plural'] )
			    );
		    }
	    }
    }

	protected function handle_objects_delete($sendback)
    {
	    if ( 'delete' !== $this->current_action() || empty($this->object_ids) || !isset($_REQUEST['_wpnonce']) ) {
		    return null;
	    }

	    $nonce = wp_unslash( $_REQUEST['_wpnonce'] );
	    if ( ! wp_verify_nonce( $nonce, "tlr-delete-single-{$this->_args['singular']}" )
		          && ! wp_verify_nonce( $nonce, "bulk-{$this->_args['plural']}" )
	    ) {
		    return null;
	    }

	    if ( current_user_can('manage_options' ) ) {
            $object_class = ucfirst( $this->_args['singular'] );
            $object_class = "\Texteller\\$object_class";

		    $di = 0;
            if ( class_exists( $object_class ) ) {

	            foreach ( $this->object_ids as $object_id ) {
		            /** @var TLR\Data $object */
		            $object = new $object_class( $object_id );
		            if( $object->get_id() !== $object_id ) {
		                continue; // member does not exist (Request is already processed.)
                    }
		            $deleted = $object->delete();

		            if ( $deleted ) {
			            $di ++;
		            } else {
			            TLR\tlr_write_log( sprintf( "An error occurred while trying to delete {$this->_args['singular']} with ID %s", $object_id ) );
		            }
	            }
            }

            if ( $di ) {
	            return add_query_arg(
		            [ 'deleted' => $di ],
		            $sendback
	            );
            } else {
                return null;
            }

	    } else {
		    return add_query_arg(
			    [ 'deleted' => 'not_allowed' ],
			    $sendback
		    );
	    }
	}

	private function get_object_ids()
    {
        $object_ids = [];
	    if ( isset( $_REQUEST[ $this->_args['singular'] ] ) ) {
		    if ( is_array( $_REQUEST[ $this->_args['singular'] ] ) ) {
			    $object_ids = array_map( function( $object_id ) {
				    return intval( $object_id );
			    }, $_REQUEST[ $this->_args['singular'] ] );
		    } else {
			    $object_ids = [  intval( $_REQUEST[ $this->_args['singular'] ] ) ];
		    }
	    }
	    $this->object_ids = $object_ids;
	    return $object_ids;
    }

	/**
	 * Filters the table data based on the search keyword
	 *
	 * @param $table_data
	 * @param $search_key
	 *
	 * @return array
	 */
	public function filter_table_data( $table_data, $search_key )
	{
		$filtered_table_data = array_values(
			array_filter( $table_data, function( $row ) use( $search_key )
			{
				foreach( $row as $row_val ) {
					if( stripos( $row_val, $search_key ) !== false ) {
						return true;
					}
				}
				return false;
			} )
		);
		return $filtered_table_data;
	}

	/**
	 * Display the table heading and search query, if any
	 */
	protected function display_notices()
	{
		$admin_notices = $this->get_notices();

		if ( !$admin_notices ) {
		    return;
		}

		foreach ( $admin_notices as $notice ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $notice['class'] ), esc_html( $notice['message'] ) );
		}
	}

	protected function get_notices()
	{
		return $this->notices;
	}

	protected function add_notices( $class, $message )
	{
		if ( ! empty($class) && ! empty($message) ) {
			$this->notices[] = [
				'class'     =>  $class,
				'message'   =>  $message
			];
		}
	}

	public function display_search_result_label()
	{
		if ( $this->get_request_search() ) {
			echo '<div style="margin: 7px 0;"><span>' . esc_attr( sprintf( __('Search results for "%s"', 'texteller'), $this->get_request_search() ) ) . '</span></div>';
		}
	}

	protected function get_request_search() : string
	{
		return isset( $_REQUEST['s'] ) ? wp_unslash( trim( $_REQUEST['s'] ) ) : '';
	}

	protected function get_views()
	{
		$tabs = $this->get_tabs();
		$views = [];

		foreach ( $tabs as $tab_id => $tab_title ) {
		    $tab_title = is_array($tab_title) ? "{$tab_title[0]} ({$tab_title[1]})" : $tab_title;
		    $url = esc_url( remove_query_arg('paged', add_query_arg( ['tab' => $tab_id], $this->base_url) ) );
			$views[$tab_id] = "<a{$this->get_active_tab_class($tab_id)} href='$url'>$tab_title</a>";
		}

		return $views;
	}

	private function get_active_tab_class( $tab )
	{
		$active_tab = !empty( $_GET['tab'] ) ? $_GET['tab'] : 'all';

		if ( $active_tab === $tab  ) {
			return ' class="current"';
		} else {
			return '';
		}
	}

	/**
	 * @return array
	 */
	public function get_tabs(): array {
		return $this->tabs;
	}

	/**
	 * @param array $tabs
	 */
	public function set_tabs( array $tabs ) {
		$this->tabs = $tabs;
	}

	public function display_table()
	{
		$object_type = $this->_args['singular'];
		?>
		<div id="tlr-<?= $object_type; ?>s-table">
			<div id="tlr-post-body">
				<form id="tlr-<?= $object_type; ?>s-list-form" method="get">
					<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
					<?php
					$this->search_box( __('Search', 'texteller'), 'tlr-'. $object_type );
					$this->display();
					?>
				</form>
			</div>
		</div>
		<?php
	}

	public function tlr_get_columns( array $columns = [] )
	{
		$table_columns = array_merge( [ 'cb' => '<input type="checkbox"/>' ], $columns, [ 'ID' => __('ID', 'texteller') ] );
		return $table_columns;
	}

	protected function column_default( $item, $column_name )
	{
		return $item[$column_name];
	}

	protected function column_cb( $item )
	{
		$object_name = $this->_args['singular'];
		return sprintf(
			"<label class='screen-reader-text' for='{$object_name}_" . $item['ID'] . "'>" . sprintf( __( 'Select member with ID %s' ), $item['ID'] ) . '</label>'
			. "<input type='checkbox' name='{$object_name}[]' id='{$object_name}_{$item['ID']}' value='{$item['ID']}' />"
		);
	}

	public function tlr_row_actions( $object_id, $actions )
    {
	    /**
	     * Action: Delete Object
	     */
	    $delete_link = esc_url(
	            add_query_arg(
	                    [
	                            'page'                      =>  "tlr-{$this->_args['plural']}",
	                            'action'	                =>  'delete',
	                            $this->_args['singular']	=>  intval( $object_id ),
                                '_wpnonce'	                =>  wp_create_nonce( "tlr-delete-single-{$this->_args['singular']}" )
                        ],
                        $this->base_url
                )
        );
	    $alert = sprintf( esc_html__( '%s with ID %d, will be deleted permanently.', 'texteller' ), ucfirst($this->_args['singular']), $object_id ) . '\n' . esc_html__('Are you sure?', 'texteller');
	    $actions['delete'] = "<a href='$delete_link' id='delete-member-$object_id'>"
	                         . __( 'Delete', 'texteller' )
	                         . '</a>'
	                         . "<script>jQuery('#delete-member-' + $object_id ).on('click', function() { return confirm( '$alert' ); });</script>";

	    return $this->row_actions( $actions );
    }

	public function no_items()
	{
		echo sprintf( esc_html__( 'No %s found.', 'texteller' ), $this->_args['plural'] );
	}

	/**
	 * @return self
	 */
	public static function get_instance()
	{
		if ( null === static::$instance ) {
			static::$instance = new static();
		}
		return static::$instance;
	}
}