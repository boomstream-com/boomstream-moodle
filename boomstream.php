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

    private function _process($text, $urls, $hosts, $codes) {
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

                if (isset($_SERVER['HTTP_HOST'])) {
                    $identifier = $_SERVER['HTTP_HOST'];
                }
                $hash = $identifier . '|' . $USER->id . '|' . $media;
                $link = 'https://boomstream.com/api/ppv/addbuyer?' .
                    'format=json&' .
                    'apikey=' .  $this->key .
                    '&code=' . $this->subscription .
                    '&media=' . $media .
                    '&email=' . $USER->email .
                    '&notification=0' .
                    '&hash=' . $hash;

                $debugMessage .= "\nTry call api/ppv/addbuyer: " . $link;

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $link);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                try {
                    $response = curl_exec($ch);
                    curl_close($ch);
                    $result = json_decode($response, true);
                    unset($result['Versions']);
                    $result = (object) $result;
                } catch (Exception $e) {
                    $debugMessage .= "\nFailed call api/ppv/addbuyer: " . $link;
                    $debugMessage .= "\nError: " . $e->getMessage();
                    continue;
                }

                $debugMessage .= "\nResult:\n";
                $debugMessage .= print_r($result, true);

                if (isset($result->Status) & $result->Status == 'Success') {
                    if (false !== strpos($matchedUrl, "?")) {
                        $resultUrl = $matchedUrl . '&id_recovery=' . $hash;
                    } else {
                        $resultUrl = $matchedUrl . '?id_recovery=' . $hash;
                    }

                    $pattern = "/" . preg_quote($matchedUrl, '/') . "/";
                    $text = preg_replace($pattern, $resultUrl, $text);
                }
                $i ++;
            }
        }
        return [$text, $debugMessage];
    }
}
