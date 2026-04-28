<?php

define("boomstream_test", 1);

require "boomstream.php";

if (defined("boomstream_test")) {
    class moodle_text_filter
    {

    }

    function get_config($a, $b)
    {
        $arr = [
            'subscription' => 'N5wLwvlW',
            'key' => '12345678912345678912345678912345',
            'hostname' => 'play.boomstream.net',
            'debug' => 1
        ];
        return $arr[$b];
    }
}

global $USER;
$USER = (object)$USER = ['id' => 1, 'email' => 'obidnov@gmail.com'];

$boomstream = new boomstream();
//$testText = '<iframe width="640" height="360" src="https://play.boomstream.com/VVwbS8LD?color=undefined&size=undefined&title=0" frameborder="0" scrolling="no" allowfullscreen></iframe>' .
//    '<iframe width="640" height="360" src="https://play.boomstream.com/player.html?code=VVwbS8LD&qwe" frameborder="0" scrolling="no" allowfullscreen></iframe>'.
//    '<iframe width="640" height="360" src="https://play.boomstream.net/FE7yDJpF/config.jsonp" frameborder="0" scrolling="no" allowfullscreen></iframe>';


$testText = '<script src="https://play.boomstream.com/Am0TlUow/config.jsonp" async></script>
<script src="https://play.boomstream.com/assets/javascripts/biframesdk.js?v=1.0.5" async></script>
<span data-boomstream-code="Am0TlUow"
      data-boomstream-mode="adaptive"
      data-boomstream-use-fullscreen-mode="0"
></span>';


$result = $boomstream->filter($testText);
echo '<!doctype html><html lang="en-RU"><head><meta charset="UTF-8"></head><body>';
echo "\n\nTest is passed:\n";
echo $result;
echo "\n";
echo '</body></html>';