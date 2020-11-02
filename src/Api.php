<?php

class Api implements ApiInterface
{
    private static string $HASH_SECRET = '';
    private const HASH_ALGORITHM = 'sha256';
    private static $logger;

    /**
     * Api constructor.
     * @param string $HASH_SECRET
     * @throws Exception
     */
    public function __construct(string $HASH_SECRET = '')
    {
        self::$HASH_SECRET = $HASH_SECRET ?: (class_exists('Config') ? Config::get('API_HASH_SECRET') : '');
        self::$logger      = function_exists('addToLog') ? addToLog : '';

        if (!self::$HASH_SECRET) {
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

    /**
     * @param        $data
     * @param string $fileName
     */
    private function addToLog($data, string $fileName = '!api_err')
    {
        self::$logger($data, $fileName);
    }

    /**
     * @param string $apiUrl
     * @param string $action
     * @param array  $params
     * @return array|mixed
     */
    public function apiCall(string $apiUrl, string $action, array $params = [])
    {
        if (empty($apiUrl) || empty($action) || !is_array($params)) {
            $this->addToLog('Wrong api call params!');
            $this->addToLog('URL: ' . $apiUrl);
            $this->addToLog('Action: ' . $action);
            $this->addToLog('Params ' . json_encode($params));

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
            $this->addToLog("apiURL: $apiUrl");
            $this->addToLog("action: $action");
            $this->addToLog($params);
            $this->addToLog('Invalid api call response: ' . json_encode($res));
            $this->addToLog($res);
            $this->addToLog('curl_errno: ' . $errno);
            $this->addToLog('curl_error: ' . htmlspecialchars(curl_error($ch)));
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
            $this->addToLog('=', 'hash.txt');
            $this->addToLog($_REQUEST, 'hash.txt');
            $this->addToLog('generated:', 'hash.txt');
            $this->addToLog($generatedHash, 'hash.txt');
            $this->addToLog($body, 'hash.txt');
            $this->addToLog('- request:', 'hash.txt');
            $this->addToLog($hash, 'hash.txt');
            $this->addToLog('=', 'hash.txt');
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
