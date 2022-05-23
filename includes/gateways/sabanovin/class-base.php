<?php

namespace Texteller\Gateways\SabaNovin;

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
     * @var null|SabaNovin $client SabaNovin Client
     */
    private static ?SabaNovin $client = null;

    public function __construct()
    {
    }

    public static function get_client(): ?SabaNovin
    {
        if (self::$client && is_object(self::$client)) {
            return self::$client;
        }

        try {
            self::$client = new SabaNovin();
        } catch (Exception $e) {
            TLR\tlr_write_log(
                "SabaNovin: Failed to initialize client. " . $e->getMessage()
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
        $res = self::$client->get_balance();

        if (!is_wp_error($res) && !empty($res)) {
            $account_details["balance"] = $res;
        } else {
            TLR\tlr_write_log(
                "SabaNovin: An error occurred while getting account balance " .
                    $res->get_error_code() .
                    $res->get_error_message()
            );
        }

        return $account_details;
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

        if ("sabanovin-sms" === $interface) {
            return $this->send_sms($text, $number);
        } else {
            return false;
        }
    }

    private function send_sms(string $text, string $number)
    {
        if (empty(self::get_client())) {
            return false;
        }

        $result = self::$client->send_sms($number, $text);

        if (!is_wp_error($result)) {
            return !empty($result)
                ? [
                    "data" => ["reference_id" => $result->reference_id],
                ]
                : false;
        } else {
            TLR\tlr_write_log(
                "SabaNovin: An error occurred while sending the message. " .
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
        $delivery_body = $request->get_body_params();
        TLR\tlr_write_log($delivery_body);
        TLR\tlr_write_log($method);

        //        if ("POST" === $method && !empty($delivery_body["id"])) {
        //            $args = [
        //                "object_type" => "message",
        //                "statuses" => ["sent", "delivered", "failed", "pending"],
        //                "gateways" => ["bulksms"],
        //                "field" => "ID",
        //                "gateway_data" => sanitize_text_field($delivery_body["id"]),
        //            ];
        //            $message_query = new TLR\Object_Query($args);
        //            $message_id = $message_query->get_messages(1);
        //            if (!empty($message_id)) {
        //                $message = new TLR\Message($message_id[0]);
        //                if ($message->get_id()) {
        //                    if ($delivery_body["status"]["type"] == "DELIVERED") {
        //                        $status = "delivered";
        //                    } elseif ($delivery_body["status"]["type"] == "ACCEPTED") {
        //                        $status = "sent";
        //                    } else {
        //                        $status = "failed";
        //                    }
        //                    $to = $delivery_body["from"];
        //                    if ($message->get_interface_number() != $to) {
        //                        $message->set_interface_number($to);
        //                    }
        //                    $message->set_status($status);
        //                    $message->save();
        //                }
        //            }
        //        }

        return "success";
    }

    /**
     * @param WP_REST_Request $request Current request.
     *
     * @return string|WP_Error
     */
    public static function rest_receive_callback(WP_REST_Request $request)
    {
        $message_body = $request->get_params();

        if (isset($message_body["reference_id"])) {
            $reference_id = sanitize_text_field($message_body["reference_id"]);
            $text = isset($message_body["text"])
                ? sanitize_text_field($message_body["text"])
                : "";
            $gateway = isset($message_body["gateway"])
                ? sanitize_text_field($message_body["gateway"])
                : "";
            $from = isset($message_body["from"])
                ? sanitize_text_field($message_body["from"])
                : "";
            $member_id = TLR\tlr_get_member_id($from);

            if (!$from || !$gateway || !$reference_id) {
                return new WP_Error(
                    "rest_invalid_message_data",
                    esc_html("Invalid message data"),
                    ["status" => 404]
                );
            } else {
                $message = new TLR\Message();
                $message->set_recipient($from);
                $message->set_gateway("sabanovin");
                $message->set_interface("sabanovin-sms");
                $message->set_interface_number($gateway);
                $message->set_gateway_data(["reference_id" => $reference_id]);
                $message->set_content($text);
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
            "sabanovin-sms" => "SMS",
        ];
    }

    public static function get_default_interface(): string
    {
        return "sabanovin-sms";
    }

    public static function get_interface_number(string $interface): string
    {
        return self::get_client()::get_gateway_number();
    }

    public static function get_content_types(): array
    {
        return [
            "sabanovin-sms" => "text",
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
            "pre_update_option_tlr_gateway_sabanovin_api_key",
            [self::class, "update_encrypted_option"],
            10,
            2
        );
    }

    public static function add_options()
    {
        self::register_section([
            "id" => "tlr_gateway_sabanovin",
            "title" => __("SabaNovin", "texteller"),
            "desc" => sprintf(
                /* translators: %s: Gateway name */
                __("Configure %s gateway options", "texteller"),
                __("SabaNovin", "texteller")
            ),
            "class" => "description",
            "page" => "tlr_gateways",
        ]);

        $options = [
            [
                "id" => "tlr_gateway_sabanovin_api_key",
                "title" => _x(
                    "SabaNovin API key",
                    "SabaNovin gateway",
                    "texteller"
                ),
                "page" => "tlr_gateways",
                "section" => "tlr_gateway_sabanovin",
                "desc" => _x(
                    "SabaNovin API key acquired from sabanovin.com",
                    "SabaNovin gateway",
                    "texteller"
                ),
                "type" => "input",
                "params" => [
                    "type" => "password",
                ],
            ],
            [
                "id" => "tlr_gateway_sabanovin_gateway",
                "title" => _x(
                    "SabaNovin gateway number",
                    "SabaNovin gateway",
                    "texteller"
                ),
                "page" => "tlr_gateways",
                "section" => "tlr_gateway_sabanovin",
                "desc" => _x(
                    "SabaNovin gateway number acquired from sabanovin.com",
                    "SabaNovin gateway",
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
            "tlr_gateway_sabanovin" !== $current_section ||
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
                        "Delivery webhook",
                        "texteller"
                    ) ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                <span><?= esc_html(
                    get_rest_url(null, "texteller/v1/delivery/sabanovin")
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
                    get_rest_url(null, "texteller/v1/receive/sabanovin")
                ) ?></span>
                </div><?php } ?>
        </div>
        </div>
		<?php
    }
}
