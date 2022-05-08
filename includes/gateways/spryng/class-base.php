<?php

namespace Texteller\Gateways\Spryng;

use Spryng\SpryngRestApi\Spryng;

use Texteller as TLR;
use Twilio\Exceptions\ConfigurationException;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Api\V2010\AccountInstance;
use Twilio\Rest\Client;
use Twilio\Security\RequestValidator;
use WP_Error;
use WP_REST_Request;

defined("ABSPATH") || exit();

class Base implements TLR\Interfaces\Gateway
{
    use TLR\Traits\Options_Base;
    use TLR\Traits\DateTime;
    use TLR\Traits\Encrypted_Options;

    /**
     * @var null|Client $client Twilio Client
     */
    private static ?Spryng $client = null;

    /**
     * @var null|AccountInstance $account
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
            return '';
        }

	    $balance_object = self::get_client()->balance->get()->toObject();
	    self::$balance = $balance_object->getAmount();
        return (string) self::$balance;
    }

    public static function get_account_details()
    {
        $balance = self::get_balance();
        if ($balance === null | $balance === '') {
            return false;
        }

//        $date_created = empty($account->dateCreated)
//            ? $account->dateCreated
//            : "";
//
//        if ($date_created) {
//            self::init_datetime_formatter();
//            $date_created = self::format_datetime($date_created);
//        }
//
//        $friendly_name = empty($account->friendlyName)
//            ? $account->friendlyName
//            : "";
//        $status = empty($account->status) ? $account->status : "";
//        $type = empty($account->type) ? $account->type : "";
//
//        $balance = "";
//        if (empty($account->subresourceUris["balance"])) {
//            TLR\tlr_write_log(
//                "Twilio: Cannot get account balance subsource URI"
//            );
//        } else {
//            $balanceUrl =
//                "https://api.twilio.com" . $account->subresourceUris["balance"];
//            $balanceResponse = self::$client->request("GET", $balanceUrl);
//            $responseContent = $balanceResponse->getContent();
//            if (
//                !isset($responseContent["balance"]) ||
//                !isset($responseContent["currency"])
//            ) {
//                TLR\tlr_write_log("Twilio: Cannot get account balance details");
//            } else {
//                $balance =
//                    round($responseContent["balance"], 2) .
//                    " " .
//                    $responseContent["currency"];
//            }
//        }
        return [
//            "friendly_name" => $friendly_name,
//            "date_created" => $date_created,
//            "status" => $status,
//            "type" => $type,
            "balance" => $balance,
        ];
    }

    public static function get_client()
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

        if ("twilio-sms" === $interface) {
            return $this->send_sms($text, $number);
        } else {
            return false; // todo
        }
    }

    private function send_sms(string $text, string $number)
    {
        if (null === self::$client || !is_object(self::$client)) {
            return false;
        }
        try {
            $MessageInstance = self::$client->messages->create($number, [
                "from" => get_option("tlr_gateway_twilio_number"),
                "body" => $text,
                "statusCallback" => str_replace(
                    "http://localhost",
                    "https://c36d-83-83-117-146.eu.ngrok.io",
                    get_rest_url(null, "texteller/v1/delivery/twilio/")
                ),
            ]);
            $message_sid = $MessageInstance->sid;
            return $message_sid ? ["sid" => $message_sid] : false;
        } catch (TwilioException $e) {
            TLR\tlr_write_log(
                "Twilio: An error occurred. | " . $e->getMessage()
            );
            return false;
        }
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
            "POST" === $method &&
            !empty($delivery_body["MessageSid"]) &&
            !empty($delivery_body["MessageStatus"]) &&
            in_array(
                $delivery_body["MessageStatus"],
                ["sent", "delivered", "read", "failed"],
                true
            )
        ) {
            $args = [
                "object_type" => "message",
                "statuses" => ["sent", "delivered", "failed", "pending"],
                "gateways" => ["twilio"],
                "field" => "ID",
                "gateway_data" => sanitize_text_field(
                    $delivery_body["MessageSid"]
                ),
            ];
            $message_query = new TLR\Object_Query($args);
            $message_id = $message_query->get_messages(1);
            $message = new TLR\Message($message_id[0]);
            if ($message->get_id()) {
                $message->set_status($delivery_body["MessageStatus"]);
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

    /**
     * @param WP_REST_Request $request Current request.
     *
     * @return string|WP_Error
     */
    public static function rest_receive_callback(WP_REST_Request $request)
    {
        $method = $request->get_method();
        $message_body = $request->get_body_params();
        $message_sid = isset($message_body["MessageSid"])
            ? sanitize_text_field($message_body["MessageSid"])
            : "";
        $text = isset($message_body["Body"])
            ? sanitize_text_field($message_body["Body"])
            : "";
        $to = isset($message_body["To"])
            ? sanitize_text_field($message_body["To"])
            : "";
        $from = isset($message_body["From"])
            ? sanitize_text_field($message_body["From"])
            : "";
        $member_id = TLR\tlr_get_member_id($from);

        if ("POST" !== $method || !$from || !$to || !$message_sid) {
            return new WP_Error(
                "rest_invalid_message_data",
                esc_html("Invalid message data"),
                ["status" => 404]
            );
        } else {
            if ($to !== self::get_interface_number("twilio-sms")) {
                return new WP_Error(
                    "rest_invalid_inbound_number",
                    esc_html("Invalid inbound phone number"),
                    ["status" => 404]
                );
            }
            $token = self::get_encrypted_option(
                "tlr_gateway_twilio_auth_token"
            );
            $url = get_rest_url(null, "texteller/v1/receive/twilio/");
            $validator = new RequestValidator($token);

            if (
                $validator->validate(
                    $request->get_header("x-twilio-signature"),
                    $url,
                    $message_body
                )
            ) {
                $message = new TLR\Message();
                $message->set_recipient($from);
                $message->set_gateway("twilio");
                $message->set_interface("twilio-sms");
                $message->set_interface_number($to);
                $message->set_gateway_data(["sid" => $message_sid]);
                $message->set_content($text);
                $message->set_status("received");
                $message->set_trigger("tlr_inbound_message");
                $message->set_member_id($member_id);
                $message->save();
                return "success";
            } else {
                return new WP_Error(
                    "rest_forbidden_request",
                    esc_html("Forbidden"),
                    ["status" => 403]
                );
            }
        }
    }

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
        return '';
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
                "desc" => __("Spryng account SID acquired from portal.spryngsms.com", 'texteller'),
                "type" => "input",
                "params" => [
                    "type" => "password",
                ],
            ]
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
<!--                <div class="gateway-info-label-wrap">-->
<!--                    <span>--><?//= esc_html__(
//                        "Account Friendly Name",
//                        "texteller"
//                    ) ?><!--</span>-->
<!--                </div>-->
<!--                <div class="gateway-info-value-wrap">-->
<!--                    <span>--><?//= esc_html(
//                        $account_details["friendly_name"]
//                    ) ?><!--</span>-->
<!--                </div>-->
<!--                <div class="gateway-info-label-wrap">-->
<!--                    <span>--><?//= esc_html__("Date Created", "texteller") ?><!--</span>-->
<!--                </div>-->
<!--                <div class="gateway-info-value-wrap">-->
<!--                    <span>--><?//= esc_html(
//                        $account_details["date_created"]
//                    ) ?><!--</span>-->
<!--                </div>-->
<!--                <div class="gateway-info-label-wrap">-->
<!--                    <span>--><?//= esc_html__(
//                        "Account Status",
//                        "texteller"
//                    ) ?><!--</span>-->
<!--                </div>-->
<!--                <div class="gateway-info-value-wrap">-->
<!--                    <span>--><?//= esc_html($account_details["status"]) ?><!--</span>-->
<!--                </div>-->
<!--                <div class="gateway-info-label-wrap">-->
<!--                    <span>--><?//= esc_html__("Account Type", "texteller") ?><!--</span>-->
<!--                </div>-->
<!--                <div class="gateway-info-value-wrap">-->
<!--                    <span>--><?//= esc_html($account_details["type"]) ?><!--</span>-->
<!--                </div>-->
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
