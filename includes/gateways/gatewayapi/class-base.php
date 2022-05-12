<?php

namespace Texteller\Gateways\GatewayAPI;

use Texteller as TLR;
use nickdnk\GatewayAPI\Exceptions\BaseException;
use nickdnk\GatewayAPI\Exceptions\ConnectionException;
use nickdnk\GatewayAPI\Exceptions\GatewayRequestException;
use nickdnk\GatewayAPI\Exceptions\GatewayServerException;
use nickdnk\GatewayAPI\Exceptions\InsufficientFundsException;
use nickdnk\GatewayAPI\Exceptions\MessageException;
use nickdnk\GatewayAPI\Exceptions\SuccessfulResponseParsingException;
use nickdnk\GatewayAPI\Exceptions\UnauthorizedException;
use WP_Error;
use WP_REST_Request;
use nickdnk\GatewayAPI\GatewayAPIHandler;
use nickdnk\GatewayAPI\Entities\Request\Recipient;
use nickdnk\GatewayAPI\Entities\Request\SMSMessage;

defined("ABSPATH") || exit();

class Base implements TLR\Interfaces\Gateway
{
    use TLR\Traits\Options_Base;
    use TLR\Traits\DateTime;
    use TLR\Traits\Encrypted_Options;

    /**
     * @var null|GatewayAPIHandler $client GatewayAPI Client
     */
    private static ?GatewayAPIHandler $client = null;

    /**
     * @var null|string $balance
     */
    private static ?string $balance = null;

    public function __construct()
    {
    }

    public static function get_client(): ?GatewayAPIHandler
    {
        if (self::$client && is_object(self::$client)) {
            return self::$client;
        }

        $key = get_option("tlr_gateway_gatewayapi_key", "");
        $secret = self::get_encrypted_option("tlr_gateway_gatewayapi_secret");

        if (!$secret || !$key) {
            return null;
        }

        self::$client = new GatewayAPIHandler($key, $secret);
        return self::$client;
    }

    public static function get_balance(): string
    {
        if (self::$balance) {
            return self::$balance;
        }

        if (!self::get_client()) {
            return "";
        }

        try {
            self::$balance = self::get_client()
                ->getCreditStatus()
                ->getCredit();
        } catch (ConnectionException | GatewayServerException | InsufficientFundsException | MessageException | SuccessfulResponseParsingException | UnauthorizedException | GatewayRequestException $e) {
            TLR\tlr_write_log(
                "GatewayAPI: An error occurred while getting account credit." .
                    $e->getMessage()
            );
            self::$balance = "";
        }

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

    public function send(
        string $text,
        string $number,
        string $interface = "",
        array $action_gateway_data = []
    ) {
        if (!self::get_client()) {
            return false;
        }

        if ("gatewayapi-sms" === $interface) {
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

        $sender_name = self::get_sender_name();

        $message = new SMSMessage($text, $sender_name);

        $message->addRecipient(new Recipient($number));

        try {
            $result = self::$client->deliverMessages([$message]);

            // All message IDs returned.
            $message_id = $result->getMessageIds();

            return $message_id
                ? [
                    "data" => ["id" => $message_id[0]],
                    "message_interface_number" => $sender_name,
                ]
                : false;
        } catch (InsufficientFundsException $e) {
            TLR\tlr_write_log(
                "GatewayAPI: our account has insufficient funds."
            );
        } catch (MessageException | SuccessfulResponseParsingException $e) {
            TLR\tlr_write_log(
                "GatewayAPI: An error occurred while sending the message. " .
                    $e->getGatewayAPIErrorCode() .
                    " | " .
                    $e->getMessage()
            );
        } catch (UnauthorizedException $e) {
            TLR\tlr_write_log(
                "GatewayAPI: Something is wrong with your credentials or your IP. " .
                    $e->getGatewayAPIErrorCode() .
                    " | " .
                    $e->getMessage()
            );
        } catch (GatewayServerException $e) {
            TLR\tlr_write_log(
                "GatewayAPI: Something is wrong with GatewayAPI servers. " .
                    $e->getGatewayAPIErrorCode() .
                    " | " .
                    $e->getMessage()
            );
        } catch (ConnectionException $e) {
            TLR\tlr_write_log(
                "GatewayAPI: Connection to GatewayAPI failed or timed out. " .
                    $e->getMessage()
            );
        } catch (BaseException $e) {
            TLR\tlr_write_log(
                "GatewayAPI: An error occurred while sending the message. " .
                    $e->getMessage()
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
		TLR\tlr_write_log(
			$request
		);
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
        return self::get_sender_name();
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

    private static function get_sender_name(): string
    {
        $originator = get_option(
            "tlr_gateway_gatewayapi_sender_name",
            "Texteller"
        );
        return empty($originator) ? "Texteller" : $originator;
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
