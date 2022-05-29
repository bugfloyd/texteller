<?php

namespace Texteller\Gateways\Textlocal;

use Texteller as TLR;
use Exception;
use WP_Error;

class Textlocal
{
    use TLR\Traits\Encrypted_Options;

    private string $api_key = "";

    private string $base_url = "https://api.txtlocal.com";

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->set_api_key();

        if (empty($this->get_api_key())) {
            throw new Exception("Missing Textlocal credentials.");
        }
    }

    private function set_api_key(): void
    {
        $api_key = self::get_encrypted_option(
            "tlr_gateway_textlocal_api_key"
        );
        $this->api_key = $api_key;
    }

    private function get_api_key(): string
    {
        return $this->api_key;
    }

    public function send_sms(TLR\Message $message)
    {
        $uri = "$this->base_url/send";

        // build the request
        $request = [
	        "apiKey" => $this->get_api_key(),
	        "sender" => urlencode(self::get_sender_name()),
	        "numbers" => $message->get_recipient(),
	        "message" => rawurlencode($message->get_content()),
	        "custom" => $message->get_id()
        ];

        $res = wp_remote_request($uri, [
            "method" => "POST",
            "body" => $request,
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
                "TLR_TEXTLOCAL_FAILED",
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
                "TLR_TEXTLOCAL_TECH_FAILED",
                json_encode($res["body"])
            );
        }

        return new WP_Error(
            "TLR_TEXTLOCAL_FAILED",
            "Failed to send SMS using BulkSMS."
        );
    }

    public function get_account_details()
    {
        $uri = "$this->base_url/balance";

	    $request = [
		    "apiKey" => $this->get_api_key()
	    ];

        $res = wp_remote_request($uri, [
            "method" => "POST",
            "body" => $request,
        ]);

        // not an error - hurray!
        if (!is_wp_error($res)) {
            if ($res["response"]["code"] == 200) {
				$body = json_decode($res["body"]);
				if ($body->status === 'success') {
					return $body;
				}
            }
            $error_raw = $res["body"];
            $error = json_decode($error_raw);
            return new WP_Error(
                "TLR_TEXTLOCAL_FAILED",
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
                "TLR_TEXTLOCAL_TECH_FAILED",
                json_encode($res["body"])
            );
        }

        return new WP_Error(
            "TLR_TEXTLOCAL_FAILED",
            "Failed to get Textlocal balance."
        );
    }

    public static function get_sender_name(): string
    {
        return get_option("tlr_gateway_textlocal_sender", "Texteller");
    }
}
