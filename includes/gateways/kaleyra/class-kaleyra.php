<?php

namespace Texteller\Gateways\Kaleyra;

use Exception;
use Texteller as TLR;

class Kaleyra
{
    private string $sid = "";

    private string $api_key = "";

    private string $api_url = "";

	private string $delivery_callback = "";

	/**
	 * @throws Exception
	 */
	public function __construct(string $sid, string $api_key, string $delivery_callback)
    {
        if (empty($sid) || empty($api_key)) {
	        throw new Exception( 'Missing Kaleyra credentials.' );
        }
        $this->sid = $sid;
        $this->api_key = $api_key;
        $this->set_api_url();
        return $this;
    }

    private function set_api_url()
    {
        $this->api_url = "https://api.kaleyra.io/v1/" . $this->sid;
    }

	private function get_api_url(): string
	{
		return $this->api_url;
	}

	private function get_api_key(): string
	{
		return $this->api_key;
	}

	private function get_delivery_callback(): string
	{
		return $this->delivery_callback;
	}

    public function send_sms(string $number, string $sender, string $body)
    {
	    $ch = curl_init();

		$post_fields = "to={$number}&sender={$sender}&body={$body}&source=API&ref=texteller";

		$callback_url = $this->get_delivery_callback();
		if ($callback_url) {
			$callback = "{url\":\"{$this->get_delivery_callback()}\",\"method\":\"POST\"}";
			$post_fields = $post_fields . "&callback={$callback}";
		}

	    curl_setopt($ch, CURLOPT_URL, $this->get_api_url() . '/messages');
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($ch, CURLOPT_POST, 1);
	    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);

	    $headers = ["Api-Key: {$this->get_api_key()}", 'Content-Type: application/x-www-form-urlencoded'];

	    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

	    $result = curl_exec($ch);
	    if (curl_errno($ch)) {
		    TLR\tlr_write_log(
			    "Kaleyra: An error occurred while sending SMS." .
			    curl_error($ch)
		    );
		    curl_close($ch);
			return false;
	    } else {
			return $result;
	    }
    }
}
