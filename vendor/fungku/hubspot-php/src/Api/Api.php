<?php namespace Fungku\HubSpot\Api;

use Fungku\HubSpot\Contracts\HttpClient;
use Fungku\HubSpot\Http\Query;
use GuzzleHttp\Exception\RequestException;

abstract class Api
{
    /**
     * @var string HubSpot API key
     */
    protected $apiKey;

    /**
     * @var HttpClient
     */
    protected $client;

    /**
     * @var bool
     */
    private $oauth;

    /**
     * @var string Base url
     */
    protected $baseUrl = "https://api.hubapi.com";

    /**
     * Default user agent.
     */
    const USER_AGENT = 'Fungku_HubSpot_PHP/0.9 (https://github.com/fungku/hubspot-php)';

    /**
     * @param string $apiKey
     * @param HttpClient $client
     * @param bool $oauth
     */
    public function __construct($apiKey, HttpClient $client, $oauth = false)
    {
        $this->apiKey = $apiKey;
        $this->client = $client;
        $this->oauth = $oauth;
    }

    /**
     * Send the request to the HubSpot API.
     *
     * @param string $method The HTTP request verb.
     * @param string $url The url to send the request to.
     * @param array $options An array of options to send with the request.
     * @return mixed
     */
    protected function requestUrl($method, $url, array $options =[])
    {
        $options['headers']['User-Agent'] = self::USER_AGENT;

        try {
            return $this->client->$method($url, $options);
        } catch (RequestException $e) {
            return $e->getResponse();
        }
    }

    /**
     * Send the request to the HubSpot API.
     *
     * @param string $method The HTTP request verb.
     * @param string $endpoint The HubSpot API endpoint.
     * @param array $options An array of options to send with the request.
     * @param string $queryString A query string to send with the request.
     * @return mixed
     */
    protected function request($method, $endpoint, array $options = [], $queryString = null)
    {
        $url = $this->generateUrl($endpoint, $queryString);

        return $this->requestUrl($method, $url, $options);
    }

    /**
     * Generate the full endpoint url, including query string.
     *
     * @param string $endpoint The HubSpot API endpoint.
     * @param string $queryString The query string to send to the endpoint.
     * @return string
     */
    protected function generateUrl($endpoint, $queryString = null)
    {
        $authType = $this->oauth ? 'access_token' : 'hapikey';

        return $this->baseUrl . $endpoint . '?'. $authType . '=' . $this->apiKey . $queryString;
    }

    /**
     * Generate a query string for batch requests.
     * 
     * This is a workaround to deal with multiple items with the same key/variable name, not something PHP generally likes.
     *
     * @param string $varName The name of the query variable.
     * @param array $items An array of item values for the variable.
     * @return string
     */
    protected function generateBatchQuery($varName, array $items)
    {
        $queryString = '';
        foreach ($items as $item) {
            $queryString .= "&{$varName}={$item}";
        }

        return $queryString;
    }

    /**
     * @param array|Query $params
     * @return mixed
     * @throws \InvalidArgumentException
     */
    protected function getQuery($params)
    {
        if (is_array($params) || $params instanceof Query) {
            return $params;
        }

        throw new \InvalidArgumentException('Argument must be an array or an instance of \Fungku\HubSpot\Http\Query');
    }
}
