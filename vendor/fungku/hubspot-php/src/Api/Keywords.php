<?php namespace Fungku\HubSpot\Api;

class Keywords extends Api
{
    /**
     * Get all keywords.
     *
     * @return mixed
     */
    public function all()
    {
        $endpoint = '/keywords/v1/keywords.json';

        return $this->request('get', $endpoint);
    }

    /**
     * Get a keyword.
     *
     * @param string $keyword_guid
     * @return mixed
     */
    public function getById($keyword_guid)
    {
        $endpoint = "/keywords/v1/keywords/{$keyword_guid}.json";

        return $this->request('get', $endpoint);
    }

    /**
     * Create a new keyword.
     *
     * @param array $keyword
     * @return mixed
     */
    public function create(array $keyword)
    {
        $endpoint = "/keywords/v1/keywords.json";

        $options['json'] = $keyword;

        return $this->request('put', $endpoint, $options);
    }

    /**
     * Delete a keyword.
     *
     * @param string $keyword_guid
     * @return mixed
     */
    public function delete($keyword_guid)
    {
        $endpoint = "/keywords/v1/keywords/{$keyword_guid}";

        return $this->request('delete', $endpoint);
    }

}
