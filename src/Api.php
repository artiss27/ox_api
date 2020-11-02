<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class Api implements ApiInterface
{
    private static $HASH_SECRET = '';
    private static $logDir = '';
    private const HASH_ALGORITHM = 'sha256';

    /**
     * Api constructor.
     * @param string $HASH_SECRET
     * @throws Exception
     */
    public function __construct(string $HASH_SECRET = '')
    {
        self::$HASH_SECRET = $HASH_SECRET ?: (class_exists('Config') ? Config::get('API_HASH_SECRET') : '');
        self::$logDir      = self::$logDir ?: dirname(__DIR__, 4) . '/';

        if (!self::$HASH_SECRET) {
            $this->log('Wrong api call params!', 'error');
            $this->setError('Empty secret hash!');
        }
    }

    /**
     * @param string $error
     * @throws Exception
     */
    private function setError(string $error)
    {
        throw new \Exception($error);
    }

    public function log($data, $method = 'debug', $fileName = 'api_err.log')
    {
        if (in_array($method, ['warning', 'error', 'info', 'debug'])) {
            $log = new Logger('name');
            $log->pushHandler(new StreamHandler(self::$logDir . $fileName, Logger::WARNING));
            $log->$method($data);
        }
    }

    /**
     * @param string $apiUrl
     * @param string $action
     * @param array  $params
     * @return array|mixed
     * @throws Exception
     */
    public function apiCall(string $apiUrl, string $action, array $params = [])
    {
        if (empty($apiUrl) || empty($action) || !is_array($params)) {
            $this->log('Wrong api call params!', 'error');
            $this->log('URL: ' . $apiUrl, 'error');
            $this->log('Action: ' . $action, 'error');
            $this->log('Params ' . json_encode($params), 'error');

            $this->setError('Wrong api call params!');
        }

        $params['action'] = $action;
        $params           = json_encode($params);
        $url              = $apiUrl . '?hash=' . $this->generateHash($params);

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => $params,
        ]);
        $res    = curl_exec($ch);
        $result = json_decode($res, true);

        $errno = curl_errno($ch);

        if (!$result || !empty($result['error']) || !empty($errno)) {
            $this->log("apiURL: $apiUrl", 'error');
            $this->log("action: $action", 'error');
            $this->log($params, 'error');
            $this->log('Invalid api call response: ' . json_encode($res), 'error');
            $this->log($res, 'error');
            $this->log('curl_errno: ' . $errno, 'error');
            $this->log('curl_error: ' . htmlspecialchars(curl_error($ch)), 'error');
        }
        curl_close($ch);

        return $result;
    }

    /**
     * @param string $hash
     * @param string $body
     * @return bool
     */
    public function isHashValid(string $hash, string $body): bool
    {
        $generatedHash = $this->generateHash($body);
        $is_same       = $generatedHash === $hash;

        if (!$is_same) {
            $this->log('=', 'warning', 'hash.log');
            $this->log($_REQUEST, 'warning', 'hash.log');
            $this->log('generated:', 'warning', 'hash.log');
            $this->log($generatedHash, 'warning', 'hash.log');
            $this->log($body, 'warning', 'hash.log');
            $this->log('- request:', 'warning', 'hash.log');
            $this->log($hash, 'warning', 'hash.log');
            $this->log('=', 'warning', 'hash.log');
        }
        return $is_same;
    }

    /**
     * @param $body
     * @return string
     */
    private function generateHash($body): string
    {
        return hash_hmac(self::HASH_ALGORITHM, str_replace(mb_substr($body, -6, 1), ':)', $body), self::$HASH_SECRET);
    }
}
