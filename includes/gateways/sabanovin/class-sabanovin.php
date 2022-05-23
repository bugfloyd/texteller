<?php

namespace Texteller\Gateways\SabaNovin;

use Texteller as TLR;
use Exception;
use WP_Error;

class SabaNovin
{
    use TLR\Traits\Encrypted_Options;

    private string $api_key = "";

    private string $base_url = "https://api.sabanovin.com/v1";

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->set_api_key();

        if (empty($this->get_api_key())) {
            throw new Exception("Missing credentials.");
        }
    }

    private function set_api_key(): void
    {
        $api_key = self::get_encrypted_option("tlr_gateway_sabanovin_api_key");
        $this->api_key = $api_key;
    }

    private function get_api_key(): string
    {
        return $this->api_key;
    }

    private function get_auth_url(): string
    {
        return $this->base_url . "/" . $this->get_api_key();
    }

    public static function get_gateway_number(): string
    {
        return get_option("tlr_gateway_sabanovin_gateway", "");
    }

    public function send_sms(string $number, string $text)
    {
        $uri = "{$this->get_auth_url()}/sms/send.json";
        $gateway_number = self::get_gateway_number();

        if (empty($gateway_number)) {
            return new WP_Error("TLR_SABANOVIN_FAILED", "No gateway number");
        }

        // build the request
        $request = [
            "gateway" => $gateway_number,
            "to" => $number,
            "text" => $text,
        ];

        $res = wp_remote_request($uri, [
            "method" => "POST",
            "headers" => [
                "Content-Type" => "application/json",
            ],
            "body" => json_encode($request),
        ]);

        if (!is_wp_error($res)) {
            if (
                $res["response"]["code"] == 200 ||
                $res["response"]["code"] == 201
            ) {
                $body = json_decode($res["body"]);
                if ($body->status->code == 200) {
                    return $body->entries[0];
                }
            }
            $error_raw = $res["body"];
            $error = json_decode($error_raw);
            return new WP_Error(
                "TLR_SABANOVIN_FAILED",
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
                "TLR_SABANOVIN_TECH_FAILED",
                json_encode($res["body"])
            );
        }

        return new WP_Error(
            "TLR_BULKSMS_FAILED",
            "Failed to send SMS using BulkSMS."
        );
    }

    public function get_balance()
    {
        $uri = "{$this->get_auth_url()}/account/balance.json";

        $res = wp_remote_request($uri, [
            "method" => "GET",
            "headers" => [
                "Content-Type" => "application/json",
            ],
        ]);

        // not an error - hurray!
        if (!is_wp_error($res)) {
            if ($res["response"]["code"] == 200) {
                $body = json_decode($res["body"]);
                if ($body->status->code == 200) {
                    return $body->entry->balance;
                }
            }
            $error_raw = $res["body"];
            $error = json_decode($error_raw);
            return new WP_Error(
                "TLR_SABANOVIN_FAILED",
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
                "TLR_SABANOVIN_TECH_FAILED",
                json_encode($res["body"])
            );
        }

        return new WP_Error(
            "TLR_BULKSMS_FAILED",
            "Failed to get SABANOVIN balance."
        );
    }
}
