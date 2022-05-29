<?php

namespace Texteller\Gateways\Twilio;
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
    private static ?Client $client = null;

    /**
     * @var null|AccountInstance $account
     */
    private static ?AccountInstance $account = null;

    public function __construct()
    {
    }

    public static function get_account()
    {
        if (self::$account && is_object(self::$account)) {
            return self::$account;
        }

        $initialized = self::get_client();
        if (!$initialized) {
            return false;
        }
        try {
            self::$account = self::$client->api->v2010
                ->accounts(self::$client->getAccountSid())
                ->fetch();
            return self::$account;
        } catch (TwilioException $e) {
            TLR\tlr_write_log(
                "Twilio: An error occurred. | " . $e->getMessage()
            );
            return false;
        }
    }

    public static function get_incoming_phone_numbers()
    {
        $account = self::get_account();
        if (!$account) {
            return false;
        }

        if (empty($account->subresourceUris["incoming_phone_numbers"])) {
            TLR\tlr_write_log(
                "Twilio: Cannot get account incoming_phone_numbers subsource URI"
            );
        } else {
            $numbersUrl =
                "https://api.twilio.com" .
                $account->subresourceUris["incoming_phone_numbers"];
            $numbersResponse = self::$client->request("GET", $numbersUrl);
            $responseContent = $numbersResponse->getContent();
            if (!isset($responseContent["incoming_phone_numbers"])) {
                TLR\tlr_write_log(
                    "Twilio: Cannot get incoming_phone_numbers details"
                );
            } else {
                return $responseContent["incoming_phone_numbers"];
            }
        }
        return false;
    }

    public static function get_account_details()
    {
        $account = self::get_account();
        if (!$account) {
            return false;
        }

        $date_created = empty($account->dateCreated)
            ? $account->dateCreated
            : "";

        if ($date_created) {
            self::init_datetime_formatter();
            $date_created = self::format_datetime($date_created);
        }

        $friendly_name = empty($account->friendlyName)
            ? $account->friendlyName
            : "";
        $status = empty($account->status) ? $account->status : "";
        $type = empty($account->type) ? $account->type : "";

        $balance = "";
        if (empty($account->subresourceUris["balance"])) {
            TLR\tlr_write_log(
                "Twilio: Cannot get account balance subsource URI"
            );
        } else {
            $balanceUrl =
                "https://api.twilio.com" . $account->subresourceUris["balance"];
            $balanceResponse = self::$client->request("GET", $balanceUrl);
            $responseContent = $balanceResponse->getContent();
            if (
                !isset($responseContent["balance"]) ||
                !isset($responseContent["currency"])
            ) {
                TLR\tlr_write_log("Twilio: Cannot get account balance details");
            } else {
                $balance =
                    round($responseContent["balance"], 2) .
                    " " .
                    $responseContent["currency"];
            }
        }
        return [
            "friendly_name" => $friendly_name,
            "date_created" => $date_created,
            "status" => $status,
            "type" => $type,
            "balance" => $balance,
        ];
    }

    public static function get_client()
    {
        if (self::$client && is_object(self::$client)) {
            return self::$client;
        }

        $sid = get_option("tlr_gateway_twilio_sid", "");
        $token = self::get_encrypted_option("tlr_gateway_twilio_auth_token");

        if (!$sid || !$token) {
            return null;
        }

        try {
            self::$client = new Client($sid, $token);
            return self::$client;
        } catch (ConfigurationException $e) {
            TLR\tlr_write_log(
                "Twilio authentication failed. | " . $e->getCode()
            );
            return false;
        }
    }

    public function send(
	    TLR\Message $message,
        array $action_gateway_data = []
    ) {
        if (!self::get_client()) {
            return false;
        }

        if ("twilio-sms" === $message->get_interface()) {
            return $this->send_sms($message->get_content(), $message->get_recipient());
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
            return $message_sid ? ['data' => ["sid" => $message_sid]] : false;
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
            "twilio-sms" => "SMS",
        ];
    }

    public static function get_default_interface(): string
    {
        return "twilio-sms";
    }

    public static function get_interface_number(string $interface): string
    {
        return get_option("tlr_gateway_twilio_number", "");
    }

    public static function get_content_types(): array
    {
        return [
            "twilio-sms" => "text",
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
            [self::class, "render_gateway_status"],
            10,
            2
        );
        add_filter(
            "pre_update_option_tlr_gateway_twilio_auth_token",
            [self::class, "update_encrypted_option"],
            10,
            2
        );
        add_filter("pre_update_option_tlr_gateway_twilio_number", [
            self::class,
            "initialize_phone_numbers",
        ]);
    }

    public static function initialize_phone_numbers($value)
    {
        if ($value) {
            return $value;
        }
        $numbers = self::get_incoming_phone_numbers();
        if ($numbers && !empty($numbers[0]["phone_number"])) {
            return $numbers[0]["phone_number"];
        }
        return $value;
    }

    public static function add_options()
    {
        self::register_section([
            "id" => "tlr_gateway_twilio",
            "title" => __("Twilio", "texteller"),
            "desc" => sprintf(
                /* translators: %s: Gateway name */
                __("Configure %s gateway options", "texteller"),
                __("Twilio", "texteller")
            ),
            "class" => "description",
            "page" => "tlr_gateways",
        ]);

        $options = [
            [
                "id" => "tlr_gateway_twilio_sid",
                "title" => __("Twilio SID", "texteller"),
                "page" => "tlr_gateways",
                "section" => "tlr_gateway_twilio",
                "desc" => "Twilio account SID acquired from twilio.com/console",
                "type" => "input",
                "params" => [
                    "type" => "text",
                ],
            ],
            [
                "id" => "tlr_gateway_twilio_auth_token",
                "title" => "Twilio Auth Token",
                "page" => "tlr_gateways",
                "section" => "tlr_gateway_twilio",
                "desc" =>
                    "Twilio account Auth Token acquired from twilio.com/console",
                "type" => "input",
                "params" => [
                    "type" => "password",
                ],
            ],
            [
                "id" => "tlr_gateway_twilio_number",
                "title" => "Twilio Phone Number",
                "page" => "tlr_gateways",
                "section" => "tlr_gateway_twilio",
                "desc" =>
                    "The Twilio phone number purchased from twilio.com/console",
                "type" => [self::class, "render_number_selector"],
                "params" => [
                    "type" => "text",
                ],
            ],
        ];
        self::register_options($options);
    }

    public function render_number_selector()
    {
        $stored_value = get_option("tlr_gateway_twilio_number", "");
        $numbers = self::get_incoming_phone_numbers();
        ob_start();
        ?>
		<select name="tlr_gateway_twilio_number" aria-label="Select gateway phone number to be used in the plugin.">
			<?php if ($numbers === false) { ?>
				<option value="0"><?= esc_html__(
        "Please save valid authentication credentials",
        "texteller"
    ) ?></option>
				<?php } elseif (empty($numbers)) { ?>
                <option value="0"><?= esc_html__(
                    "Please buy a number at twilio!",
                    "texteller"
                ) ?></option>
				<?php } else {foreach ((array) $numbers as $number) {
           if (!empty($number["phone_number"])) { ?>
						<option value="<?= esc_attr($number["phone_number"]) ?>"<?php selected(
    $stored_value,
    $number["phone_number"]
); ?>><?= esc_html($number["friendly_name"]) ?></option>
						<?php }
       }} ?>
		</select>
		<?php return ob_get_clean();
    }

    public static function render_gateway_status($current_section, $current_tab)
    {
        if (
            "tlr_gateway_twilio" !== $current_section ||
            "tlr_gateways" !== $current_tab
        ) {
            return;
        }
        $account_details = self::get_account_details();
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
                    <span><?= esc_html__(
                        "Account Friendly Name",
                        "texteller"
                    ) ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                    <span><?= esc_html(
                        $account_details["friendly_name"]
                    ) ?></span>
                </div>
                <div class="gateway-info-label-wrap">
                    <span><?= esc_html__("Date Created", "texteller") ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                    <span><?= esc_html(
                        $account_details["date_created"]
                    ) ?></span>
                </div>
                <div class="gateway-info-label-wrap">
                    <span><?= esc_html__(
                        "Account Status",
                        "texteller"
                    ) ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                    <span><?= esc_html($account_details["status"]) ?></span>
                </div>
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
                        "Receive Endpoint",
                        "texteller"
                    ) ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                <span><?= esc_html(
                    get_rest_url(null, "texteller/v1/receive/twilio/")
                ) ?></span>
                </div><?php } ?>
        </div>
        </div>
		<?php
    }
}
