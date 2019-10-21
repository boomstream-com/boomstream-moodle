<?php

class boomstream {

    public function filter($text, array $options = array()) {

        //https://video.autoshcool-online.com.ua/Rg8vLM74
        //https://play.boomstream.com/Rg8vLM74

        // Do a quick check using stripos to avoid unnecessary work
        if (strpos($text, 'play.boomstream.com') === false && strpos($text, 'video.autoshcool-online.com.ua') === false) {
            return $text;
        }



        $matches = [];

        preg_match_all("/(https?:\/\/play\.boomstream\.com)\/([a-zA-Z0-9]{8})/", $text, $matches);
        if (isset($matches[0]) && count($matches[0]) > 0) {
            $text = $this->_process($text, $matches);
        }

        preg_match_all("/(https?:\/\/play\.boomstream\.com)[^'\"\s]+code=([a-zA-Z0-9]+)/", $text, $matches);
        if (isset($matches[0]) && count($matches[0]) > 0) {
            $text = $this->_process($text, $matches);
        }

        preg_match_all("/(https?:\/\/video\.autoshcool-online\.com\.ua)\/([a-zA-Z0-9]{8})/", $text, $matches);
        if (isset($matches[0]) && count($matches[0]) > 0) {
            $text = $this->_process($text, $matches);
        }

        preg_match_all("/(https?:\/\/video\.autoshcool-online\.com\.ua)[^'\"\s]+code=([a-zA-Z0-9]+)/", $text, $matches);
        if (isset($matches[0]) && count($matches[0]) > 0) {
            $text = $this->_process($text, $matches);
        }
        return $text;
    }

    private function _process($text, $matches) {
        global $USER;

        if (defined("boomstream_test")) {
            var_dump($matches);
            $USER = (object)$USER = ['id' => 1, 'email' => 'obidnov@gmail.com'];
        }

        if (isset($matches[2]) && count($matches[2]) > 0) {
            $i = 0;
            foreach ($matches[2] as $media) {

                if (defined("boomstream_test")) {
                    var_dump($media);
                }

                $host = $matches[1][$i];
                $matchedUrl = $matches[0][$i];

                $recoveryString = '';
                if ($key = get_config('filter_boomstream', 'key')) {
                    if ($subscription = get_config('filter_boomstream', 'subscription')) {
                        $identifier = 'autoshcool-online.com.ua';
                        if (isset($_SERVER['HTTP_HOST'])) {
                            $identifier = $_SERVER['HTTP_HOST'];
                        }
                        $hash = $identifier . '|' . $USER->id;
                        $result = file_get_contents('https://boomstream.com/api/ppv/addbuyer?format=json&apikey=' . $key . '&code=' . $subscription . '&media=' . $media . '&email=' . $USER->email . '&notification=0&hash=' . $hash);
                        $result = json_decode($result);
                        if (defined("boomstream_test")) {
                            var_dump($result);
                        }
                        if (isset($result->Status) & $result->Status == 'Success') {
                            $recoveryString = '?id_recovery=' . $hash;
                        }
                    }
                }

                $pattern = "/" . preg_quote($matchedUrl, '/') . "/";
                $params = '';
                $parts = parse_url($matchedUrl);
                if (isset($parts['query'])) {
                    parse_str($parts['query'], $query);
                    if (isset($query['code'])) {
                        unset($query['code']);
                    }
                    if (count($query) > 0) {
                        $params = "&" . implode("&", $query);
                    }
                }

                $text = preg_replace($pattern, $host . "/" . $media . $recoveryString . $params, $text);

                $i ++;
            }
        }
        return $text;
    }
}
