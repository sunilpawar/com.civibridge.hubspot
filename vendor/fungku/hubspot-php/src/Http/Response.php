<?php namespace Fungku\HubSpot\Http;

use Fungku\HubSpot\Contracts\ApiResponse;
use GuzzleHttp\Message\ResponseInterface;

class Response implements ApiResponse
{
    /**
     * @var ResponseInterface
     */
    private $response;

    /**
     * @param ResponseInterface $response
     */
    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

    /**
     * @return int
     */
    public function statusCode()
    {
        return $this->response->getStatusCode();
    }

    /**
     * @return array
     */
    public function headers()
    {
        return $this->response->getHeaders();
    }

    /**
     * @param string $header
     * @return string
     */
    public function header($header)
    {
        return $this->response->getHeader($header);
    }

    /**
     * @return string|null
     */
    public function reasonPhrase()
    {
        return $this->response->getReasonPhrase();
    }

    /**
     * @return mixed
     */
    public function json()
    {
        return $this->response->json();
    }

    /**
     * @return \SimpleXMLElement
     */
    public function xml()
    {
        return $this->response->xml();
    }

    /**
     * Get the effective URL that resulted in this response (e.g. the last
     * redirect URL).
     *
     * @return string
     */
    public function effectiveUrl()
    {
        return $this->response->getEffectiveUrl();
    }
}
