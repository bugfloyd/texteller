<?php

namespace Texteller\Admin;
use Texteller as TLR;

defined( 'ABSPATH' ) || exit;

class Member {

	private static $instance = null;

	private $action;
	private $member_id = 0;

	/**
	 * @var \WP_Error
	 */
	private $notices = null;

	private function __construct()
	{
		$action = ! empty( $_GET['action'] ) ? $_GET['action'] : 'new';
		$member_id = ! empty( $_GET['member'] ) ? (int) $_GET['member'] : 0;

		$this->member_id = (int) $member_id;
		$this->action    = in_array( $action, [ 'list', 'new', 'edit' ], true ) ? $action : 'list';
	}

	public function init()
	{
		if ( ! $this->member_id && 'new' !== $this->action ) {
			wp_safe_redirect( admin_url('admin.php?page=tlr-members') );
			exit;
		}

		if ( $this->member_id && 'new' === $this->action ) {
			wp_safe_redirect( add_query_arg('action', 'edit') );
			exit;
		}

		switch ( $this->action ) {

			case 'new':
			case 'edit':
			    add_filter( 'wp_dropdown_users', [self::class, 'dropdown_users_callback'] );
                $this->notices = new \WP_Error;

                $this->get_session_notices();

                $this->save();
                $this->render_html();
                break;

			case 'list':
            default:
	            wp_safe_redirect( admin_url('admin.php?page=tlr-members') );
	            exit;
		}
	}

	private function get_session_notices()
    {
	    if (!session_id() && !headers_sent() ) {
		    session_start();
	    }

        if ( ! empty( $_SESSION['tlr_notice'] ) && $_SESSION['tlr_notice'] > 0 ) {
            $notice_id = $_SESSION['tlr_notice'];

            $notices = [
                    101 =>  [
                            'code'  =>  'tlr_success_member_create',
                        'message'   =>  __('Member created successfully', 'texteller' )
                    ]
            ];

            if ( isset($notices[$notice_id]) ) {
	            $this->notices->add( $notices[$notice_id]['code'], $notices[$notice_id]['message'] );
            }

	        $_SESSION['tlr_notice'] = 0;
        }
    }

	/**
	 * Callback for wp_dropdown_users
	 **
	 * @param  string $output Current markup for output.
	 * @return string         Modified markup for output.
	 */
	public static function dropdown_users_callback( $output )
	{
		$screen = get_current_screen();

		if ( 0 !== strpos( $screen->id, 'texteller' ) && false === strpos( $screen->id, 'tlr_' ) ) {
			return $output;
		}

		$member_id = ! empty( $_GET['member'] ) ? (int) $_GET['member'] : 0;
		$user_id = ! empty( $_POST['tlr_user_id'] ) ? (int) $_POST['tlr_user_id'] : 0;

		if ( ! $user_id  ) {
			$user_id = TLR\tlr_get_user_id( $member_id );
		}

		$user       = $output ? get_userdata( $user_id ) : null;
		$display_name   = ! empty( $user ) ? $user->display_name : '';

		$selected_user = $user_id > 0 ? "<option selected='selected' value='$user_id'>$display_name</option>" : '';


		// return custom markup for
		return "<label for='tlr_user_id'>" . __('Link to a WordPress user', 'texteller') . "</label><select name='tlr_user_id' id='tlr_user_id' class='tlr-data-fields'>$selected_user</select>";
	}


	public static function register_meta_boxes( $screen, $action )
	{
		$member_id = ! empty ( $_GET['member'] ) ? (int) $_GET['member'] : 0;
		$member = new TLR\Member($member_id);

		add_meta_box(
			'tlr-member-data',
			__( 'Member Info', 'texteller' ),
			[ Meta_Boxes\Member_Data::class, 'render'],
			$screen,
			'normal',
			'high',
			$member
		);

		add_meta_box(
			'tlr-member-note',
			__( 'Member Note', 'texteller' ),
			[Meta_Boxes\Member_Note::class, 'render'],
			$screen,
			'normal',
			'default',
			$member
		);

		add_meta_box(
			'tlr-member-actions',
			__( 'Member actions', 'texteller' ),
			[Meta_Boxes\Member_Actions::class, 'render'],
			$screen,
			'side',
			'high',
			$member
		);

		$taxonomy = get_taxonomy( 'member_group' );
		$label = $taxonomy->labels->name;
		add_meta_box(
			'tlr-member-group',
			$label,
			$taxonomy->meta_box_cb,
			$screen,
			'side',
			'default',
			[$member,
				'taxonomy' => 'member_group']
		);

		if ( 'new' !== $action ) {
			add_meta_box(
				'tlr-member-send-message',
				__( 'Send Message', 'texteller' ),
				[Meta_Boxes\Member_Send_Message::class, 'render'],
				$screen,
				'side',
				'low',
				$member
			);

			add_meta_box(
				'tlr-member-number-info',
				__( 'Number Information', 'texteller' ),
				[Meta_Boxes\Member_Number_Info::class, 'render'],
				$screen,
				'side',
				'low',
				$member
			);

			add_meta_box(
				'tlr-member-history',
				__( 'Member recent message history', 'texteller' ),
				[ Meta_Boxes\Member_History::class, 'render'],
				$screen,
				'normal',
				'low',
				$member
			);
        }
	}

	private function save()
    {
        // Check action
        if ( ! isset( $_POST['tlr_action'] ) || 'tlr_save_member' !== $_POST['tlr_action'] ) {
            return;
        }

	    // Check if our nonce is set and verified.
	    if ( empty( $_POST['tlr_nonce'] ) || 1 !== wp_verify_nonce( $_POST['tlr_nonce'], 'tlr_member_manage_nonce' ) ) {
		    return;
	    }

	    // Check ID
	    if ( ! empty( $_POST['member_id'] ) && (int) $_POST['member_id'] !== $this->member_id ) {
		    return;
	    }

        // Check permissions todo
	    if ( ! current_user_can( 'manage_options' ) ) {
		    return;
	    }

	    $member_fields = [
		    'user_id'       =>  [
			    'enabled'   =>  'yes',
			    'id'        =>  'user_id',
			    'title'     =>  __('User ID', 'texteller'),
			    'required'  =>  0
		    ],
		    'mobile'    =>  [
			    'enabled'   =>  'yes',
			    'id'        =>  'mobile',
			    'title'     =>  __('Mobile Number', 'texteller'),
			    'required'  =>  0
		    ],
		    'status'    =>  [
			    'enabled'   =>  'yes',
			    'id'        =>  'status',
			    'title'     =>  __('Status', 'texteller'),
			    'required'  =>  0
		    ],
		    'email'     =>  [
			    'enabled'   =>  'yes',
			    'id'        =>  'email',
			    'title'     =>  __('Email', 'texteller'),
			    'required'  =>  0
		    ],
		    'first_name'    =>  [
			    'enabled'   =>  'yes',
			    'id'        =>  'first_name',
			    'title'     =>  __('First Name', 'texteller'),
			    'required'  =>  0
		    ],
		    'last_name' =>  [
			    'enabled'   =>  'yes',
			    'id'        =>  'last_name',
			    'title'     =>  __('Last Name', 'texteller'),
			    'required'  =>  0
		    ],
		    'title'    =>  [
			    'enabled'   =>  'yes',
			    'id'        =>  'title',
			    'title'     =>  __('Title', 'texteller'),
			    'required'  =>  0
		    ],
		    'member_group'   =>  [
			    'enabled'   =>  'yes',
			    'id'        =>  'member_group',
			    'title'     =>  __('Member Group', 'texteller'),
			    'required'  =>  0
		    ],
		    'description'   =>  [
			    'enabled'   =>  'yes',
			    'id'        =>  'description',
			    'title'     =>  __('Member Description', 'texteller'),
			    'required'  =>  0
		    ]
	    ];

	    // Get the instance of Register_User_Member
	    $register_member = TLR\Registration_Module::get_instance( $this->member_id );

	    // Validate and set data to Register_User_Member instance
	    $register_member->init(
	            $member_fields,
                $this->notices,
                [],
                'admin_edit',
                [ 'member_group' => [ 'tax_input' => 'member_group'] ]
        );

	    if ( $this->notices->get_error_codes() ) {
	        $codes = $this->notices->get_error_codes();
	        foreach ( $codes as $code ) {
		        if ( 0 === strpos( $code, 'tlr_error' ) ) {
			        return;
                }
            }
        }

	    // todo: on edit, don't change the origin
	    $register_member->set_member_field_value('reg_origin', 'tlr-dashboard' );

	    $existing_member_id = $register_member->get_member_field_value('id' );
	    $new_member_id = $register_member->save_member();

	    if ( 0 === $existing_member_id ) {
	        if ( $new_member_id ) {
		        $this->notices->add( 'tlr_success_member_create', __('Member has been successfully created.', 'texteller' ) );
		        $this->action = 'edit';

		        if (!session_id()) {
			        session_start();
		        }
		        $_SESSION['tlr_notice'] = 101;

		        wp_safe_redirect( add_query_arg( ['action' => 'edit', 'member' => $new_member_id] ) );
		        exit;
	        } else {
		        $this->notices->add( 'tlr_error_member_create', __('An error occurred while trying to create the member.', 'texteller' ) );
            }
        } else {

		    if ( ! empty( $_POST['tlr_member_action'] ) ) {
			    $action = $_POST['tlr_member_action'];

			    if ( 'verify' === $action ) {
				    $register_member->verify_member();
			    } elseif ( 'unverify' === $action ) {
				    $register_member->unverify_member();
			    } elseif ( 'cancel' === $action ) {
				    $register_member->cancel_member();
                } elseif ( 'uncancel' === $action ) {
				    $register_member->uncancel_member();
                }
		    }

		    if ( $new_member_id ) {
			    $this->notices->add( 'tlr_success_member_update', __( 'Member has been successfully updated.', 'texteller' ) );
		    } else {
			    $this->notices->add( 'tlr_error_member_update', __('An error occurred while trying to update the member.', 'texteller' ) );
		    }
        }
    }

	private function render_html()
	{
		global $current_screen;

		self::register_meta_boxes( $current_screen, $this->action );


		$page_title = 'new' === $this->action ? __('Add New Member', 'texteller') :  __('Edit Member', 'texteller');

		$register_member = TLR\Registration_Module::get_instance( $this->member_id );
		$locations     = [ 'side', 'normal' ];

		?>
        <div class="wrap">
            <h1>
                <?php
                echo $page_title;
                if ( 'new' !== $this->action ) {
                    echo '<a href="'
                         . esc_url( add_query_arg( ['action' => 'new'], admin_url( 'admin.php?page=tlr_edit_member' ) ) ) . '" class="add-new-h2">'
                        . __('Add New', 'texteller') . '</a>';
                }
                ?>
            </h1>
            <?php
            $this->render_notices();
            ?>
            <div id="metaboxes">
                <form  method="POST" class="metabox-base-form">
                    <?php $this->render_hidden_fields( $register_member ); ?>
                    <div id="poststuff" class="sidebar-open">
                        <div id="post-body" class="metabox-holder columns-2">
                            <?php foreach ( $locations as $location ) : ?>
                            <div id="postbox-container-<?= 'normal' === $location ? '2' : '1'; ?>" class="postbox-container">
                                <?php
                                do_meta_boxes(
                                    $current_screen,
                                    $location,
	                                $register_member
                                );
                                ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
		<?php
	}

	private function render_notices()
	{
		if ( ! $this->notices->has_errors() ) {
			return;
		}

		foreach ( (array) $this->notices->errors as $code => $messages ) {
			$class =  'error';
			if ( 0 === strpos( $code, 'tlr_' ) ) {
			    $class = substr( $code, 4 );
				$class = substr( $class, 0, strpos( $class, '_' ) );
            }
			foreach ( (array) $messages as $message ) {
				echo $message ? "<div class='notice notice-$class is-dismissible'><p>$message</p></div>" : '';
            }
			$this->notices->remove( $code );
		}
	}

	/**
	 * Renders the hidden form required for the meta boxes form.
	 *
	 * @since 5.0.0
	 *
	 * @param TLR\Registration_Module $register_member Current member object.
	 */
	private function render_hidden_fields( $register_member )
    {

        // todo: use this nonce:
		$nonce_action = 'update-member_' . $register_member->get_member_field_value( 'id' );
		$current_user = wp_get_current_user();
		wp_nonce_field( $nonce_action );
		?>
        <input type="hidden" id="member-id" name="member_id" value="<?php echo esc_attr( $register_member->get_member_field_value( 'id' ) ); ?>">
        <input type="hidden" id="user-id" name="user_id" value="<?php echo (int) $current_user->ID; ?>">
		<?php
	}

	public static function get_instance()
	{
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}