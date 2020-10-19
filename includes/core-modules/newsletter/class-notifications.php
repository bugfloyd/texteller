<?php

namespace Texteller\Core_Modules\Newsletter;
use Texteller as TLR;

defined( 'ABSPATH' ) || exit;

/**
 * Class Notifications Handles newsletter notifications
 * @package Texteller\Core_Modules\Newsletter
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
		add_action( 'texteller_nl_member_registered', [ self::class, 'member_registered' ] );
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
		if ( 'member' === $tag_type && is_a($object, '\Texteller\Member') ) {
			$member_tags_values = TLR\tlr_get_base_tags_values_array('member', $object );
			$tags->add_tag_type_data('member', $member_tags_values );
		}
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
		if ( 'registered_member' === $trigger_recipient_type ) {
			if ( is_a($object,'\Texteller\Member') ) {
				/** @var \Texteller\Member $object */
				$member_id = $object->get_id();
			}
		}
		return !empty($member_id) ? [ 'members' => [$member_id] ] : [];
	}


	///////////////////////////////////////////////////
	///                                             ///
	///             Notification Triggers           ///
	///                                             ///
	///////////////////////////////////////////////////

	/**
	 * Sends notification when new member is registered via newsletter registration form
	 *
	 * @see \Texteller\Core_Modules\Newsletter\Registration::member_registration_listener()
	 *
	 * @param \Texteller\Member $member
	 */
	public static function member_registered( TLR\Member $member )
	{
		TLR\Gateway_Manager::send_notification( [
			'notification_class'    =>  self::class,
			'tag_type'              =>  'member',
			'trigger'               =>  'tlr_newsletter_member_registered',
			'object'                =>  $member
		] );
	}
}
