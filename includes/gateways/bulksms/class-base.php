<?php

namespace Texteller\Gateways\BulkSMS;

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
     * @var null|BulkSMS $client BulkSMS Client
     */
    private static ?BulkSMS $client = null;

    public function __construct()
    {
    }

    public static function get_client(): ?BulkSMS
    {
        if (self::$client && is_object(self::$client)) {
            return self::$client;
        }

        try {
            self::$client = new BulkSMS();
        } catch (Exception $e) {
            TLR\tlr_write_log(
                "BulkSMS: Failed to initialize BulkSMS client. " .
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
            $account_details["username"] = $res->username;
            $account_details["balance"] = $res->credits->balance;
            $account_details["created"] = $res->created;
            $account_details["first_name"] = $res->firstName;
            $account_details["last_name"] = $res->lastName;
            $account_details["remaining_quota"] = $res->quota->remaining;
        } else {
            TLR\tlr_write_log(
                "BulkSMS: An error occurred while getting account balance " .
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

        if ("bulksms-sms" === $message->get_interface()) {
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
                    "data" => ["id" => $result[0]->id],
                    "message_interface_number" => $result[0]->from,
                ]
                : false;
        } else {
            TLR\tlr_write_log(
                "BulkSMS: An error occurred while sending the message. " .
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
        $delivery_body = $delivery_body[0] ?? [];

        if ("POST" === $method && !empty($delivery_body["id"])) {
            $args = [
                "object_type" => "message",
                "statuses" => ["sent", "delivered", "failed", "pending"],
                "gateways" => ["bulksms"],
                "field" => "ID",
                "gateway_data" => sanitize_text_field($delivery_body["id"]),
            ];
            $message_query = new TLR\Object_Query($args);
            $message_id = $message_query->get_messages(1);
            if (!empty($message_id)) {
                $message = new TLR\Message($message_id[0]);
                if ($message->get_id()) {
                    if ($delivery_body["status"]["type"] == "DELIVERED") {
                        $status = "delivered";
                    } elseif ($delivery_body["status"]["type"] == "ACCEPTED") {
                        $status = "sent";
                    } else {
                        $status = "failed";
                    }
                    $to = $delivery_body["from"];
                    if ($message->get_interface_number() != $to) {
                        $message->set_interface_number($to);
                    }
                    $message->set_status($status);
                    $message->save();
                }
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
        $message_body = $request->get_params();

        $message_body = $message_body[0] ?? [];

        if (
            isset($message_body["type"]) &&
            "RECEIVED" === $message_body["type"]
        ) {
            $message_id = isset($message_body["id"])
                ? sanitize_text_field($message_body["id"])
                : "";
            $body = isset($message_body["body"])
                ? sanitize_text_field($message_body["body"])
                : "";
            $to = isset($message_body["to"])
                ? sanitize_text_field($message_body["to"])
                : "";
            $from = isset($message_body["from"])
                ? sanitize_text_field($message_body["from"])
                : "";
            $member_id = TLR\tlr_get_member_id($from);

            if ("POST" !== $method || !$from || !$to || !$message_id) {
                return new WP_Error(
                    "rest_invalid_message_data",
                    esc_html("Invalid message data"),
                    ["status" => 404]
                );
            } else {
                $message = new TLR\Message();
                $message->set_recipient($from);
                $message->set_gateway("bulksms");
                $message->set_interface("bulksms-sms");
                $message->set_interface_number($to);
                $message->set_gateway_data(["id" => $message_id]);
                $message->set_content($body);
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
            "bulksms-sms" => "SMS",
        ];
    }

    public static function get_default_interface(): string
    {
        return "bulksms-sms";
    }

    public static function get_interface_number(string $interface): string
    {
        return self::get_client()::get_sender_name();
    }

    public static function get_content_types(): array
    {
        return [
            "bulksms-sms" => "text",
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
            "pre_update_option_tlr_gateway_bulksms_token_secret",
            [self::class, "update_encrypted_option"],
            10,
            2
        );
    }

    public static function add_options()
    {
        self::register_section([
            "id" => "tlr_gateway_bulksms",
            "title" => __("BulkSMS", "texteller"),
            "desc" => sprintf(
                /* translators: %s: Gateway name */
                __("Configure %s gateway options", "texteller"),
                __("BulkSMS", "texteller")
            ),
            "class" => "description",
            "page" => "tlr_gateways",
        ]);

        $options = [
            [
                "id" => "tlr_gateway_bulksms_token_id",
                "title" => _x(
                    "BulkSMS token ID",
                    "BulkSMS gateway",
                    "texteller"
                ),
                "page" => "tlr_gateways",
                "section" => "tlr_gateway_bulksms",
                "desc" => _x(
                    "BulkSMS token ID acquired from bulksms.com",
                    "BulkSMS gateway",
                    "texteller"
                ),
                "type" => "input",
                "params" => [
                    "type" => "text",
                ],
            ],
            [
                "id" => "tlr_gateway_bulksms_token_secret",
                "title" => _x(
                    "BulkSMS token secret",
                    "BulkSMS gateway",
                    "texteller"
                ),
                "page" => "tlr_gateways",
                "section" => "tlr_gateway_bulksms",
                "desc" => _x(
                    "BulkSMS token secret acquired from bulksms.com",
                    "BulkSMS gateway",
                    "texteller"
                ),
                "type" => "input",
                "params" => [
                    "type" => "password",
                ],
            ],
            [
                "id" => "tlr_gateway_bulksms_sender_type",
                "page" => "tlr_gateways",
                "section" => "tlr_gateway_bulksms",
                "title" => __("BulkSMS sender name", "texteller"),
                "field_args" => ["default" => "REPLIABLE"],
                "desc" => _x(
                    "The sender name of the message for BulkSMS. Spaces are not allowed. Please note that you should first add the sender IDs in bulksms.com",
                    "BulkSMS gateway",
                    "texteller"
                ),
                "extra_options" => [
                    [
                        "id" =>
                            "tlr_gateway_bulksms_sender_international_address",
                        "page" => "tlr_gateways",
                        "section" => "tlr_gateway_bulksms",
                        "type" => "hidden",
                    ],
                    [
                        "id" =>
                            "tlr_gateway_bulksms_sender_alphanumeric_address",
                        "page" => "tlr_gateways",
                        "section" => "tlr_gateway_bulksms",
                        "type" => "hidden",
                    ],
                ],
                "type" => "radio",
                "params" => [
                    "values" => [
                        "REPLIABLE" => [
                            "label" => _x(
                                "Repliable",
                                "BulkSMS gateway",
                                "texteller"
                            ),
                            "desc" => _x(
                                "If you want BulkSMS to collect replies to this message on your behalf, specify the type as Repliable.",
                                "BulkSMS gateway",
                                "texteller"
                            ),
                        ],
                        "INTERNATIONAL" => [
                            "label" => _x(
                                "International",
                                "BulkSMS gateway",
                                "texteller"
                            ),
                            "input" => [
                                "id" =>
                                    "tlr_gateway_bulksms_sender_international_address",
                                "title" => _x(
                                    "Number",
                                    "BulkSMS gateway international input",
                                    "texteller"
                                ),
                            ],
                            "desc" => _x(
                                "International can start with +. It has a maximum length of 15 digits, and has to be longer than 6 digits.",
                                "BulkSMS gateway",
                                "texteller"
                            ),
                        ],
                        "ALPHANUMERIC" => [
                            "label" => _x(
                                "Alphanumeric",
                                "BulkSMS gateway",
                                "texteller"
                            ),
                            "input" => [
                                "id" =>
                                    "tlr_gateway_bulksms_sender_alphanumeric_address",
                                "title" => _x(
                                    "Text",
                                    "BulkSMS gateway alphanumeric input",
                                    "texteller"
                                ),
                            ],
                            "desc" => _x(
                                "Alphanumeric has a maximum length of 11 characters.",
                                "BulkSMS gateway",
                                "texteller"
                            ),
                        ],
                    ],
                ],
            ],
            [
                "id" => "tlr_gateway_bulksms_routing_group",
                "title" => __("BulkSMS routing group", "texteller"),
                "page" => "tlr_gateways",
                "section" => "tlr_gateway_bulksms",
                "field_args" => ["default" => "business"],
                "desc" => _x(
                    "BulkSMS messaging route to be used while sending messages.",
                    "BulkSMS gateway",
                    "texteller"
                ),
                "type" => "select",
                "params" => [
                    "options" => [
                        "STANDARD" => _x(
                            "Standard",
                            "BulkSMS gateway routing group",
                            "texteller"
                        ),
                        "ECONOMY" => _x(
                            "Economy",
                            "BulkSMS gateway routing group",
                            "texteller"
                        ),
                        "PREMIUM" => _x(
                            "Premium",
                            "BulkSMS gateway routing group",
                            "texteller"
                        ),
                    ],
                    "attribs" => [
                        "style" => "width:200px;height:34px;",
                    ],
                ],
            ],
        ];
        self::register_options($options);
    }

    public static function render_gateway_status($current_section, $current_tab)
    {
        if (
            "tlr_gateway_bulksms" !== $current_section ||
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
                    <span><?= esc_html__("Username", "texteller") ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                    <span><?= esc_html($account_details["username"]) ?></span>
                </div>
                <div class="gateway-info-label-wrap">
                    <span><?= esc_html__("First name", "texteller") ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                    <span><?= esc_html($account_details["first_name"]) ?></span>
                </div>
                <div class="gateway-info-label-wrap">
                    <span><?= esc_html__("Last name", "texteller") ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                    <span><?= esc_html($account_details["last_name"]) ?></span>
                </div>
                <div class="gateway-info-label-wrap">
                    <span><?= esc_html__("Created at", "texteller") ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                    <span><?= esc_html($account_details["created"]) ?></span>
                </div>
                <div class="gateway-info-label-wrap">
                    <span><?= esc_html__("Balance", "texteller") ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                    <span><?= esc_html($account_details["balance"]) ?></span>
                </div>
                <div class="gateway-info-label-wrap">
                    <span><?= esc_html__(
                        "Remaining quota",
                        "texteller"
                    ) ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                    <span><?= esc_html(
                        $account_details["remaining_quota"]
                    ) ?></span>
                </div>
                <div class="gateway-info-label-wrap">
                    <span><?= esc_html__(
                        "Delivery webhook",
                        "texteller"
                    ) ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                <span><?= esc_html(
                    get_rest_url(null, "texteller/v1/delivery/bulksms")
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
                    get_rest_url(null, "texteller/v1/receive/bulksms")
                ) ?></span>
                </div>
                <div class="gateway-info-label-wrap">
                <span><?= esc_html__("Note:", "texteller") ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                <span><?= esc_html_x(
                    "While setting webhooks on bulksms.com, you should choose 'only one message' for the 'Invoke with' option.",
                    "BulkSMS gateway details",
                    "texteller"
                ) ?></span>
                </div>
                <div class="gateway-info-label-wrap">
                    <span><?= esc_html__("Note:", "texteller") ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                <span><?= esc_html_x(
				        "Due to the way BulkSMS API works, it is possible to get delivery status for a message sent via 'Economy' route as 'Delivered' while the actual message is not delivered yet. ",
				        "BulkSMS gateway details",
				        "texteller"
			        ) ?></span>
                </div><?php } ?>
        </div>
        </div>
		<?php
    }
}
