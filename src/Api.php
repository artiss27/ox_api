<?php

class Api
{
    private static string $HASH_SECRET;
    private const HASH_ALGORITHM = 'sha256';

    public function __construct()
    {
        self::$HASH_SECRET = Config::get('API_HASH_SECRET');
    }

    public function apiCall(string $apiUrl, string $action, array $params = [])
    {
        if (empty($apiUrl) || empty($action) || !is_array($params))
        {
            addToLog('Wrong api call params!');
            addToLog('URL: ' . $apiUrl);
            addToLog('Action: ' . $action);
            addToLog('Params ' . json_encode($params));

            return [];
        }

        $params['action'] = $action;
        $params           = json_encode($params);
        $url              = $apiUrl . '?hash=' . $this->generateHash($params);

        $ch = curl_init($url);

        // curl_setopt($ch, CURLOPT_VERBOSE, true);
        // $verbose = fopen('php://temp', 'wb+');
        // curl_setopt($ch, CURLOPT_STDERR, $verbose);

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => $params
        ]);
        $res = curl_exec($ch);
        // addToLog($res);
        $result = json_decode($res, true);

        $errno = curl_errno($ch);

        if (!$result || !empty($result['error']) || !empty($errno))
        {
            addToLog("apiURL: $apiUrl", '!api_err');
            addToLog("action: $action", '!api_err');
            addToLog($params, '!api_err');
            addToLog('Invalid api call response: ' . json_encode($res), '!api_err');

            addToLog($res, '!api_err');
            // rewind($verbose);
            // $verboseLog = stream_get_contents($verbose);
            // addToLog("Verbose information:\n<pre>" . htmlspecialchars($verboseLog) . "</pre>\n", '!api_err');
            addToLog('curl_errno: ' . $errno, '!api_err');
            addToLog('curl_error: ' . htmlspecialchars(curl_error($ch)), '!api_err');
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
        $is_same = $generatedHash === $hash;

        if (!$is_same)
        {
            addToLog('=',   'hash.txt');
            addToLog($_REQUEST, 'hash.txt');
            addToLog('generated:', 'hash.txt');
            addToLog($generatedHash,   'hash.txt');
            addToLog($body, 'hash.txt');
            addToLog('- request:', 'hash.txt');
            addToLog($hash,        'hash.txt');
            addToLog('=',      'hash.txt');
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
