<?php
namespace Texteller;

use Texteller\Interfaces\Gateway;

defined( 'ABSPATH' ) || exit;

class Gateway_Manager
{
	private static $active_gateways = [];

	public function __construct() {}

	private static function set_active_gateways()
	{
		if ( empty(self::$active_gateways) ) {
			self::$active_gateways = get_option( 'tlr_active_gateways', [] );
		}
	}

	/**
	 * @param array|string $content
	 * @param string $recipient_number
	 * @param string $trigger_name
	 * @param string $gateway
	 * @param string $interface
	 * @param bool $db_save
	 * @param null|int $member_id
	 * @param array $action_gateway_data
	 *
	 * @return bool true on success and false on failure
	 */
	public static function send( $content, string $recipient_number, string $trigger_name , string $gateway, string $interface, bool $db_save, $member_id = null, array $action_gateway_data = [] )
	{
		if ( !$recipient_number || !$trigger_name || !$gateway ) {
			return false;
		}

		self::set_active_gateways();
		$gateway_class = tlr_get_gateway_class( $gateway ); /** @var Gateway $gateway_class */
		$interface_number = '';

		if ( $gateway_class && self::is_gateway_active( $gateway ) ) {
			$gateway_instance = new $gateway_class(); /** @var Gateway $gateway_instance */
			$interface = $interface ? $interface : $gateway_class::get_default_interface();
			$interface_number = $gateway_class::get_interface_number($interface);
		}

		if ( ! $gateway_class::is_interface_active($interface) ) {
			return false;
		}

		if ( ! self::is_gateway_active( $gateway ) ) {
			tlr_write_log("Gateway not active: $gateway_class" );
		}

		/** @var Message $message */
		$message = null;

		if ( true === $db_save ) {
			$message_array = [
				'message_trigger'           =>  $trigger_name,
				'message_gateway'           =>  $gateway,
				'message_interface'         =>  $interface,
				'message_interface_number'  =>  $interface_number,
				'message_recipient'         =>  $recipient_number,
				'message_content'           =>  $content,
				'message_status'            =>  'pending',
				'message_gateway_data'      =>  [],
				'message_member_id'         =>  $member_id
			];

			$message = new Message();
			$message->set_message_data( $message_array );
			$message->save();
		}

		if( isset($gateway_instance) ) {
			$response = $gateway_instance->send($message, $action_gateway_data);
			if( ! is_null( $message ) ) {
				$message->set_status( $response ? 'sent' : 'failed' );
				$message->set_gateway_data(isset($response['data']) && is_array( $response['data'] ) ? $response['data'] : [] );
				if (isset($response['message_interface_number'])) {
					$message->set_interface_number($response['message_interface_number']);
				}
				$message->save();
			}
			return (bool) $response;
		}

		if( ! is_null( $message ) ) {
			$message->set_status( 'failed' );
			$message->save();
		}

		return false;
	}

	private static function is_gateway_active ( $gateway )
	{
		self::set_active_gateways();
		return (bool) in_array( $gateway, self::$active_gateways, true );
	}

	public static function send_notification( array $args )
	{
		if (
			! isset( $args['trigger'], $args['notification_class'], $args['tag_type'], $args['object'] )
			|| ! is_object( $args['object'] )
		) {
			return;
		}

		self::set_active_gateways();

		$stored_trigger_option = isset($args['trigger_option']) ? $args['trigger_option'] : get_option( 'tlr_trigger_' . $args['trigger'], [] );
		if (
			empty( $stored_trigger_option )
			|| empty( $stored_trigger_option['enabled'] )
			|| empty( $stored_trigger_option['actions'] )
			|| ! is_array( $stored_trigger_option['actions'] )
		) {
			return;
		}

		$object = $args['object'];
		$recipient_types = [ 'trigger', 'staff', 'members', 'numbers' ];
		$actions = $stored_trigger_option['actions'];

		foreach ( $recipient_types as $recipient_type ) {

			if ( empty($actions[$recipient_type]) ) {
				continue;
			} else {
				$action_data = $actions[$recipient_type];
			}
			if (
				empty($action_data['is_all'])
				&& ( empty($action_data['recipients']) || !is_array($action_data['recipients']) )
			) {
				continue;
			}
			$action_recipients = $action_data['recipients'];
			$action_gateway = '';
			$action_interface = '';
			$action_save = '';
			$final_text = '';
			$gateway_data = [];

			if ( 'trigger' !== $recipient_type ) {
				if ( empty($action_data['enabled']) || empty($action_data['gateway']) || empty($action_data['interface']) ) {
					continue;
				} else {
					$action_gateway = $action_data['gateway'];
					$action_interface = $action_data['interface'];
					$action_save = ! empty($action_data['save']);
					$content = !empty( $action_data['content']) ? $action_data['content'] : '';
					$gateway_data = ( !empty( $action_data['gateway_data'] ) && is_array( $action_data['gateway_data'] ) )
						? $action_data['gateway_data'] : [];

					$final_text = self::get_final_message_content(
						$content,
						$action_gateway,
						$action_interface,
						$gateway_data,
						$args['tag_type'],
						self::get_tags(
							$args['notification_class'],
							$args['tag_type'],
							$object,
							isset($args['extra_tags']) && is_array($args['extra_tags']) ? $args['extra_tags'] :[]
						)
					);
				}
			}

			$recipients = [];
			$numbers = [];
			switch($recipient_type) {

				case 'trigger':
					foreach ( (array) $action_recipients as $trigger_recipient => $trigger_data ) {
						if ( empty($trigger_data['enabled']) || 1 != $trigger_data['enabled'] ) {
							continue;
						}
						$notification_class = $args['notification_class'];
						$params = isset($args['params']) ? (array) $args['params'] : [];
						if ( class_exists($notification_class) && method_exists($notification_class,'get_trigger_recipient_member_ids') ) {
							$current_recipients = $notification_class::get_trigger_recipient_member_ids( $trigger_recipient, $object, $params );
						}
						if ( !empty($current_recipients) ) {
							$params = [
								'trigger'               =>  $args['trigger'],
								'notification_class'    =>  $args['notification_class'],
								'tag_type'              =>  $args['tag_type'],
								'object'                =>  $args['object'],
								'trigger_option'        =>  [
									'enabled'   =>  1,
									'actions'   =>  []
								],
								'extra_tags'            =>  isset($args['extra_tags']) ? $args['extra_tags'] : []
							];
							if ( !empty($current_recipients['members']) ) {
								$params['trigger_option']['actions']['members'] = [
									'enabled'       =>  1,
									'save'          =>  !empty($trigger_data['save']),
									'gateway'       =>  $trigger_data['gateway'],
									'interface'     =>  $trigger_data['interface'],
									'content'       =>  $trigger_data['content'],
									'gateway_data'  =>  $trigger_data['gateway_data'] ?? [],
									'recipients'    =>  $current_recipients['members']
								];
							}
							if ( !empty($current_recipients['numbers']) ) {
								$params['trigger_option']['actions']['numbers'] = [
									'enabled'       =>  1,
									'save'          =>  !empty($trigger_data['save']),
									'gateway'       =>  $trigger_data['gateway'],
									'interface'     =>  $trigger_data['interface'],
									'content'       =>  $trigger_data['content'],
									'gateway_data'  =>  $trigger_data['gateway_data'] ?? [],
									'recipients'    =>  $current_recipients['numbers']
								];
							}
							self::send_notification($params);
						}
					}
					break;

				case 'staff':
					if( !empty($action_data['is_all']) ) {
						$staff = get_option( 'tlr_staff',[] );
					} else {
						$staff = $action_recipients;
					}
					foreach ( $staff as $member_id ) {
						$member = new Member( (int) $member_id);
						if ( $member->get_mobile() ) {
							$recipients[$member->get_id()] = $member->get_mobile();
						}
					}
					break;

				case 'members':
					$recipients = [];
					foreach ( $action_recipients as $member_id ) {
						$member = new Member( (int) $member_id);
						if ( $member->get_mobile() && !$member->is_canceled() ) {
							$recipients[$member->get_id()] = $member->get_mobile();
						}
					}
					break;

				case 'numbers':
					foreach ( $action_recipients as $number ) {
						if ( !$number ) {
							continue;
						}
						$possible_member_id = tlr_get_member_id($number,'mobile');
						if ( $possible_member_id ) {
							$member = new Member($possible_member_id);
							if ( $member->get_mobile() ) {
								$recipients[$member->get_id()] = $member->get_mobile();
							}
						} else {
							$numbers[] = $number;
						}
					}
					break;
			}

			if ( !empty($numbers) ) {
				foreach ( $numbers as $number ) {
					self::send(
						$final_text,
						$number,
						$args['trigger'],
						$action_gateway,
						$action_interface,
						$action_save,
						0,
						$gateway_data
					);
				}
			}

			if ( ! empty($recipients) ) {
				foreach ( $recipients as $member_id => $number ) {
					self::send(
						$final_text,
						$number,
						$args['trigger'],
						$action_gateway,
						$action_interface,
						$action_save,
						$member_id,
						$gateway_data
					);
				}
			}
		}
	}

	/**
	 * Sets message's tag values by calling the related method from the notification class
	 *
	 * @param string $notification_class
	 * @param string $tag_type
	 * @param $object
	 * @param array $extra_tags
	 *
	 * @return bool|Tags
	 */
	public static function get_tags( string $notification_class, string $tag_type, $object, array $extra_tags = [] )
	{
		if( class_exists( $notification_class ) ) {
			$method = "set_tags_values";

			if ( method_exists( $notification_class, $method ) ) {
				$tags = new Tags();
				self::add_global_tags($tags, $tag_type);
				$notification_class::$method( $tag_type, $tags, $object, $extra_tags );

				return $tags;
			}
		}
		return false;
	}

	private static function add_global_tags( Tags &$tags, string $tag_type )
	{
		$global_tags = [
			'site_url'      =>  get_site_url(),
			'site_title'    =>  get_bloginfo('name')
		];
		$tags->add_tag_type_data( $tag_type, $global_tags );
	}

	private static function get_final_message_content( string $content, string $gateway, string $interface, array $action_gateway_data, string $tag_type, Tags $tags ): string
	{
		$gateway_class = tlr_get_gateway_class($gateway);

		// Override gateway-based message content
		if( $gateway_class && method_exists( $gateway_class, 'get_message_content' ) ) {
			$gateway_content = $gateway_class::get_message_content( $interface, $action_gateway_data );
		}
		if ( !empty($gateway_content) ) {
			$content = $gateway_content;
		} else {
			self::add_signature( $content );
		}

		self::process_tags( $content, $tag_type, $tags );
		self::process_links( $content );
		$content = tlr_convert_numbers( $content, get_option('tlr_message_numbers_lang', 'none') );

		return $content;
	}

	private static function add_signature( &$text )
	{
		$signature = apply_filters( 'texteller_signature' , get_option('tlr_message_signature') );
		if ( !empty($signature) ) {
			$text = $text . "\n" . $signature;
		}
	}

	public static function process_tags( string &$text, string $tag_type, Tags $tags )
	{
		$tag_labels = [];
		$tag_values = [];

		$tags_data = $tags->get_merged_tag_types_data( [ $tag_type, 'global' ] );

		foreach ( $tags_data as $tag_id => $tag_value ) {
			$tag_labels[] = '{' . $tag_id . '}';
			$tag_values[] = $tag_value;
		}

		$text =  str_replace( $tag_labels, $tag_values, $text );
	}

	public static function process_links( &$text )
	{
		if ( 'yes' === get_option( 'tlr_shorten_links' ) ) {

			$reg_ex = "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";
			$links = [];

			if ( preg_match_all( $reg_ex, $text, $links ) ) {

				$bitly_token = tlr_decrypt( get_option('tlr_slink_bitly_token') );
				if ( !empty($bitly_token) && is_array( $links[0] ) ) {

					foreach ( $links[0] as $link ) {
						$data = [
							'long_url' => $link
						];
						$data = json_encode($data);
						$response = wp_remote_post('https://api-ssl.bitly.com/v4/shorten', [
							'headers'   =>  [
								'Host'          =>  'api-ssl.bitly.com',
								'Authorization' =>  'Bearer 42e97a5c36f963fb98fc8730ce43f2bc57d78779',
								'Content-Type'  =>  'application/json'
							],
							'body'  =>  $data
						] );
						if ( !is_wp_error($response) ) {
							$response = wp_remote_retrieve_body( $response );
							$response = json_decode( $response, true );
							if( !empty( $response['link'] ) ) {
								$text = str_replace( $link, $response['link'], $text );
							}
						}
					}
				}
			}
		}
	}
}