<?php

namespace Texteller\Gateways\Melipayamak;
use Texteller as TLR;

defined( 'ABSPATH' ) || exit;

class Base implements TLR\Interfaces\Gateway
{
	use TLR\Traits\Options_Base;
	use TLR\Traits\DateTime;
	use TLR\Traits\Encrypted_Options;

	private $username, $password, $phone_number, $credit, $sender;

	public function __construct() {
	    $this->init_gateway();
    }

	private function init_gateway()
	{
        if ( $this->username && $this->password && $this->phone_number ) {
            return;
        }
		$this->username     = get_option('tlr_gateway_melipayamak_username');
		$this->password     = self::get_encrypted_option( 'tlr_gateway_melipayamak_password');
		$this->phone_number = get_option('tlr_gateway_melipayamak_number');
	}

	public static function get_interfaces()
	{
	    $interfaces = [ 'melipayamak-dedicated' =>  __( 'Dedicated Line', 'texteller' ) ];
	    if ( 'yes' === get_option('tlr_gateway_melipayamak_shared_interface') ) {
		    $interfaces['melipayamak-shared'] = __( 'Shared Line', 'texteller' );
        }
		return $interfaces;
	}

	public static function get_default_interface()
	{
		return 'melipayamak-dedicated';
	}


	public function send( TLR\Message $message, array $action_gateway_data = [] )
	{
		switch ( $message->get_interface() ) {

			case 'melipayamak-shared' :
			    if ( empty($action_gateway_data['mp_bid']) ) {
			        return false;
                }
				$response = $this->shared_send( $message->get_content(), $message->get_recipient(), $action_gateway_data['mp_bid'] );
				break;

			case 'melipayamak-dedicated' :
			default:
			$response = $this->dedicated_send( $message->get_content(), [ $message->get_recipient()] );
		}
		return $response;
	}

	public function shared_send( string $vars, string $number, int $bid )
	{
		if ( empty( $vars ) || empty($number) || empty($bid) ) {
			return false;
		}

		$args = [];
		$args['body'] = [
			'username'  =>  $this->username,
			'password'  =>  $this->password,
			'to'        =>  $number,
			'bodyId'    =>  $bid,
			'text'      =>  $vars
		];

		$response = wp_remote_post( 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber', $args );
		if ( is_wp_error($response) ) {
		    return false;
		}
		$response = wp_remote_retrieve_body( $response );
		$response = json_decode( $response, true );

		$recid = isset($response['Value']) ? $response['Value'] : '';
		$is_sent = isset($response['RetStatus']) && 1 === $response['RetStatus'] && strlen( $recid ) > 10;

		return $is_sent ? ['data' => [ 'recid' => $recid ]] : false;
	}

	public function dedicated_send( string $text, array $numbers )
	{
		if ( empty($text) || empty($numbers) ) {
			return false;
		}

		$numbers = implode( ',', $numbers );
		$args = [];
		$args['body'] = [
			'username'  =>  $this->username,
			'password'  =>  $this->password,
			'to'        =>  $numbers,
            'from'      =>  $this->phone_number,
			'text'      =>  $text
		];

		$response = wp_remote_post( 'https://rest.payamak-panel.com/api/SendSMS/SendSMS', $args );
		if ( is_wp_error($response) ) {
			return false;
		}
		$response = wp_remote_retrieve_body( $response );
		$response = json_decode( $response, true );

		$recid = isset($response['Value']) ? $response['Value'] : '';
		$is_sent = isset($response['RetStatus']) && 1 === $response['RetStatus'] && strlen( $recid ) > 10;

		return $is_sent ? ['data' => [ 'recid' => $recid ]]   : false;
	}

	public function get_credit()
    {
	    $args = [];
	    $args['body'] = [
		    'username'  =>  $this->username,
		    'password'  =>  $this->password
	    ];

	    $response = wp_remote_post( 'https://rest.payamak-panel.com/api/SendSMS/GetCredit', $args );
	    if ( is_wp_error($response) ) {
		    return false;
	    }
	    $response = wp_remote_retrieve_body( $response );
	    $response = json_decode( $response, true );

	    if ( isset($response['RetStatus']) && 1 === $response['RetStatus'] && isset($response['Value']) ) {
	        return $response['Value'];
        } else {
	        return false;
        }
    }

    public function get_numbers()
    {
        $this->init_gateway();
	    $args = [];
	    $args['body'] = [
		    'username'  =>  $this->username,
		    'password'  =>  $this->password
	    ];

	    $response = wp_remote_post( 'https://rest.payamak-panel.com/api/SendSMS/GetUserNumbers', $args );
	    if ( is_wp_error($response) ) {
		    return [];
	    }
	    $response = wp_remote_retrieve_body( $response );
	    $response = json_decode( $response, true );

	    if ( !empty($response['Data']) ) {
	        return $response['Data'];
        } else {
	        return [];
        }
    }

	/**
	 * @param \WP_REST_Request $request Current request.
	 * @return string|\WP_Error
	 */
    public static function rest_receive_callback( $request )
    {
        $method = $request->get_method();
        $from =  $request->get_param('from');
        $to = $request->get_param('to');
        $text = $request->get_param('body');
        if ( 'GET' !== $method || !$from || !is_string($from) || !$to || !is_string($to) || !$text || !is_string($text) ) {
            return new \WP_Error( 'rest_invalid_message_data', esc_html('Invalid message data'), [ 'status' => 404 ] );
        } else {
            $from = '+98' . sanitize_text_field($from);
            $to = sanitize_text_field($to);

            if ( $to !== self::get_interface_number('melipayamak-dedicated') ) {
	            return new \WP_Error( 'rest_invalid_inbound_number', esc_html('Invalid inbound phone number'), [ 'status' => 404 ] );
            }

            $text = sanitize_text_field($text);
	        $member_id = TLR\tlr_get_member_id($from,'mobile');

	        $message = new TLR\Message();
            $message->set_recipient($from);
            $message->set_gateway('melipayamak');
            $message->set_interface('melipayamak-dedicated');
            $message->set_interface_number( $to );
            $message->set_content($text);
            $message->set_status('received');
            $message->set_trigger('tlr_inbound_message' );
            $message->set_member_id($member_id);
            $message->save();
            return 'success';
        }
    }

	public static function get_interface_number( string $interface ): string
	{
		return get_option( 'tlr_gateway_melipayamak_number', '' );
	}

	public static function get_content_types()
	{
		return [
			'melipayamak-dedicated' =>  'text',
			'melipayamak-shared'    =>  'replace'
		];
	}

	public static function is_interface_active( string $interface ) : bool
	{
		if ( 'melipayamak-dedicated' === $interface ) {
			return true;
		} elseif ( 'melipayamak-shared' === $interface ) {
			return 'yes' === get_option( 'tlr_gateway_melipayamak_shared_interface' );
		}
		return false;
	}

	public static function get_message_content( string $interface, array $action_gateway_data ) : string
	{
		if ( 'melipayamak-shared' !== $interface ) {
			return '';
		} else {
			$vars = !empty($action_gateway_data['mp_vars']) && is_array($action_gateway_data['mp_vars'])
				? $action_gateway_data['mp_vars'] : [];
			return implode( ';', $vars );
		}
	}

	///////////////////////////////////////////////////
	///                                             ///
	///             Gateway Options                 ///
	///                                             ///
	///////////////////////////////////////////////////

	public function register_gateway_options()
	{
        self::add_options();
		add_action( 'tlr_options_after_fields', [ $this, 'render_gateway_status' ], 10, 2);
		add_filter( 'pre_update_option_tlr_gateway_melipayamak_password', [self::class, 'update_encrypted_option'], 10, 2 );
		add_filter( 'pre_update_option_tlr_gateway_melipayamak_number', [$this, 'initialize_phone_numbers'] );
	}

	public function initialize_phone_numbers( $value )
    {
	    if ( $value ) {
		    return $value;
	    }

	    $numbers = $this->get_numbers();
	    if ( $numbers && !empty($numbers[0]['Number']) ) {
		    return $numbers[0]['Number'];
	    }

	    return $value;
    }

	public static function add_options()
	{
		self::register_section([
			'id'    =>  'tlr_gateway_melipayamak',
			'title' =>  __( 'Melipayamak', 'texteller' ),
			'desc'  =>  sprintf( /* translators: %s: Gateway name */
				__( 'Configure %s gateway options', 'texteller' ), __( 'Melipayamak', 'texteller' )
			),
			'class' =>  'description',
			'page'  =>  'tlr_gateways'
		]);

		$options = [
			[
				'id'        =>  'tlr_gateway_melipayamak_username',
				'title'     =>  __( 'Melipayamak Username', 'texteller' ),
				'page'      =>  'tlr_gateways',
				'section'   =>  'tlr_gateway_melipayamak',
				'desc'      =>  __( 'Melipayamak panel username', 'texteller' ),
                'type'      =>  'input',
				'params'    =>  [
					'type'  =>  'text'
				]
			],
			[
				'id'        =>  'tlr_gateway_melipayamak_password',
				'title'     =>  __( 'Melipayamak Password', 'texteller' ),
				'page'      =>  'tlr_gateways',
				'section'   =>  'tlr_gateway_melipayamak',
				'desc'      =>  __( 'Melipayamak panel password', 'texteller' ),
                'type'      =>  'input',
				'params'    =>  [
					'type'  =>  'password'
				]
			],
			[
				'id'        =>  'tlr_gateway_melipayamak_number',
				'title'     =>  __( 'Sender Number', 'texteller' ),
				'page'      =>  'tlr_gateways',
				'section'   =>  'tlr_gateway_melipayamak',
				'desc'      =>  __( 'The line number purchased from Melipayamak panel', 'texteller' ),
				'type'      =>  [self::class, 'render_number_selector']
			],
			[
				'id'        =>  'tlr_gateway_melipayamak_shared_interface',
				'title'     =>  __( 'Shared Line Interface', 'texteller' ),
				'page'      =>  'tlr_gateways',
				'section'   =>  'tlr_gateway_melipayamak',
				'desc'      =>  __( 'Enabled shared line API in Melipayamak panel is needed.', 'texteller' ),
                'helper'    =>  __( 'Shared line API can be activated through contacting Melipayamak support.', 'texteller' ),
				'type'      =>  'input',
				'params'    =>  [
					'type'  =>  'checkbox',
                    'label' =>  __( 'Enable Melipayamak shared line interface', 'texteller' )
				]
			]
		];
		self::register_options( $options );
	}

	public function render_number_selector()
    {
        $stored_value = $this->phone_number;
        $numbers = $this->get_numbers();
        ob_start();
        ?>
        <select name="tlr_gateway_melipayamak_number">
            <?php
            if ( !$numbers ) {
                ?>
                <option value="0"><?= esc_html__('Please save valid authentication credentials', 'texteller' ) ?></option>
                <?php
            } else {
	            foreach ( (array) $numbers as $number ) {
		            if ( !empty($number['Number']) ) {
		                ?>
                        <option value="<?= esc_attr($number['Number']) ?>"<?php selected( $stored_value,$number['Number'] ) ?>><?= esc_html($number['Number']) ?></option>
                        <?php
		            }
	            }
            }
            ?>
        </select>
        <?php
        return ob_get_clean();
    }

	public static function get_gateway_options( $option_name, $stored_gateway_data )
	{
		$bid = isset($stored_gateway_data['mp_bid']) ? $stored_gateway_data['mp_bid'] : '';
		$variables = isset($stored_gateway_data['mp_vars']) ? $stored_gateway_data['mp_vars'] : [];
		?>
		<div class="melipayamak-shared-content">
			<div class="shared-bid">
				<label>
					<span><?= __( 'Message template ID:', 'texteller' ) ?></span>
					<input type="text" name="<?=$option_name?>[mp_bid]" class="tlr-small-field" value="<?=$bid?>">
				</label>
			</div>
			<div class="shared-variables">
				<label>
					<span><?= __( 'Message Variables:', 'texteller' ) ?></span>
					<?php
					echo TLR\Admin\Option_Renderer::variable_list(['id'=>"{$option_name}[mp_vars]"], $variables);
					?>
				</label>
			</div>
		</div>
		<?php
	}

	public function render_gateway_status( $current_section, $current_tab )
	{
		if ( 'tlr_gateway_melipayamak' !== $current_section || 'tlr_gateways' !== $current_tab ) {
			return;
		}
		$credit = $this->get_credit();
		$is_authenticated = (bool) $credit;
		?><div class="gateway-checker-wrap">
        <h4 style="margin-top:0;"><?= __( 'API Connection Status and Account Details', 'texteller' ) ?></h4>
        <div class="gateway-info-wrap">
            <div class="gateway-info-label-wrap">
                <span><?= __( 'API Authentication', 'texteller' ) ?></span>
            </div>
            <div class="gateway-info-value-wrap"><?php
				?><span><?php
					if ( $is_authenticated ) {
						esc_html_e( 'Verified!', 'texteller' );
					} else {
						esc_html_e( 'Please save valid authentication credentials', 'texteller' );
					}
					?></span>
            </div><?php
			if( $is_authenticated ) {
				?>
                <div class="gateway-info-label-wrap">
                    <span><?= esc_html__( 'Balance', 'texteller' ) ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                    <span><?= esc_html( round($credit,2) ) . ' ' . esc_html__('messages', 'texteller') ?></span>
                </div>
                <div class="gateway-info-label-wrap">
                    <span><?= esc_html__( 'Receive Endpoint', 'texteller' ) ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                <span><?= esc_html( get_rest_url(null, 'texteller/v1/receive/melipayamak') . '/?to=$TO$&body=$TEXT$&from=$FROM$' ) ?></span>
                </div><?php
			}
			?>
        </div>
        </div>
		<?php
	}
}