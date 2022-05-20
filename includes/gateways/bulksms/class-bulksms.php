<?php

namespace Texteller\Gateways\BulkSMS;

use Texteller as TLR;
use Exception;
use WP_Error;

class BulkSMS
{
    use TLR\Traits\Encrypted_Options;

    private string $token_id = "";

    private string $token_secret = "";

    private string $base_url = "https://api.bulksms.com/v1";

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->set_token_id();
        $this->set_token_secret();

        if (empty($this->get_token_id()) || empty($this->get_token_secret())) {
            throw new Exception("Missing BulksSMS credentials.");
        }
    }

    private function set_token_id(): void
    {
        $key = get_option("tlr_gateway_bulksms_token_id", "");
        $this->token_id = $key;
    }

    private function set_token_secret(): void
    {
        $secret = self::get_encrypted_option(
            "tlr_gateway_bulksms_token_secret"
        );
        $this->token_secret = $secret;
    }

    private function get_token_id(): string
    {
        return $this->token_id;
    }

    private function get_token_secret(): string
    {
        return $this->token_secret;
    }

    private function get_auth_token(): string
    {
        $token_id = $this->get_token_id();
        $token_secret = $this->get_token_secret();

        // OAuth 1.0a - Sign SBS with secret
        return base64_encode($token_id . ":" . $token_secret);
    }

    public function send_sms(string $number, string $text)
    {
        $uri = "$this->base_url/messages?auto-unicode=true";

        // build the request
        $request = [
            [
                "from" => self::get_sender_object(),
                "to" => [
                    "type" => "INTERNATIONAL",
                    "address" => $number,
                ],
                "routingGroup" => self::get_routing_group(),
                "body" => $text,
            ],
        ];

        $res = wp_remote_request($uri, [
            "method" => "POST",
            "headers" => [
                "Authorization" => "Basic {$this->get_auth_token()}",
                "Content-Type" => "application/json",
            ],
            "body" => json_encode($request),
        ]);

        if (!is_wp_error($res)) {
            if (
                $res["response"]["code"] == 200 ||
                $res["response"]["code"] == 201
            ) {
                return json_decode($res["body"]);
            }
            $error_raw = $res["body"];
            $error = json_decode($error_raw);
            return new WP_Error(
                "TLR_BULKSMS_FAILED",
                $error && isset($error->message) && $error->message
                    ? $error->message .
                        "\nCode " .
                        $error->code .
                        "\nUUID: " .
                        $error->incident_uuid
                    : $res["response"]["code"] . "\n" . $error_raw
            );
        }

        if (is_wp_error($res) && !isset($res->errors["http_request_failed"])) {
            return new WP_Error(
                "TLR_BULKSMS_TECH_FAILED",
                json_encode($res["body"])
            );
        }

        return new WP_Error(
            "TLR_BULKSMS_FAILED",
            "Failed to send SMS using BulkSMS."
        );
    }

    public function get_account_details()
    {
        $uri = "$this->base_url/profile";

        $res = wp_remote_request($uri, [
            "method" => "GET",
            "headers" => [
                "Authorization" => "Basic {$this->get_auth_token()}",
                "Content-Type" => "application/json",
            ],
        ]);

        // not an error - hurray!
        if (!is_wp_error($res)) {
            if ($res["response"]["code"] == 200) {
                return json_decode($res["body"]);
            }
            $error_raw = $res["body"];
            $error = json_decode($error_raw);
            return new WP_Error(
                "TLR_BULKSMS_FAILED",
                $error && isset($error->message) && $error->message
                    ? $error->message .
                        "\nCode " .
                        $error->code .
                        "\nUUID: " .
                        $error->incident_uuid
                    : $res["response"]["code"] . "\n" . $error_raw
            );
        }

        if (is_wp_error($res) && !isset($res->errors["http_request_failed"])) {
            return new WP_Error(
                "TLR_BULKSMS_TECH_FAILED",
                json_encode($res["body"])
            );
        }

        return new WP_Error(
            "TLR_BULKSMS_FAILED",
            "Failed to get BulkSMS profile."
        );
    }

    public static function get_sender_object(): array
    {
        $sender_type = get_option(
            "tlr_gateway_bulksms_sender_type",
            "REPLIABLE"
        );
        $sender_address = "";

        if ($sender_type === "INTERNATIONAL") {
            $sender_address = get_option(
                "tlr_gateway_bulksms_sender_international_address",
                ""
            );
	        return ["type" => $sender_type, "address" => $sender_address];
        }

        if ($sender_type === "ALPHANUMERIC") {
            $sender_address = get_option(
                "tlr_gateway_bulksms_sender_alphanumeric_address",
                "Texteller"
            );
	        return ["type" => $sender_type, "address" => $sender_address];
        }

        return ["type" => $sender_type];
    }

    public static function get_sender_name(): string
    {
        $sender = self::get_sender_object();
        if ($sender["type"] === "REPLIABLE") {
            return "repliable";
        } else {
            return $sender["address"];
        }
    }

    public static function get_routing_group(): string
    {
        return get_option("tlr_gateway_bulksms_routing_group", "STANDARD");
    }
}
