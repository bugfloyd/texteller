<?php

namespace Texteller\Gateways\GatewayAPI;

use Texteller as TLR;
use Exception;
use WP_Error;

class GatewayAPI
{
    use TLR\Traits\Encrypted_Options;

    private string $api_key = "";

    private string $api_secret = "";

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->set_api_key();
        $this->set_api_secret();

        if (empty($this->get_api_key()) || empty($this->get_api_secret())) {
            throw new Exception("Missing GatewayAPI credentials.");
        }
    }

    private function set_api_key(): void
    {
        $key = get_option("tlr_gateway_gatewayapi_key", "");
        $this->api_key = $key;
    }

    private function set_api_secret(): void
    {
        $secret = self::get_encrypted_option("tlr_gateway_gatewayapi_secret");
        $this->api_secret = $secret;
    }

    private function get_api_key(): string
    {
        return $this->api_key;
    }

    private function get_api_secret(): string
    {
        return $this->api_secret;
    }

    private function get_auth_header(
        string $uri,
        string $ts,
        string $method = "POST"
    ): string {
        $consumer_key = rawurlencode($this->get_api_key());
        $secret = rawurlencode($this->get_api_secret());
        $nonce = rawurlencode(uniqid(false, true));

        // OAuth 1.0a - Signature Base String
        $oauth_params = [
            "oauth_consumer_key" => $consumer_key,
            "oauth_nonce" => $nonce,
            "oauth_signature_method" => "HMAC-SHA1",
            "oauth_timestamp" => $ts,
            "oauth_version" => "1.0",
        ];

        $sbs = "$method&" . rawurlencode($uri) . "&";
        $sbsA = [];
        foreach ($oauth_params as $key => $val) {
            $sbsA[] = $key . "%3D" . $val;
        }
        $sbs .= implode("%26", $sbsA);

        // OAuth 1.0a - Sign SBS with secret
        $sig = base64_encode(hash_hmac("sha1", $sbs, $secret . "&", true));
        $oauth_params["oauth_signature"] = rawurlencode($sig);

        // Construct Authorization header
        $auth = "OAuth ";
        $authA = [];
        foreach ($oauth_params as $key => $val) {
            $authA[] = $key . '="' . $val . '"';
        }
        $auth .= implode(", ", $authA);

        return $auth;
    }

    public function send_sms(string $number, string $text)
    {
        // build the request
        $request = [
            "recipients" => [
                [
                    "msisdn" => $number,
                    "tagvalues" => [],
                ],
            ],
            "message" => $text,
            "destaddr" => "MOBILE",
            "encoding" => "UTF8",
        ];
        $sender = self::get_sender_name();
        if ($sender) {
            $request["sender"] = $sender;
        }

        // possible URIs
        $uris = [
            "https://gatewayapi.com/rest/mtsms",
            "https://badssl.gatewayapi.com/rest/mtsms",
            "http://badssl.gatewayapi.com/rest/mtsms",
        ];

        $ts = time() - 3;
        foreach ($uris as $i => $uri) {
            $ts = rawurlencode($ts + $i);
            $res = wp_remote_request($uri, [
                "method" => "POST",
                "headers" => [
                    "Authorization" => $this->get_auth_header($uri, $ts),
                    "Content-Type" => "application/json",
                    "user-agent" => "wp-gatewayapi",
                ],
                "body" => json_encode($request),
            ]);

            // not an error - hurray!
            if (!is_wp_error($res)) {
                if ($res["response"]["code"] == 200) {
                    return current(json_decode($res["body"])->ids);
                }
                $error_raw = $res["body"];
                $error = json_decode($error_raw);
                return new WP_Error(
                    "TLR_GWAPI_FAILED",
                    $error && isset($error->message) && $error->message
                        ? $error->message .
                            "\nCode " .
                            $error->code .
                            "\nUUID: " .
                            $error->incident_uuid
                        : $res["response"]["code"] . "\n" . $error_raw
                );
            }

            // error: BUT no reason to try another URL as this is not communications related
            if (
                is_wp_error($res) &&
                !isset($res->errors["http_request_failed"])
            ) {
                return new WP_Error(
                    "TLR_GWAPI_TECH_FAILED",
                    json_encode($res["body"])
                );
            }
        }

        return new WP_Error(
            "TLR_GWAPI_FAILED",
            "Failed to send SMS using GatewayAPI."
        );
    }

    public function get_account_details()
    {
        // possible URIs
        $uris = [
            "https://gatewayapi.com/rest/me",
            "https://badssl.gatewayapi.com/rest/me",
            "http://badssl.gatewayapi.com/rest/me",
        ];

        $ts = time() - 3;
        foreach ($uris as $i => $uri) {
            $ts = rawurlencode($ts + $i);
            $res = wp_remote_request($uri, [
                "method" => "GET",
                "headers" => [
                    "Authorization" => $this->get_auth_header($uri, $ts, "GET"),
                    "Content-Type" => "application/json",
                    "user-agent" => "wp-gatewayapi",
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
                    "TLR_GWAPI_FAILED",
                    $error && isset($error->message) && $error->message
                        ? $error->message .
                            "\nCode " .
                            $error->code .
                            "\nUUID: " .
                            $error->incident_uuid
                        : $res["response"]["code"] . "\n" . $error_raw
                );
            }

            // error: BUT no reason to try another URL as this is not communications related
            if (
                is_wp_error($res) &&
                !isset($res->errors["http_request_failed"])
            ) {
                return new WP_Error(
                    "TLR_GWAPI_TECH_FAILED",
                    json_encode($res["body"])
                );
            }
        }

        return new WP_Error("TLR_GWAPI_FAILED", "Failed to get balance.");
    }

    public static function get_sender_name(): string
    {
        $originator = get_option(
            "tlr_gateway_gatewayapi_sender_name",
            "Texteller"
        );
        return empty($originator) ? "Texteller" : $originator;
    }
}
