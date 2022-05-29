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
	    TLR\Message $message,
        array $action_gateway_data = []
    ) {
        if (!self::get_client()) {
            return false;
        }

        if ("spryng-sms" === $message->get_interface()) {
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

        $originator = self::get_originator();
        $route = self::get_route();

        $message = new Message();
        $message->setBody($text);
        $message->setRecipients([$number]);
        $message->setOriginator($originator);
        $message->setRoute($route);

        $response = self::$client->message->create($message);

        if ($response->wasSuccessful()) {
            $message = $response->toObject();
            $message_id = $message->getId();
            $used_originator = $message->getOriginator();
            return $message_id
                ? [
                    "data" => ["id" => $message_id],
                    "message_interface_number" => $used_originator,
                ]
                : false;
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
        $delivery_body = $request->get_query_params();

        if (
            "GET" === $method &&
            !empty($delivery_body["ID"]) &&
            !empty($delivery_body["STATUS"]) &&
            in_array($delivery_body["STATUS"], [10, 20])
        ) {
            $args = [
                "object_type" => "message",
                "statuses" => ["sent", "delivered", "failed", "pending"],
                "gateways" => ["spryng"],
                "field" => "ID",
                "gateway_data" => sanitize_text_field($delivery_body["ID"]),
            ];
            $message_query = new TLR\Object_Query($args);
            $message_id = $message_query->get_messages(1);
            $message = new TLR\Message($message_id[0]);
            if ($message->get_id()) {
                $message->set_status(
                    $delivery_body["STATUS"] == 10 ? "delivered" : "failed"
                );
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

    private static function get_originator(): string
    {
        $originator = get_option("tlr_gateway_spryng_originator", "Texteller");
        return empty($originator) ? "Texteller" : $originator;
    }

    private static function get_route(): int
    {
        $route = get_option("tlr_gateway_spryng_route", "business");
        if ($route === "business") {
            return 5;
        } elseif ($route === "economy") {
            return 6;
        } else {
            return 5;
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
            [
                "id" => "tlr_gateway_spryng_originator",
                "title" => __("Spryng originator", "texteller"),
                "page" => "tlr_gateways",
                "section" => "tlr_gateway_spryng",
                "desc" => __(
                    "The sender of the message for Spryng. Can be alphanumeric string (max. 11 characters) or phone number (max. 14 digits in E.164 format like 31612345678)",
                    "texteller"
                ),
                "type" => "input",
                "params" => [
                    "type" => "text",
                ],
            ],
            [
                "id" => "tlr_gateway_spryng_route",
                "title" => __("Spryng Route", "texteller"),
                "page" => "tlr_gateways",
                "section" => "tlr_gateway_spryng",
                "field_args" => ["default" => "business"],
                "desc" => __(
                    "Spryng messaging route to be used while sending messages.",
                    "texteller"
                ),
                "type" => "select",
                "params" => [
                    "options" => [
                        "business" => __("Spryng business", "texteller"),
                        "economy" => __("Spryng economy", "texteller"),
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
                        "Delivery Endpoint",
                        "texteller"
                    ) ?></span>
                </div>
                <div class="gateway-info-value-wrap">
                <span><?= esc_html(
                    get_rest_url(null, "texteller/v1/delivery/spryng")
                ) ?></span>
                </div><?php } ?>
        </div>
        </div>
		<?php
    }
}
