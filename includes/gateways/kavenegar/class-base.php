<?php

namespace Texteller\Gateways\Kavenegar;

use stdClass;
use Texteller as TLR;
use Kavenegar\Exceptions\ApiException;
use Kavenegar\Exceptions\HttpException;
use Kavenegar\KavenegarApi;
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
     * @var null|KavenegarApi $client Kavenegar Client
     */
    private ?KavenegarApi $client = null;

    /**
     * @var string $phone_number Kavenegar phone number
     */
    private string $phone_number = "";

    /**
     * @var null|stdClass $account
     */
    private ?stdClass $account = null;

    public function __construct()
    {
    }

    public function get_account()
    {
        if ($this->account) {
            return $this->account;
        }

        if (!$this->get_client()) {
            return false;
        }
        try {
            $this->account = $this->client->AccountInfo();
            return $this->account;
        } catch (ApiException $e) {
            TLR\tlr_write_log(
                "Kavenegar: An error occurred. | " . $e->errorMessage()
            );
        } catch (HttpException $e) {
            TLR\tlr_write_log(
                "Kavenegar: A connection error occurred. | " .
                    $e->errorMessage()
            );
        }
        return false;
    }

    public function get_account_details()
    {
        $account = $this->get_account();
        if (!$account) {
            return false;
        }

        $config = $this->client->AccountConfig(
            null,
            null,
            null,
            null,
            null,
            null
        );

        $type = !empty($account->type) ? $account->type : "";
        $balance = $account->remaincredit;

        return [
            "type" => $type,
            "balance" => $balance,
            "debug_mode" => $config->debugmode,
            "default_sender" => $config->defaultsender,
            "daily_report" => $config->dailyreport,
            "min_credit_alarm" => $config->mincreditalarm,
            "resend_failed" => $config->resendfailed,
            "api_logs" => $config->apilogs,
        ];
    }

    public function get_client(): ?KavenegarApi
    {
        if ($this->client && is_object($this->client)) {
            return $this->client;
        }

        $api_key = self::get_encrypted_option("tlr_gateway_kavenegar_api_key");
        if (!$api_key) {
            return null;
        }

        $this->client = new KavenegarApi($api_key);
        return $this->client;
    }

    public function send(
        string $text,
        string $number,
        string $interface = "",
        array $action_gateway_data = []
    ) {
        if (!$this->get_client()) {
            return false;
        }

        $this->phone_number = self::get_interface_number($interface);

        if ("kavenegar-sms" === $interface) {
            return $this->send_sms($text, $number);
        } else {
            return false; // todo
        }
    }

    private function send_sms(string $text, string $number)
    {
        if (!is_object($this->client)) {
            return false;
        }

        try {
            $message = $text;
            $receptor = [$number];
            $result = $this->client->Send(
                $this->phone_number,
                $receptor,
                $message
            );
            if ($result[0]) {
                return $result[0]->messageid
                    ? [
                        "data" => ["messageid" => $result[0]->messageid],
                        "message_interface_number" => $result[0]->sender,
                    ]
                    : false;
            }
        } catch (ApiException $e) {
            TLR\tlr_write_log(
                "Kavenegar: An error occurred. | " . $e->errorMessage()
            );
        } catch (HttpException $e) {
            TLR\tlr_write_log(
                "Kavenegar: A connection error occurred. | " .
                    $e->errorMessage()
            );
        }
        return false;
    }

    //    /**
    //     * @param WP_REST_Request $request Current request.
    //     *
    //     * @return string|WP_Error
    //     */
    //    public static function rest_delivery_callback(WP_REST_Request $request)
    //    {
    //        $method = $request->get_method();
    //        $delivery_body = $request->get_body_params();
    //        if (
    //            "POST" === $method &&
    //            !empty($delivery_body["MessageSid"]) &&
    //            !empty($delivery_body["MessageStatus"]) &&
    //            in_array(
    //                $delivery_body["MessageStatus"],
    //                ["sent", "delivered", "read", "failed"],
    //                true
    //            )
    //        ) {
    //            $args = [
    //                "object_type" => "message",
    //                "statuses" => ["sent", "delivered", "failed", "pending"],
    //                "gateways" => ["twilio"],
    //                "field" => "ID",
    //                "gateway_data" => sanitize_text_field(
    //                    $delivery_body["MessageSid"]
    //                ),
    //            ];
    //            $message_query = new TLR\Object_Query($args);
    //            $message_id = $message_query->get_messages(1);
    //            $message = new TLR\Message($message_id[0]);
    //            if ($message->get_id()) {
    //                $message->set_status($delivery_body["MessageStatus"]);
    //                $message->save();
    //                return "success";
    //            }
    //        }
    //        return new WP_Error(
    //            "rest_invalid_message_data",
    //            esc_html("Invalid message data"),
    //            ["status" => 404]
    //        );
    //    }

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
            "kavenegar-sms" => "SMS",
        ];
    }

    public static function get_default_interface(): string
    {
        return "kavenegar-sms";
    }

    public static function get_interface_number(string $interface): string
    {
        return get_option("tlr_gateway_kavenegar_number", "");
    }

    public static function get_content_types(): array
    {
        return [
            "kavenegar-sms" => "text",
        ];
    }

    public static function is_interface_active(string $interface): bool
    {
        // TODO: Implement is_interface_active() method.
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
            [$this, "render_gateway_status"],
            10,
            2
        );
        add_filter(
            "pre_update_option_tlr_gateway_kavenegar_api_key",
            [self::class, "update_encrypted_option"],
            10,
            2
        );
    }

    public static function add_options()
    {
        self::register_section([
            "id" => "tlr_gateway_kavenegar",
            "title" => __("Kavenegar", "texteller"),
            "desc" => sprintf(
                /* translators: %s: Gateway name */
                __("Configure %s gateway options", "texteller"),
                __("Kavenegar", "texteller")
            ),
            "class" => "description",
            "page" => "tlr_gateways",
        ]);

        $options = [
            [
                "id" => "tlr_gateway_kavenegar_api_key",
                "title" => __("Kavenegar API Key", "texteller"),
                "page" => "tlr_gateways",
                "section" => "tlr_gateway_kavenegar",
                "desc" => __(
                    "Kavenegar API key acquired from panel.kavenegar.com",
                    "texteller"
                ),
                "type" => "input",
                "params" => [
                    "type" => "password",
                ],
            ],
            [
                "id" => "tlr_gateway_kavenegar_number",
                "title" => __("Kavenegar Sender Number", "texteller"),
                "page" => "tlr_gateways",
                "section" => "tlr_gateway_kavenegar",
                "desc" => __(
                    "The Kavenegar phone number purchased from panel.kavenegar.com/client/Lines",
                    "texteller"
                ),
                "helper" => __(
                    "To use the default phone number of the account, leave this field empty",
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

    public function render_gateway_status($current_section, $current_tab)
    {
        if (
            "tlr_gateway_kavenegar" !== $current_section ||
            "tlr_gateways" !== $current_tab
        ) {
            return;
        }
        $account_details = $this->get_account_details();
        $is_authenticated = (bool) $account_details;
        ?><div class="gateway-checker-wrap">
        <h4 style="margin-top:0;"><?= __(
            "API Connection Status and Account Details",
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
                    <span><?= esc_html__("Account Type", "texteller") ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                    <span><?= esc_html($account_details["type"]) ?></span>
                </div>
                <div class="gateway-info-label-wrap">
                    <span><?= esc_html__("Balance", "texteller") ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                    <span><?= esc_html($account_details["balance"]) ?></span>
                </div>
                <div class="gateway-info-label-wrap">
                    <span><?= esc_html__(
                        "Default sender",
                        "texteller"
                    ) ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                    <span><?= esc_html(
                        $account_details["default_sender"]
                    ) ?></span>
                </div>
                <div class="gateway-info-label-wrap">
                    <span><?= esc_html__("Debug mode", "texteller") ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                    <span><?= esc_html($account_details["debug_mode"]) ?></span>
                </div>
                <div class="gateway-info-label-wrap">
                    <span><?= esc_html__("Daily report", "texteller") ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                    <span><?= esc_html(
                        $account_details["daily_report"]
                    ) ?></span>
                </div>
                <div class="gateway-info-label-wrap">
                    <span><?= esc_html__(
                        "Minimum credit alarm",
                        "texteller"
                    ) ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                    <span><?= esc_html(
                        $account_details["min_credit_alarm"]
                    ) ?></span>
                </div>
                <div class="gateway-info-label-wrap">
                    <span><?= esc_html__("Resend failed", "texteller") ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                    <span><?= esc_html(
                        $account_details["resend_failed"]
                    ) ?></span>
                </div>
                <div class="gateway-info-label-wrap">
                    <span><?= esc_html__("API logs", "texteller") ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                    <span><?= esc_html($account_details["api_logs"]) ?></span>
                </div>
                <div class="gateway-info-label-wrap">
                    <span><?= esc_html__(
                        "Receive Endpoint",
                        "texteller"
                    ) ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                <span><?= esc_html(
                    get_rest_url(null, "texteller/v1/receive/kavenegar/")
                ) ?></span>
                </div><?php } ?>
        </div>
        </div>
		<?php
    }
}
