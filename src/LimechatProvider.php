<?php

namespace Flits\Limechat;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Flits\Limechat\LimechatException;

class LimechatProvider {
    public $BASE_URL = "https://flow-builder.limechat.ai/builder/v1/event/create";
    public $HEADERS;
    public $EXTRA_CONFIG;
    public $client;

    function __construct($config) {
        $this->HEADERS = $config['headers'] ?? []; // extra headers if you want to pass it in request
        $this->EXTRA_CONFIG = $config['EXTRA_CONFIG'] ?? []; // Extra Guzzle/client config for api call
        $this->setupClient();
    }

    function setupClient() {
        $config = [
            'base_uri' => $this->BASE_URL,
            'timeout' => 2.0,
            'curl' => [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '', 
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            ],
            'headers' => $this->HEADERS,
        ];
        $config = array_merge($config, $this->EXTRA_CONFIG);
        $this->client = new Client($config);
    }

    function POST($payload) {
        try {
            $response = $this->client->request($this->METHOD,  $this->URL, [
                'body' => $payload,
            ]);
        } catch (RequestException $ex) {
            throw new LimechatException($ex->getResponse()->getBody()->getContents(), $ex->getResponse()->getStatusCode());
        }
        if ($response->getStatusCode() != 200) {
            throw new LimechatException($response->getBody()->getContents(), $response->getStatusCode());
        }
        return json_decode($response->getBody()->getContents());
    }
}