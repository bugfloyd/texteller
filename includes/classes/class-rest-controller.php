<?php

namespace Texteller;

defined( 'ABSPATH' ) || exit;

class REST_Controller {

	public static function init()
	{
		add_action( 'rest_api_init', [self::class, 'register_rest_routes'] );
	}

	public static function register_rest_routes()
	{
		register_rest_route(
			'texteller/v1',
			'/receive/(?P<gateway_name>[\w]+)/',
			[
				'args'   => [
					'gateway_name' => [
						'description'       =>  __( 'Gateway Name' ),
						'type'              =>  'string',
						'validate_callback' =>  [ self::class, 'validate_gateway' ],
						'sanitize_callback' =>  'sanitize_text_field'
					]
				],
				[
					'methods'   =>  'GET,POST',
					'callback'  =>  [ self::class, 'init_gateway_receive' ],
					'permission_callback' => '__return_true'
				]
			]
		);

		register_rest_route(
			'texteller/v1',
			'/delivery/(?P<gateway_name>[\w]+)/',
			[
				'args'   => [
					'gateway_name' => [
						'description'       =>  __( 'Gateway Name' ),
						'type'              =>  'string',
						'validate_callback' =>  [ self::class, 'validate_gateway' ],
						'sanitize_callback' =>  'sanitize_text_field'
					]
				],
				[
					'methods'   =>  'GET,POST',
					'callback'  =>  [ self::class, 'init_gateway_delivery' ],
					'permission_callback' => '__return_true'
				]
			]
		);
	}

	/**
	 *
	 * @param \WP_REST_Request $request Current request.
	 * @return \WP_REST_Response|mixed
	 */
	public static function init_gateway_receive( $request )
	{
		$gateway_slug = $request->get_param('gateway_name');
		$gateway_class = tlr_get_gateway_class($gateway_slug);
		if ( method_exists( $gateway_class,'rest_receive_callback' ) ) {
			$response = $gateway_class::rest_receive_callback( $request );
			return rest_ensure_response( $response );
		} else {
			return new \WP_Error( 'rest_invalid_gateway', esc_html('Invalid gateway'), [ 'status' => 404 ] );
		}
	}

	/**
	 *
	 * @param \WP_REST_Request $request Current request.
	 * @return \WP_REST_Response|mixed
	 */
	public static function init_gateway_delivery( $request )
	{
		$gateway_slug = $request->get_param('gateway_name');
		$gateway_class = tlr_get_gateway_class($gateway_slug);
		if ( method_exists( $gateway_class,'rest_delivery_callback' ) ) {
			$response = $gateway_class::rest_delivery_callback( $request );
			return rest_ensure_response( $response );
		} else {
			return new \WP_Error( 'rest_invalid_gateway', esc_html('Invalid gateway'), [ 'status' => 404 ] );
		}
	}

	public static function validate_gateway( $param, $request, $key )
	{
		if ( is_string($param) ) {
			$gateway_slug = sanitize_text_field($param);
			$gateway_class = tlr_get_gateway_class($gateway_slug);
			if ( $gateway_class ) {
				return true;
			}
		}
		return new \WP_Error( 'rest_invalid_gateway', sprintf( esc_html__( '%s is not a valid gateway.', 'texteller' ), $param ), [ 'status' => 400 ] );
	}

}