<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Version information
 *
 * @package    filter
 * @subpackage boomstream
 * @copyright  HWD LTD <support@boomstream.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
if (isset($_GET['run']) && $_GET['run'] == 'test') {
    class moodle_text_filter {

    }
    function get_config($a, $b) {
        $arr = ['subscription'=> 'mJTImNQg', 'key' => '12345678912345678912345678912345'];
        return $arr[$b];
    }
}
class filter_boomstream extends moodle_text_filter {

    public function filter($text, array $options = array()) {
        global $USER;

        if (isset($_GET['run']) && $_GET['run'] == 'test') {
            $USER = (object)$USER = ['id' => 1, 'email' => 'obidnov@gmail.com'];
        }

        // Do a quick check using stripos to avoid unnecessary work
        if (strpos($text, 'boomstream') === false) {
            return $text;
        }
        $matches = [];
        preg_match_all("/https?:\/\/play\.boomstream\.com[^'\"\s]+code=([^&'\"\s]+)/", $text, $matches);
        if (!empty($matches)) {
            foreach ($matches[1] as $media) {
                $recoveryString = '';
                if ($key = get_config('filter_boomstream', 'key')) {
                    if ($subscription = get_config('filter_boomstream', 'subscription')) {
                	$hash = $_SERVER['HTTP_HOST'] . '|' . $USER->id;
                	$result = file_get_contents('https://boomstream.com/api/ppv/addbuyer?format=json&apikey=' . $key . '&code=' . $subscription . '&media=' . $media . '&email=' . $USER->email . '&notification=0&hash=' . $hash);
                	$result = json_decode($result);
                	if (isset($result->Status) & $result->Status == 'Success') {
                            $recoveryString = '&id_recovery=' . $hash;
                        }
                    }
                }
                $text = preg_replace("/code=" . $media . "/", "code=" . $media . $recoveryString, $text);
            }
        }
        return $text;
    }

}
if (isset($_GET['run']) && $_GET['run'] == 'test') {
    global $USER;
    $myTest = new filter_boomstream();
    $testText = '<iframe width="640" height="360" src="https://play.boomstream.com/player.html?code=zgnUqB24&qwe" frameborder="0" scrolling="no" allowfullscreen></iframe> <iframe width="640" height="360" src="https://play.boomstream.com/player.html?code=qGRrSoqC" frameborder="0" scrolling="no" allowfullscreen></iframe>';
    print_r($myTest->filter($testText));
}