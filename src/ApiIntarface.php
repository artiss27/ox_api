<?php

interface ApiInterface
{
    public function __construct(string $HASH_SECRET = '');

    /**
     * @param string $apiUrl
     * @param string $action
     * @param array  $params
     * @return array|mixed
     */
    public function apiCall(string $apiUrl, string $action, array $params = []);

    /**
     * @param string $hash
     * @param string $body
     * @return bool
     */
    public function isHashValid(string $hash, string $body);
}