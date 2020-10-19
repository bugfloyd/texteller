<?php

namespace Texteller\Core_Modules\WordPress;
use Texteller as TLR;
use Texteller\Member;
use function Texteller\tlr_get_member_id;

defined( 'ABSPATH' ) || exit;

/**
 * Class Notifications Handles WordPress notifications
 * @package Texteller\Core_Modules\WordPress
 */
final class Notifications
{
	/**
	 * Notifications constructor.
	 */
	public function __construct()
	{
		$this->init();
	}

	/**
	 * Initializes notification trigger hooks
	 */
	public function init()
	{
		add_action( 'retrieve_password_key', [self::class,'new_user_rp_notification'],10 ,2 );
		add_action( 'tlr_wp_reset_password_notification', [self::class, 'send_lost_user_rp_link'], 10, 3 );
		add_action( 'tlr_wp_member_registered', [self::class,'wp_login_new_user_registered'], 10, 2  );
		add_action( 'rest_after_insert_post', [self::class, 'rest_post_updated'] );
		add_action( 'post_updated', [self::class, 'post_updated'], 10, 3 );
		add_action( 'save_post_post', [self::class, 'save_post'], 10, 3 );
		add_action( 'comment_post', [self::class,'comment_posted'], 10, 3 );
		add_action( 'transition_comment_status', [self::class, 'comment_approved'], 10, 3 );
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
	public static function set_tags_values( string $tag_type, TLR\Tags &$tags, $object, array $extra_tags )
	{
		switch($tag_type) {
			case 'user':
				$member_tags = TLR\tlr_get_base_tags_values_array('member', $object);
				$user_tag_values = [
					'login_link'    =>  wp_login_url(),
				];
				$tags->add_tag_type_data('user', array_merge( $member_tags, $user_tag_values ) );
				break;

			case 'draft_member':
				$member_tags = TLR\tlr_get_base_tags_values_array('member', $object);
				if( isset( $member_tags['member_id'] ) ) {
					unset( $member_tags['member_id'] );
				}
				$user = get_user_by('login', $extra_tags['user_login'] );
				$rp_link = '';
				if ( isset( $extra_tags['rp_key'] ) && isset( $extra_tags['user_login'] ) ) {
					$rp_link = network_site_url(
						"wp-login.php?action=rp&key={$extra_tags['rp_key']}&login=" . rawurlencode( $extra_tags['user_login'] ),
						'login'
					);
				}
				$new_user_tag_values = [
					'login_link'        =>  wp_login_url(),
					'rp_link'           =>  $rp_link,
					'member_user_id'    =>  $user->ID,
					'member_username'   =>  $user->user_login,
					'member_reg_date'   =>  $user->user_registered,
					'member_status'     =>  _x( 'registered','replacement text for the {status} tag', 'texteller' ),
				];
				$tags->add_tag_type_data('draft_member', array_merge( $member_tags, $new_user_tag_values ) );
				break;

			case 'lost_user':
				$member_tags = TLR\tlr_get_base_tags_values_array('member', $object);
				$lost_user_tags_values = [];
				$lost_user_tags_values['login_link'] = wp_login_url();
				if ( isset( $extra_tags['rp_key'], $extra_tags['user_id'], $extra_tags['user_login'] ) ) {
					$lost_user_tags_values['rp_link'] = network_site_url(
						"wp-login.php?action=rp&key={$extra_tags['rp_key']}&login=" . rawurlencode( $extra_tags['user_login'] ),
						'login'
					);
				}
				$tags->add_tag_type_data('lost_user', array_merge($member_tags, $lost_user_tags_values) );
				break;

			case 'post':
				$tags->add_tag_type_data( 'post', self::get_post_tags($object) );
				break;

			case 'comment':
				/** @var $object \WP_Comment */
				if ( 1 == $object->comment_approved ) {
					$status = __( 'Approved', 'texteller' );
				} elseif ( 'spam' === $object->comment_approved ) {
					$status = __( 'Spam', 'texteller' );
				} else {
					$status = __( 'Pending Approval', 'texteller' );
				}

				$parent_comment = \WP_Comment::get_instance($object->comment_parent);
				$comment_tags = [
					'comment_author'        =>  $object->comment_author,
					'comment_author_email'  =>  $object->comment_author_email,
					'comment_author_url'    =>  $object->comment_author_url,
					'comment_content'       =>  $object->comment_content,
					'comment_status'        =>  $status,
					'comment_parent'        =>  $parent_comment ? $parent_comment->comment_content : '',
					'comment_parent_author' =>  $parent_comment ? $parent_comment->comment_author : '',
					'comment_author_ip'     =>  $object->comment_author_IP,
					'comment_agent'         =>  $object->comment_agent,
					'comment_date'          =>  $object->comment_date,
				];
				$post_tags = self::get_post_tags( \WP_Post::get_instance($object->comment_post_ID) );
				$tags->add_tag_type_data('comment', array_merge($comment_tags, $post_tags) );
				break;
		}
	}

	/**
	 * @param \WP_Post $post
	 *
	 * @return array
	 */
	private static function get_post_tags( $post )
	{
		if ( ! $post->ID ) {
			return [];
		}

		$author = get_user_by('ID', $post->post_author );
		$cats = wp_get_post_categories( $post->ID, ['fields' => 'names'] );
		$cats = !is_wp_error($cats) ? implode(', ', $cats) : '';
		$tags = wp_get_post_tags( $post->ID, ['fields' => 'names'] );
		$tags = !is_wp_error($tags) ? implode(', ', $tags) : '';

		return [
			'post_id'           =>  $post->ID,
			'post_title'        =>  $post->post_title,
			'post_author'       =>  $author ? $author->display_name : '',
			'post_slug'         =>  $post->post_name,
			'post_url'          =>  wp_get_shortlink( $post->ID ),
			'post_date'         =>  $post->post_date,
			'post_excerpt'      =>  $post->post_excerpt,
			'post_status'       =>  $post->post_status,
			'comments_status'   =>  $post->comment_status,
			'comments_count'    =>  $post->comment_count,
			'post_cats'         =>  $cats,
			'post_tags'         =>  $tags
		];
	}

	/**
	 * Gets member IDs or mobile numbers for the current trigger recipient type and object
	 *
	 * @param   string    $trigger_recipient_type
	 * @param   object    $object
	 * @param   array     $trigger_recipient_args
	 *
	 * @return array
	 */
	public static function get_trigger_recipient_member_ids( $trigger_recipient_type, $object, $trigger_recipient_args )
	{
		$registration_origin = isset($trigger_recipient_args['registration_origin']) ? $trigger_recipient_args['registration_origin'] : '';

		switch($trigger_recipient_type) {

			case 'registered_user':
				if ( is_a($object,'\Texteller\Member') && in_array( $registration_origin, [ 'wp-login', 'wp-profile' ] ) ) {
					/** @var \Texteller\Member $object */
					$member_id = $object->get_id();
				}
				return !empty($member_id) ? [ 'members' => [$member_id] ] : [];

			case 'registered_draft_user' :
				if (
					is_a($object,'\Texteller\Member')
					&& 'wp-login' === $registration_origin
					&& 'email' !== get_option('tlr_wp_registration_new_user_rp_message', 'both' )
				) {
					/** @var \Texteller\Member $object */
					$number = $object->get_mobile();
				}
				return !empty($number) ? [ 'numbers' => [$number] ] : [];

			case 'dashboard_registered_draft_user':
				if ( is_a($object, '\Texteller\Member') && 'wp-dashboard-new-user' === $registration_origin) {
					/** @var \Texteller\Member $object */
					$number = $object->get_mobile();
				}
				return !empty($number) ? [ 'numbers' => [$number] ] : [];

			case 'dashboard_registered_user':
				if ( is_a($object,'\Texteller\Member') && in_array( $registration_origin, [ 'wp-dashboard-new-user', 'wp-dashboard-edit-user' ] ) ) {
					/** @var \Texteller\Member $object */
					$member_id = $object->get_id();
				}
				return !empty($member_id) ? [ 'members' => [$member_id] ] : [];

			case 'lost_user':
				if ( is_a($object,'\Texteller\Member') ) {
					/** @var \Texteller\Member $object */
					$member_id = $object->get_id();
				}
				return !empty($member_id) ? [ 'members' => [$member_id] ] : [];

			case 'post_author':
				if ( is_a($object,'\WP_Post') ) {
					/** @var \WP_Post $object */
					$author_id = $object->post_author;
					$member_id = $author_id ? tlr_get_member_id( $author_id, 'user_id' ) : 0;
				} elseif ( is_a($object,'\WP_Comment') ) {
					/** @var \WP_Comment $object */
					$post = \WP_Post::get_instance($object->comment_post_ID);
					$author_id = $post->post_author;
					$member_id = $author_id ? tlr_get_member_id( $author_id, 'user_id' ) : 0;
				}
				return !empty($member_id) ? [ 'members' => [$member_id] ] : [];

			case 'comment_author':
				if (is_a($object,'\WP_Comment')) {
					/** @var \WP_Comment $object */
					$author_id = $object->user_id;
					$member_id = $author_id ? tlr_get_member_id( $author_id, 'user_id' ) : 0;
				}
				return !empty($member_id) ? [ 'members' => [$member_id] ] : [];

			case 'parent_comment_author':
				if (is_a($object,'\WP_Comment')) {
					/** @var \WP_Comment $object */
					$parent_author_id = $object->comment_parent ? \WP_Comment::get_instance($object->comment_parent)->user_id : 0;
					$member_id = $parent_author_id ? tlr_get_member_id( $parent_author_id, 'user_id' ) : 0;
				}
				return !empty($member_id) ? [ 'members' => [$member_id] ] : [];

			default:
				return [];
		}
	}

	///////////////////////////////////////////////////
	///                                             ///
	///             Notification Triggers           ///
	///                                             ///
	///////////////////////////////////////////////////

	public static function send_lost_user_rp_link( $user_id, $user_login, $key )
	{
		$member_id = TLR\tlr_get_member_id( $user_id, 'user_id');
		if ( $member_id > 0 ) {
			TLR\Gateway_Manager::send_notification( [
				'notification_class'    =>  self::class,
				'tag_type'              =>  'lost_user',
				'trigger'               =>  'wp_lost_pw_rp_link',
				'object'                =>  new Member($member_id),
				'extra_tags'            =>  [ 'rp_key' => $key, 'user_id' => $user_id, 'user_login' => $user_login ]
			] );
		}
	}

	public static function new_user_rp_notification( $user_login, $key )
	{
		$registration_module = TLR\Registration_Module::get_instance();
		if ( ! $registration_module->is_reg_started || 'email' === get_option('tlr_wp_registration_new_user_rp_message', 'both' ) ) {
			return;
		}

		TLR\Gateway_Manager::send_notification( [
			'notification_class'    =>  self::class,
			'tag_type'              =>  'draft_member',
			'trigger'               =>  'wp_registration_rp_link',
			'object'                =>  $registration_module->get_member(),
			'extra_tags'            =>  [ 'user_login' => $user_login, 'rp_key' => $key ],
			'params'                =>  [ 'registration_origin' => Registration::$reg_origin ]
		] );

	}

	public static function wp_login_new_user_registered( TLR\Member $member, $reg_orig )
	{
		TLR\Gateway_Manager::send_notification( [
			'notification_class'    =>  self::class,
			'tag_type'              =>  'user',
			'trigger'               =>  'wp_registration_member_registered',
			'object'                =>  $member,
			'params'                =>  [ 'registration_origin' => $reg_orig ]
		] );
	}

	/**
	 * @param $post_ID
	 * @param \WP_Post $post
	 * @param $update
	 */
	public static function save_post( $post_ID, $post, $update )
	{
		if (
			!$update
			&& ! wp_is_post_autosave( $post_ID )
			&& ! wp_is_post_revision( $post_ID )
			&& 'post' === $post->post_type
			&& 'publish' === $post->post_status
		) {
			if ( !defined('TLR_POST_PUBLISHED') ) {
				define('TLR_POST_PUBLISHED', true);
			}
		}
	}

	/**
	 * @param $post_ID
	 * @param \WP_Post $post_after
	 * @param \WP_Post $post_before
	 */
	public static function post_updated( $post_ID, $post_after, $post_before )
	{
		if (
			! wp_is_post_autosave( $post_ID )
			&& ! wp_is_post_revision( $post_ID )
			&& 'post' === $post_after->post_type
			&& 'publish' === $post_after->post_status
			&& 'publish' !== $post_before->post_status
		) {
			if ( !defined('TLR_POST_PUBLISHED') ) {
				define('TLR_POST_PUBLISHED', true);
			}
		}
	}

	/**
	 * @param \WP_Post         $post     Inserted or updated post object.
	 */
	public static function rest_post_updated( $post )
	{
		if (
			'post' === $post->post_type
			&& 'publish' === $post->post_status
			&& defined('TLR_POST_PUBLISHED')
		) {
			TLR\Gateway_Manager::send_notification( [
				'notification_class'    =>  self::class,
				'tag_type'              =>  'post',
				'object'                =>  $post,
				'trigger'               =>  'wp_posts_new_post_published'
			] );
		}
	}

	/**
	 * @param int        $comment_ID       The comment ID.
	 * @param int|string $comment_approved 1 if the comment is approved, 0 if not, 'spam' if spam.
	 * @param array      $commentdata      Comment data.
	 */
	public static function comment_posted( $comment_ID, $comment_approved, $commentdata)
	{
		$post_id = isset($commentdata['comment_post_ID']) ? $commentdata['comment_post_ID'] : 0;

		if ( $post_id && 'post' === get_post_type( $post_id ) ) {
			TLR\Gateway_Manager::send_notification( [
				'notification_class'    =>  self::class,
				'tag_type'              =>  'comment',
				'object'                =>  \WP_Comment::get_instance($comment_ID),
				'trigger'               =>  'wp_posts_comment_posted'
			] );
		}
	}

	/**
	 * @param int|string    $new_status The new comment status.
	 * @param int|string    $old_status The old comment status.
	 * @param \WP_Comment   $comment    The comment data.
	 */
	public static function comment_approved( $new_status, $old_status, $comment )
	{
		if ( 'approved' === $new_status ) {
			$post_id = $comment->comment_post_ID;

			if ( $post_id && 'post' === get_post_type( $post_id ) ) {
				TLR\Gateway_Manager::send_notification( [
					'notification_class'    =>  self::class,
					'tag_type'              =>  'comment',
					'object'                =>  $comment,
					'trigger'               =>  'wp_posts_comment_approved'
				] );
			}
		}
	}
}
