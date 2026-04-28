<?php

class boomstream {

    private $hostname;
    private $key;
    private $subscription;
    private $debug = 0;

    public function filter($text, array $options = array()) {

        $debugMessage = '';
        if (
            ($this->hostname = get_config('filter_boomstream', 'hostname')) &&
            ($this->key = get_config('filter_boomstream', 'key')) &&
            ($this->subscription = get_config('filter_boomstream', 'subscription'))
        ) {

            $this->debug = intval(get_config('filter_boomstream', 'debug'));

            $debugMessage .= "\nStart boomstream plugin";
            $debugMessage .= "\nhostname: " . $this->hostname;
            $debugMessage .= "\nkey: " . $this->key;
            $debugMessage .= "\nsubscription: " . $this->subscription;
            $debugMessage .= "\n\n";

            $matches = [];
            $pattern = "/src\s*=\s*[\"'](https:\/\/" .
                "(" .
                "play\.boomstream\.com|" .
                "play\.boomstream\.net|" .
                str_replace(".", "\.", addslashes($this->hostname)) .
                ")" .
                "(?:\/|[^'\"\s]+code=)([a-zA-Z0-9]{8}).*?)[\"']/i";
            preg_match_all($pattern, $text, $matches);
            if (isset($matches[0]) && count($matches[0]) > 0) {
                $result = $this->_process($text, $matches[1], $matches[2], $matches[3]);
                $text = $result[0];
                $debugMessage .= $result[1];
            }
        }

        if ($this->debug === 1) {
            return $text . "\n<!--Boomstream filter is applied\n" . $debugMessage . "\n-->\n";
        } else {
            return $text;
        }
    }

    private function _curlBoomstream($link, &$debugMessage)
    {

        $debugMessage .= "\nTry to call api/ppv: " . $link;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $link);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        try {
            $response = curl_exec($ch);
            curl_close($ch);
            $result = json_decode($response, true);
            unset($result['Versions']);
            return (object) $result;
        } catch (Exception $e) {
            $debugMessage .= "\nFailed to call api/ppv: " . $link;
            $debugMessage .= "\nError: " . $e->getMessage();
            return false;
        }
    }

    private function _process($text, $urls, $hosts, $codes) {
        define("ZERO_TIME", '0000-00-00 00:00:00');
        global $USER;

        $debugMessage = '';

        if (defined("boomstream_test")) {
            $USER = (object)$USER = ['id' => 1, 'email' => 'obidnov@gmail.com'];
        }

        if (count($codes) > 0) {
            $i = 0;
            foreach ($codes as $media) {

                $matchedUrl = $urls[$i];
                $identifier = $hosts[$i];

                $debugMessage .= "\nTry working with code: " . $media;
                $debugMessage .= "\nTry working with url: " . $matchedUrl;
                $debugMessage .= "\nTry working with host: " . $identifier;

                $hash = $identifier . '|' . $USER->id . '|' . $media;
                if (isset($_SERVER['HTTP_HOST'])) {
                    $hash = $_SERVER['HTTP_HOST'] . '|' . $USER->id . '|' . $media;
                }

                if (empty($this->hostname)) {
                    $this->hostname = $identifier;
                }

                $debugMessage .= "\nAPI calls to host: " . $this->hostname;

                $params =
                    'format=json&' .
                    'apikey=' .  $this->key .
                    '&code=' . $this->subscription .
                    '&media=' . $media .
                    '&email=' . $USER->email .
                    '&notification=0' .
                    '&hash=' . $hash;

                $result = $this->_curlBoomstream('https://' . $this->hostname . '/api/ppv/addbuyer?' . $params, $debugMessage);

                if (isset($result->Status) && $result->Status == 'Success') {

                    $debugMessage .= "\nResult:\n";
                    $debugMessage .= print_r($result, true);

                    $dtExpiration = new DateTime($result->AccessExpirationDate ?? 'now', new DateTimeZone('Europe/Moscow'));
                    $dtNow = new DateTime('now', new DateTimeZone('Europe/Moscow'));

                    $isAccessExpired = $result->AccessExpirationDate && $result->AccessExpirationDate != ZERO_TIME && $dtExpiration < $dtNow;

                    $debugMessage .= "\nisAccessExpired: " . ($isAccessExpired ? "yes" : "no") . "\n";

                    if ($result->Recovery == 0 || $isAccessExpired) {
                        $resultSubscription = $this->_curlBoomstream('https://' . $this->hostname . '/api/ppv/info?' . $params, $debugMessage);

                        $debugMessage .= "\nSubscription result:\n";
                        $debugMessage .= print_r($resultSubscription, true);

                        if ($result->Recovery == 0 && isset($resultSubscription->Items['Item']['Activation']) && isset($resultSubscription->Status) && $resultSubscription->Status == 'Success') {
                            $result = $this->_curlBoomstream('https://' . $this->hostname . '/api/ppv/updatebuyer?' . $params . '&activation=' . $resultSubscription->Items['Item']['Activation'], $debugMessage);

                            $debugMessage .= "\nUpdateBuyer result:\n";
                            $debugMessage .= print_r($result, true);
                        }
                        if ($isAccessExpired && isset($resultSubscription->Status) && $resultSubscription->Status == 'Success') {

                            if (isset($resultSubscription->Items['Item']['AccessExpirationDate']) && !empty($resultSubscription->Items['Item']['AccessExpirationDate'])) {
                                $accessExpire = $resultSubscription->Items['Item']['AccessExpirationDate'];
                            } else {
                                $accessExpire = $dtNow->add(new DateInterval("P" . $resultSubscription->Items['Item']['Period'] . "D"))->format('Y-m-d H:i:s');
                            }
                            $result = $this->_curlBoomstream('https://' . $this->hostname . '/api/ppv/updatebuyer?' . $params . '&access_expire=' . urlencode($accessExpire), $debugMessage);

                            $debugMessage .= "\nUpdateBuyer result:\n";
                            $debugMessage .= print_r($result, true);
                        }
                    }

                    if (false !== strpos($matchedUrl, "?")) {
                        $resultUrl = $matchedUrl . '&id_recovery=' . $hash;
                    } else {
                        $resultUrl = $matchedUrl . '?id_recovery=' . $hash;
                    }

                    $pattern = "/" . preg_quote($matchedUrl, '/') . "/";
                    $text = preg_replace($pattern, $resultUrl, $text);

                    $debugMessage .= "\nUsed url: " . $resultUrl . "\n";

                } else {
                    $debugMessage .= "\nResult failed: " . $result->Message ?? 'Wrong response from boomstream API' . "\n";
                }
                $i ++;
            }
        }
        return [$text, $debugMessage];
    }
}
