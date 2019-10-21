<?php

define("boomstream_test", 1);

require "boomstream.php";

if (defined("boomstream_test")) {
    class moodle_text_filter
    {

    }

    function get_config($a, $b)
    {
        $arr = ['subscription' => 'mJTImNQg', 'key' => '12345678912345678912345678912345'];
        return $arr[$b];
    }
}

global $USER;
$USER = (object)$USER = ['id' => 1, 'email' => 'obidnov@gmail.com'];

$boomstream = new boomstream();
$testText = '<iframe width="640" height="360" src="https://video.autoshcool-online.com.ua/Rg8vLM74&qwe=123" frameborder="0" scrolling="no" allowfullscreen></iframe> <iframe width="640" height="360" src="https://play.boomstream.com/player.html?code=zgnUqB24&qwe" frameborder="0" scrolling="no" allowfullscreen></iframe> <iframe width="640" height="360" src="https://play.boomstream.com/player.html?code=qGRrSoqC" frameborder="0" scrolling="no" allowfullscreen></iframe>';
print_r($boomstream->filter($testText));
