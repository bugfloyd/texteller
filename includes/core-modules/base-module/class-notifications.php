<?php

namespace Texteller\Core_Modules\Base_Module;
use Texteller as TLR;

defined( 'ABSPATH' ) || exit;

/**
 * Class Notifications Handles base notifications
 * @package Texteller\Core_Modules\Newsletter
 */
final class Notifications {
	/**
	 * Notifications constructor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initializes notification trigger hooks
	 */
	public function init() {
		add_action( 'tlr_verification', [ self::class, 'send_verify_code' ], 10, 2 );
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
	public static function set_tags_values( string $tag_type, TLR\Tags &$tags, $object, array $extra_tags ) {
		if ( 'unverified_member' === $tag_type && is_a( $object, '\Texteller\Member' ) ) {
			$member_tags_values           = TLR\tlr_get_base_tags_values_array( 'member', $object );
			$unverified_member_tag_values = [
				'code' => $extra_tags['code']
			];
			$tags->add_tag_type_data( 'unverified_member', array_merge( $member_tags_values, $unverified_member_tag_values ) );
		}
	}

	/**
	 * Gets member IDs or mobile numbers for the current trigger recipient type and object
	 *
	 * @param   string $trigger_recipient_type
	 * @param   object $object
	 * @param   array $trigger_recipient_args
	 *
	 * @return array
	 */
	public static function get_trigger_recipient_member_ids( $trigger_recipient_type, $object, $trigger_recipient_args ) {
		if ( 'member' === $trigger_recipient_type ) {
			if ( is_a( $object, '\Texteller\Member' ) ) {
				/** @var \Texteller\Member $object */
				$number = $object->get_mobile();
			}
		}

		return ! empty( $number ) ? [ 'numbers' => [ $number ] ] : [];
	}


	///////////////////////////////////////////////////
	///                                             ///
	///             Notification Triggers           ///
	///                                             ///
	///////////////////////////////////////////////////

	/**
	 * Sends verification codes
	 *
	 * @see \Texteller\Verify_Number::init_verification_listener()
	 *
	 * @param TLR\Member $member
	 * @param $code
	 */
	public static function send_verify_code( $member, $code ) {
		if ( ! $member->get_mobile() ) {
			return;
		}
		TLR\Gateway_Manager::send_notification( [
			'notification_class'    =>  self::class,
			'tag_type'              =>  'unverified_member',
			'trigger'               =>  'tlr_number_verification',
			'object'                =>  $member,
			'extra_tags'            =>  [ 'code' => $code ]
		] );
	}
}
