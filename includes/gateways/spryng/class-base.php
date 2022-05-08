<?php

namespace Texteller\Gateways\Spryng;

use Texteller as TLR;
use Spryng\SpryngRestApi\Spryng;
use Spryng\SpryngRestApi\Objects\Message;
use WP_Error;
use WP_REST_Request;

defined("ABSPATH") || exit();

class Base implements TLR\Interfaces\Gateway
{
    use TLR\Traits\Options_Base;
    use TLR\Traits\DateTime;
    use TLR\Traits\Encrypted_Options;

    /**
     * @var null|Spryng $client Spryng Client
     */
    private static ?Spryng $client = null;

    /**
     * @var null|string $balance
     */
    private static ?string $balance = null;

    public function __construct()
    {
    }

    public static function get_balance(): string
    {
        if (self::$balance) {
            return self::$balance;
        }

        if (!self::get_client()) {
            return "";
        }

        $balance_object = self::get_client()
            ->balance->get()
            ->toObject();
        self::$balance = $balance_object->getAmount();
        return (string) self::$balance;
    }

    public static function get_account_details()
    {
        $balance = self::get_balance();
        if (($balance === null) | ($balance === "")) {
            return false;
        }

        return [
            "balance" => $balance,
        ];
    }

    public static function get_client(): ?Spryng
    {
        if (self::$client && is_object(self::$client)) {
            return self::$client;
        }

        $token = self::get_encrypted_option("tlr_gateway_spryng_api_key");
        if (!$token) {
            return null;
        }

        self::$client = new Spryng($token);
        return self::$client;
    }

    public function send(
        string $text,
        string $number,
        string $interface = "",
        array $action_gateway_data = []
    ) {
        if (!self::get_client()) {
            return false;
        }

        if ("spryng-sms" === $interface) {
            return $this->send_sms($text, $number);
        } else {
            return false;
        }
    }

    private function send_sms(string $text, string $number)
    {
        if (null === self::$client || !is_object(self::$client)) {
            return false;
        }

        $message = new Message();
        $message->setBody($text);
        $message->setRecipients([$number]);
        $message->setOriginator("Texteller");

        $response = self::$client->message->create($message);

        if ($response->wasSuccessful()) {
            $message = $response->toObject();
            $message_id = $message->getId();
            return $message_id ? ["id" => $message_id] : false;
        } elseif ($response->serverError()) {
            TLR\tlr_write_log(
                "Spryng: An error occurred while sending the message on Spryng servers."
            );
        } else {
            TLR\tlr_write_log(
                "Message could not be send. Response code: " .
                    $response->getResponseCode()
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
            $delivery_body = $request->get_body_params();
            if (
                "GET" === $method &&
                !empty($delivery_body["ID"]) &&
                !empty($delivery_body["STATUS"]) &&
                in_array(
                    $delivery_body["STATUS"],
                    [10, 20]
                )
            ) {
                $args = [
                    "object_type" => "message",
                    "statuses" => ["sent", "delivered", "failed", "pending"],
                    "gateways" => ["spryng"],
                    "field" => "ID",
                    "gateway_data" => sanitize_text_field(
                        $delivery_body["ID"]
                    ),
                ];
                $message_query = new TLR\Object_Query($args);
                $message_id = $message_query->get_messages(1);
                $message = new TLR\Message($message_id[0]);
                if ($message->get_id()) {
                    $message->set_status($delivery_body["STATUS"] === 10 ? 'delivered' : 'failed');
                    $message->save();
                    return "success";
                }
            }
            return new WP_Error(
                "rest_invalid_message_data",
                esc_html("Invalid message data"),
                ["status" => 404]
            );
        }

//    /**
//     * @param WP_REST_Request $request Current request.
//     *
//     * @return string|WP_Error
//     */
    //    public static function rest_receive_callback(WP_REST_Request $request)
    //    {
    //        $method = $request->get_method();
    //        $message_body = $request->get_body_params();
    //        $message_sid = isset($message_body["MessageSid"])
    //            ? sanitize_text_field($message_body["MessageSid"])
    //            : "";
    //        $text = isset($message_body["Body"])
    //            ? sanitize_text_field($message_body["Body"])
    //            : "";
    //        $to = isset($message_body["To"])
    //            ? sanitize_text_field($message_body["To"])
    //            : "";
    //        $from = isset($message_body["From"])
    //            ? sanitize_text_field($message_body["From"])
    //            : "";
    //        $member_id = TLR\tlr_get_member_id($from);
    //
    //        if ("POST" !== $method || !$from || !$to || !$message_sid) {
    //            return new WP_Error(
    //                "rest_invalid_message_data",
    //                esc_html("Invalid message data"),
    //                ["status" => 404]
    //            );
    //        } else {
    //            if ($to !== self::get_interface_number("twilio-sms")) {
    //                return new WP_Error(
    //                    "rest_invalid_inbound_number",
    //                    esc_html("Invalid inbound phone number"),
    //                    ["status" => 404]
    //                );
    //            }
    //            $token = self::get_encrypted_option(
    //                "tlr_gateway_twilio_auth_token"
    //            );
    //            $url = get_rest_url(null, "texteller/v1/receive/twilio/");
    //            $validator = new RequestValidator($token);
    //
    //            if (
    //                $validator->validate(
    //                    $request->get_header("x-twilio-signature"),
    //                    $url,
    //                    $message_body
    //                )
    //            ) {
    //                $message = new TLR\Message();
    //                $message->set_recipient($from);
    //                $message->set_gateway("twilio");
    //                $message->set_interface("twilio-sms");
    //                $message->set_interface_number($to);
    //                $message->set_gateway_data(["sid" => $message_sid]);
    //                $message->set_content($text);
    //                $message->set_status("received");
    //                $message->set_trigger("tlr_inbound_message");
    //                $message->set_member_id($member_id);
    //                $message->save();
    //                return "success";
    //            } else {
    //                return new WP_Error(
    //                    "rest_forbidden_request",
    //                    esc_html("Forbidden"),
    //                    ["status" => 403]
    //                );
    //            }
    //        }
    //    }

    public static function get_interfaces(): array
    {
        return [
            "spryng-sms" => "SMS",
        ];
    }

    public static function get_default_interface(): string
    {
        return "spryng-sms";
    }

    public static function get_interface_number(string $interface): string
    {
        return "";
    }

    public static function get_content_types(): array
    {
        return [
            "spryng-sms" => "text",
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
            "pre_update_option_tlr_gateway_spryng_api_key",
            [self::class, "update_encrypted_option"],
            10,
            2
        );
    }

    public static function add_options()
    {
        self::register_section([
            "id" => "tlr_gateway_spryng",
            "title" => __("Spryng", "texteller"),
            "desc" => sprintf(
                /* translators: %s: Gateway name */
                __("Configure %s gateway options", "texteller"),
                __("Spryng", "texteller")
            ),
            "class" => "description",
            "page" => "tlr_gateways",
        ]);

        $options = [
            [
                "id" => "tlr_gateway_spryng_api_key",
                "title" => __("Spryng API key", "texteller"),
                "page" => "tlr_gateways",
                "section" => "tlr_gateway_spryng",
                "desc" => __(
                    "Spryng account SID acquired from portal.spryngsms.com",
                    "texteller"
                ),
                "type" => "input",
                "params" => [
                    "type" => "password",
                ],
            ],
        ];
        self::register_options($options);
    }

    public static function render_gateway_status($current_section, $current_tab)
    {
        if (
            "tlr_gateway_spryng" !== $current_section ||
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
                    <span><?= esc_html($account_details["balance"]) ?></span>
                </div>
                <div class="gateway-info-label-wrap">
                    <span><?= esc_html__(
                        "Receive Endpoint",
                        "texteller"
                    ) ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                <span><?= esc_html(
                    get_rest_url(null, "texteller/v1/receive/spryng/")
                ) ?></span>
                </div><?php } ?>
        </div>
        </div>
		<?php
    }
}
