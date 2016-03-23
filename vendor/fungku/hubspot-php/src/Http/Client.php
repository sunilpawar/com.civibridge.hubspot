<?php namespace Fungku\HubSpot\Http;

use Fungku\HubSpot\Contracts\HttpClient;
use GuzzleHttp\Client as GuzzleClient;

class Client implements HttpClient
{
    /**
     * @var GuzzleClient
     */
    protected $client;

    /**
     * Make it, baby.
     */
    public function __construct()
    {
        $this->client = new GuzzleClient();
    }

    /**
     * @param string $url
     * @param array $options
     * @return Response
     */
    public function get($url, array $options = [])
    {
        return $this->client->get($url, $options)->json();
    }

    /**
     * @param string $url
     * @param array $options
     * @return \GuzzleHttp\Message\FutureResponse|\GuzzleHttp\Message\ResponseInterface|\GuzzleHttp\Ring\Future\FutureInterface|null
     */
    public function post($url, array $options = [])
    {
        return $this->client->post($url, $options)->json();
    }

    /**
     * @param string $url
     * @param array $options
     * @return \GuzzleHttp\Message\FutureResponse|\GuzzleHttp\Message\ResponseInterface|\GuzzleHttp\Ring\Future\FutureInterface|null
     */
    public function put($url, array $options = [])
    {
        return $this->client->put($url, $options)->json();
    }

    /**
     * @param string $url
     * @param array $options
     * @return \GuzzleHttp\Message\FutureResponse|\GuzzleHttp\Message\ResponseInterface|\GuzzleHttp\Ring\Future\FutureInterface|null
     */
    public function delete($url, array $options = [])
    {
        return $this->client->delete($url, $options)->json();
    }
}
