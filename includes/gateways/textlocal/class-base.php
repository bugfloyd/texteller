<?php

namespace Texteller\Gateways\Textlocal;

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
     * @var null|Textlocal $client Textlocal Client
     */
    private static ?Textlocal $client = null;

    public function __construct()
    {
    }

    public static function get_client(): ?Textlocal
    {
        if (self::$client && is_object(self::$client)) {
            return self::$client;
        }

        try {
            self::$client = new Textlocal();
        } catch (Exception $e) {
            TLR\tlr_write_log(
                "Textlocal: Failed to initialize Textlocal client. " .
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
            $account_details["balance"] = $res->balance->sms;
        } else {
            TLR\tlr_write_log(
                "Textlocal: An error occurred while getting account balance " .
                    $res->get_error_code() .
                    $res->get_error_message()
            );
        }

        return $account_details;
    }

    public function send(TLR\Message $message, array $action_gateway_data = [])
    {
        if (!self::get_client()) {
            return false;
        }

        if ("textlocal-sms" === $message->get_interface()) {
            return $this->send_sms($message);
        } else {
            return false;
        }
    }

    private function send_sms(TLR\Message $message)
    {
        if (null === self::$client || !is_object(self::$client)) {
            return false;
        }

        $result = self::$client->send_sms($message);

        if (!is_wp_error($result)) {
            return !empty($result)
                ? [
                    "data" => ["id" => $result->messages[0]->id],
                    "message_interface_number" => $result->message->sender,
                ]
                : false;
        } else {
            TLR\tlr_write_log(
                "Textlocal: An error occurred while sending the message. " .
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

        if ("POST" === $method && !empty($delivery_body["customID"])) {
            $status = $delivery_body["status"];
	        $message = new TLR\Message($delivery_body["customID"]);
	        if ($message->get_id()) {
		        if ($status == "D") {
			        $status = "delivered";
		        } elseif (
			        $status == "U" ||
			        $status == "I" ||
			        $status == "E"
		        ) {
			        $status = "failed";
		        } else {
			        $status = "sent";
		        }
		        $message->set_status($status);
		        $message->save();
	        }
        }

        return "success";
    }

    /**
     * @param WP_REST_Request $request Current request.
     *
     * @return string|WP_Error
     */
        public static function rest_receive_callback(WP_REST_Request $request)
        {
	        $method = $request->get_method();
	        $receive_body = $request->get_params();

	        if ("POST" === $method && !empty($receive_body["sender"])) {
		        $keyword = isset($receive_body["keyword"])
			        ? sanitize_text_field($receive_body["keyword"])
			        : "";
		        $content = isset($receive_body["content"])
			        ? sanitize_text_field($receive_body["content"])
			        : "";
		        $inNumber = isset($receive_body["inNumber"])
			        ? sanitize_text_field($receive_body["inNumber"])
			        : "";
		        $sender = sanitize_text_field($receive_body["sender"]);
		        $member_id = TLR\tlr_get_member_id($sender);

		        if (!$sender || !$inNumber) {
			        return new WP_Error(
				        "rest_invalid_message_data",
				        esc_html("Invalid message data"),
				        ["status" => 404]
			        );
		        } else {
			        $message = new TLR\Message();
			        $message->set_recipient($sender);
			        $message->set_gateway("bulksms");
			        $message->set_interface("bulksms-sms");
			        $message->set_interface_number($inNumber);
			        $message->set_gateway_data(["keyword" => $keyword]);
			        $message->set_content($content);
			        $message->set_status("received");
			        $message->set_trigger("tlr_inbound_message");
			        $message->set_member_id($member_id);
			        $message->save();
		        }
	        }

    	    return "success";
        }

    public static function get_interfaces(): array
    {
        return [
            "textlocal-sms" => "SMS",
        ];
    }

    public static function get_default_interface(): string
    {
        return "textlocal-sms";
    }

    public static function get_interface_number(string $interface): string
    {
        return self::get_client()::get_sender_name();
    }

    public static function get_content_types(): array
    {
        return [
            "textlocal-sms" => "text",
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
            "pre_update_option_tlr_gateway_textlocal_api_key",
            [self::class, "update_encrypted_option"],
            10,
            2
        );
    }

    public static function add_options()
    {
        self::register_section([
            "id" => "tlr_gateway_textlocal",
            "title" => __("Textlocal", "texteller"),
            "desc" => sprintf(
                /* translators: %s: Gateway name */
                __("Configure %s gateway options", "texteller"),
                __("Textlocal", "texteller")
            ),
            "class" => "description",
            "page" => "tlr_gateways",
        ]);

        $options = [
            [
                "id" => "tlr_gateway_textlocal_api_key",
                "title" => _x(
                    "Textlocal API key",
                    "Textlocal gateway",
                    "texteller"
                ),
                "page" => "tlr_gateways",
                "section" => "tlr_gateway_textlocal",
                "desc" => _x(
                    "Textlocal API key acquired from textlocal.com",
                    "Textlocal gateway",
                    "texteller"
                ),
                "type" => "input",
                "params" => [
                    "type" => "password",
                ],
            ],
            [
                "id" => "tlr_gateway_textlocal_sender",
                "title" => __("Textlocal sender", "texteller"),
                "page" => "tlr_gateways",
                "section" => "tlr_gateway_textlocal",
                "desc" => _x(
                    "The sender of the message for Textlocal. Can be alphanumeric string (max. 11 characters) or phone number (max. 13 digits in E.164 format like 31612345678)",
                    "Textlocal gateway",
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
            "tlr_gateway_textlocal" !== $current_section ||
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
                    <span><?= esc_html__("Balance", "texteller") ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                    <span><?= esc_html(
                        $account_details["balance"]
                    ) ?> SMS</span>
                </div>
                <div class="gateway-info-label-wrap">
                    <span><?= esc_html__(
                        "Delivery webhook",
                        "texteller"
                    ) ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                <span><?= esc_html(
                    get_rest_url(null, "texteller/v1/delivery/textlocal")
                ) ?></span>
                </div>
                <div class="gateway-info-label-wrap">
                    <span><?= esc_html__(
                        "Receive webhook",
                        "texteller"
                    ) ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                <span><?= esc_html(
                    get_rest_url(null, "texteller/v1/receive/textlocal")
                ) ?></span>
                </div><?php } ?>
        </div>
        </div>
		<?php
    }
}
