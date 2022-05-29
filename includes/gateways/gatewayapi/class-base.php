<?php

namespace Texteller\Gateways\GatewayAPI;

use Texteller as TLR;
use Exception;
use WP_Error;
use WP_REST_Request;

defined("ABSPATH") || exit();

class Base implements TLR\Interfaces\Gateway
{
    use TLR\Traits\Options_Base;
    use TLR\Traits\DateTime;
    use TLR\Traits\Encrypted_Options;

    /**
     * @var null|GatewayAPI $client GatewayAPI Client
     */
    private static ?GatewayAPI $client = null;

    public function __construct()
    {
    }

    public static function get_client(): ?GatewayAPI
    {
        if (self::$client && is_object(self::$client)) {
            return self::$client;
        }

        try {
            self::$client = new GatewayAPI();
        } catch (Exception $e) {
            TLR\tlr_write_log(
                "GatewayAPI: Failed to initialize GatewayAPI client. " .
                    $e->getMessage()
            );
            return null;
        }
        return self::$client;
    }

    public static function get_account_details()
    {
        if (!self::get_client()) {
            return "";
        }

        $account_details = [];

        $res = self::$client->get_account_details();

        if (!is_wp_error($res) && !empty($res)) {
            $account_details["account_id"] = $res->id;
            $account_details["balance"] = $res->credit;
            $account_details["currency"] = $res->currency;
        } else {
            TLR\tlr_write_log(
                "GatewayAPI: An error occurred while getting account balance " .
                    $res->get_error_code() .
                    $res->get_error_message()
            );
        }

        return $account_details;
    }

    public function send(
	    TLR\Message $message,
        array $action_gateway_data = []
    ) {
        if (!self::get_client()) {
            return false;
        }

        if ("gatewayapi-sms" === $message->get_interface()) {
            return $this->send_sms($message->get_content(), $message->get_recipient());
        } else {
            return false;
        }
    }

    private function send_sms(string $text, string $number)
    {
        if (null === self::$client || !is_object(self::$client)) {
            return false;
        }

        $result = self::$client->send_sms($number, $text);

        if (!is_wp_error($result)) {
            return !empty($result)
                ? [
                    "data" => ["id" => (string) $result],
                    "message_interface_number" => self::$client::get_sender_name(),
                ]
                : false;
        } else {
            TLR\tlr_write_log(
                "GatewayAPI: An error occurred while sending the message. " .
                    $result->get_error_code() .
                    $result->get_error_message()
            );
        }
        return false;
    }

    /**
     * @param WP_REST_Request $request Current request.
     *
     * @return string|WP_Error
     */
    public static function rest_delivery_callback(WP_REST_Request $request)
    {
        $method = $request->get_method();
        $delivery_body = $request->get_params();

        if (
            "POST" === $method &&
            !empty($delivery_body["id"]) &&
            !empty($delivery_body["status"])
        ) {
            $args = [
                "object_type" => "message",
                "statuses" => ["sent", "delivered", "failed", "pending"],
                "gateways" => ["gatewayapi"],
                "field" => "ID",
                "gateway_data" => sanitize_text_field($delivery_body["id"]),
            ];
            $message_query = new TLR\Object_Query($args);
            $message_id = $message_query->get_messages(1);
            if (!empty($message_id)) {
                $message = new TLR\Message($message_id[0]);
                if ($message->get_id()) {
                    if ($delivery_body["status"] == "DELIVERED") {
                        $status = "delivered";
                    } elseif (
                        $delivery_body["status"] == "BUFFERED" ||
                        $delivery_body["status"] == "ENROUTE"
                    ) {
                        $status = "pending";
                    } else {
                        $status = "failed";
                    }
                    $message->set_status($status);
                    $message->save();
                }
            }
            return "success";
        } else {
            return new WP_Error(
                "rest_invalid_message_data",
                esc_html("Invalid message data"),
                ["status" => 404]
            );
        }
    }

    /**
     * @param WP_REST_Request $request Current request.
     *
     * @return string|WP_Error
     */
    public static function rest_receive_callback(WP_REST_Request $request)
    {
        $method = $request->get_method();
        $message_body = $request->get_params();

        $message_id = isset($message_body["id"])
            ? sanitize_text_field($message_body["id"])
            : "";
        $text = isset($message_body["message"])
            ? sanitize_text_field($message_body["message"])
            : "";
        $receiver = isset($message_body["receiver"])
            ? sanitize_text_field($message_body["receiver"])
            : "";
        $msisdn = isset($message_body["msisdn"])
            ? sanitize_text_field($message_body["msisdn"])
            : "";
        $member_id = TLR\tlr_get_member_id($msisdn);

        if ("POST" !== $method || !$msisdn || !$receiver || !$message_id) {
            return new WP_Error(
                "rest_invalid_message_data",
                esc_html("Invalid message data"),
                ["status" => 404]
            );
        } else {
            $message = new TLR\Message();
            $message->set_recipient($msisdn);
            $message->set_gateway("gatewayapi");
            $message->set_interface("gatewayapi-sms");
            $message->set_interface_number($receiver);
            $message->set_gateway_data(["id" => $message_id]);
            $message->set_content($text);
            $message->set_status("received");
            $message->set_trigger("tlr_inbound_message");
            $message->set_member_id($member_id);
            $message->save();
            return "success";
        }
    }

    public static function get_interfaces(): array
    {
        return [
            "gatewayapi-sms" => "SMS",
        ];
    }

    public static function get_default_interface(): string
    {
        return "gatewayapi-sms";
    }

    public static function get_interface_number(string $interface): string
    {
        return self::get_client()::get_sender_name();
    }

    public static function get_content_types(): array
    {
        return [
            "gatewayapi-sms" => "text",
        ];
    }

    public static function is_interface_active(string $interface): bool
    {
        return true;
    }

    ///////////////////////////////////////////////////
    ///                                             ///
    ///             Gateway Options                 ///
    ///                                             ///
    ///////////////////////////////////////////////////

    public function register_gateway_options()
    {
        self::add_options();
        add_action(
            "tlr_options_after_fields",
            [self::class, "render_gateway_status"],
            10,
            2
        );
        add_filter(
            "pre_update_option_tlr_gateway_gatewayapi_secret",
            [self::class, "update_encrypted_option"],
            10,
            2
        );
    }

    public static function add_options()
    {
        self::register_section([
            "id" => "tlr_gateway_gatewayapi",
            "title" => __("GatewayAPI", "texteller"),
            "desc" => sprintf(
                /* translators: %s: Gateway name */
                __("Configure %s gateway options", "texteller"),
                __("GatewayAPI", "texteller")
            ),
            "class" => "description",
            "page" => "tlr_gateways",
        ]);

        $options = [
            [
                "id" => "tlr_gateway_gatewayapi_key",
                "title" => __("GatewayAPI key", "texteller"),
                "page" => "tlr_gateways",
                "section" => "tlr_gateway_gatewayapi",
                "desc" => __(
                    "GatewayAPI key acquired from gatewayapi.com",
                    "texteller"
                ),
                "type" => "input",
                "params" => [
                    "type" => "text",
                ],
            ],
            [
                "id" => "tlr_gateway_gatewayapi_secret",
                "title" => __("GatewayAPI secret", "texteller"),
                "page" => "tlr_gateways",
                "section" => "tlr_gateway_gatewayapi",
                "desc" => __(
                    "GatewayAPI secret acquired from gatewayapi.com",
                    "texteller"
                ),
                "type" => "input",
                "params" => [
                    "type" => "password",
                ],
            ],
            [
                "id" => "tlr_gateway_gatewayapi_sender_name",
                "title" => __("GatewayAPI sender name", "texteller"),
                "page" => "tlr_gateways",
                "section" => "tlr_gateway_gatewayapi",
                "desc" => __(
                    "The sender name of the message for GatewayAPI. 1-11 ASCII characters, spaces are removed.",
                    "texteller"
                ),
                "type" => "input",
                "params" => [
                    "type" => "text",
                ],
            ],
        ];
        self::register_options($options);
    }

    public static function render_gateway_status($current_section, $current_tab)
    {
        if (
            "tlr_gateway_gatewayapi" !== $current_section ||
            "tlr_gateways" !== $current_tab
        ) {
            return;
        }
        $account_details = self::get_account_details();
        $is_authenticated = (bool) $account_details;
        ?><div class="gateway-checker-wrap">
        <h4 style="margin-top:0;"><?= __(
            "API connection status and account details",
            "texteller"
        ) ?></h4>
        <div class="gateway-info-wrap">
            <div class="gateway-info-label-wrap">
                <span><?= __("Authentication status", "texteller") ?></span>
            </div>
            <div class="gateway-info-value-wrap"><?php  ?><span><?php if (
    $is_authenticated
) {
    esc_html_e("Verified!", "texteller");
} else {
    esc_html_e("Please save valid authentication credentials", "texteller");
} ?></span>
            </div><?php if ($account_details) { ?>
                <div class="gateway-info-label-wrap">
                    <span><?= esc_html__("Account ID", "texteller") ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                    <span><?= esc_html($account_details["account_id"]) ?></span>
                </div>
                <div class="gateway-info-label-wrap">
                    <span><?= esc_html__("Balance", "texteller") ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                    <span><?= esc_html($account_details["balance"]) .
                        " " .
                        esc_html($account_details["currency"]) ?></span>
                </div>
                <div class="gateway-info-label-wrap">
                    <span><?= esc_html__(
                        "Delivery Endpoint",
                        "texteller"
                    ) ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                <span><?= esc_html(
                    get_rest_url(null, "texteller/v1/delivery/gatewayapi")
                ) ?></span>
                </div>
                <div class="gateway-info-label-wrap">
                    <span><?= esc_html__(
                        "Receive Endpoint",
                        "texteller"
                    ) ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                <span><?= esc_html(
                    get_rest_url(null, "texteller/v1/receive/gatewayapi")
                ) ?></span>
                </div><?php } ?>
        </div>
        </div>
		<?php
    }
}
