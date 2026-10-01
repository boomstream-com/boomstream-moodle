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

namespace filter_boomstream;

/**
 * Filter logic: expands shortcodes and binds Boomstream players to the current user.
 *
 * @package    filter_boomstream
 * @copyright  2026 HWD LTD <support@boomstream.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class boomstream {
    /** @var string Value of an empty date returned by the Boomstream API. */
    private const ZERO_TIME = '0000-00-00 00:00:00';

    /** @var string[] Public Boomstream player hosts recognised by the filter. */
    private const PUBLIC_HOSTS = ['play.boomstream.com', 'play.boomstream.net', 'play.boomstream.dev', 'play.boomstream.org'];

    /** @var string Default player host used when the hostname setting is empty. */
    private const DEFAULT_HOST = 'play.boomstream.com';

    /** @var string Configured target hostname (may be empty). */
    private $hostname;

    /** @var string Boomstream API key. */
    private $key;

    /** @var string Global subscription code. */
    private $subscription;

    /** @var bool Whether the debug trace is appended to the output. */
    private $debug = false;

    /**
     * Constructor, loads the plugin configuration.
     */
    public function __construct() {
        $config = get_config('filter_boomstream');
        $this->hostname = trim($config->hostname ?? '');
        $this->key = trim($config->key ?? '');
        $this->subscription = trim($config->subscription ?? '');
        $this->debug = !empty($config->debug);
    }

    /**
     * Filters the text.
     *
     * @param string $text Text to filter.
     * @param array $options Filter options.
     * @return string Filtered text.
     */
    public function filter($text, array $options = []) {
        if (!is_string($text) || $text === '' || $this->key === '' || $this->subscription === '') {
            return $text;
        }
        if (
            stripos($text, 'boomstream') === false &&
                ($this->hostname === '' || stripos($text, $this->hostname) === false)
        ) {
            return $text;
        }

        $debugmessage = "\nStart boomstream plugin";
        $debugmessage .= "\nhostname: " . ($this->hostname ?: '(not set, will fall back to host from src)');
        $debugmessage .= "\nkey: " . $this->mask($this->key);
        $debugmessage .= "\nsubscription: " . $this->subscription;
        $debugmessage .= "\n\n";

        $subscriptionoverrides = [];
        $text = $this->expand_shortcodes($text, $debugmessage, $subscriptionoverrides);

        $hosts = self::PUBLIC_HOSTS;
        if ($this->hostname !== '') {
            $hosts[] = $this->hostname;
        }
        $hostspattern = implode('|', array_map(function ($host) {
            return preg_quote($host, '/');
        }, $hosts));

        $pattern = "/src\s*=\s*[\"'](https:\/\/(" . $hostspattern . ")(?:\/|[^'\"\s]+code=)([a-zA-Z0-9]{8}).*?)[\"']/i";
        $matches = [];
        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            [$text, $processdebug] = $this->process($text, $matches, $subscriptionoverrides);
            $debugmessage .= $processdebug;
        }

        if ($this->debug && $this->can_see_debug()) {
            // Make sure the trace cannot terminate the HTML comment early.
            $debugmessage = str_replace('--', '- -', $debugmessage);
            return $text . "\n<!--Boomstream filter is applied\n" . $debugmessage . "\n-->\n";
        }
        return $text;
    }

    /**
     * Expands [boomstream media[...] ...] shortcodes into standard Boomstream embeds.
     *
     * @param string $text Text to process.
     * @param string $debugmessage Debug trace, appended to.
     * @param array $subscriptionoverrides Map media code => subscription code, filled in.
     * @return string Text with shortcodes expanded.
     */
    public function expand_shortcodes($text, &$debugmessage, &$subscriptionoverrides) {
        $outer = '/\[boomstream\s+((?:[a-z]+\[[^\[\]]*\]\s*)+)\]/i';
        $host = $this->hostname !== '' ? $this->hostname : self::DEFAULT_HOST;

        return preg_replace_callback($outer, function ($m) use (&$debugmessage, &$subscriptionoverrides, $host) {
            preg_match_all('/([a-z]+)\[([^\[\]]*)\]/i', $m[1], $kv, PREG_SET_ORDER);
            $params = [];
            foreach ($kv as $pair) {
                $params[strtolower($pair[1])] = trim($pair[2]);
            }

            if (empty($params['media']) || !preg_match('/^[a-zA-Z0-9]{8}$/', $params['media'])) {
                $debugmessage .= "\nShortcode skipped (invalid/missing media): " . $m[0];
                return $m[0];
            }

            $media = $params['media'];
            $size = $params['size'] ?? '640x360';
            $mode = strtolower($params['mode'] ?? 'iframe');
            $parts = array_pad(explode('x', strtolower($size), 2), 2, '');
            $w = ctype_digit($parts[0]) ? (int)$parts[0] : 640;
            $h = ctype_digit($parts[1]) ? (int)$parts[1] : 360;

            if (!empty($params['subscription']) && preg_match('/^[a-zA-Z0-9]+$/', $params['subscription'])) {
                $subscriptionoverrides[$media] = $params['subscription'];
            }

            $debugmessage .= sprintf(
                "\nExpanded shortcode: media=%s mode=%s size=%dx%d subscription=%s",
                $media,
                $mode,
                $w,
                $h,
                $subscriptionoverrides[$media] ?? '(global)'
            );

            $safehost = s($host);
            if ($mode === 'adaptive') {
                // The SDK stretches the player to the container width and keeps the video aspect ratio,
                // so size[] only limits the width; without it the player takes the full width.
                $style = isset($params['size']) ? sprintf('max-width:%dpx;', $w) : '';
                return sprintf(
                    '<div style="width:100%%;%s">' .
                    '<script src="https://%s/%s/config.jsonp" async></script>' .
                    '<script src="https://%s/assets/javascripts/biframesdk.js" async></script>' .
                    '<span data-boomstream-code="%s" data-boomstream-mode="adaptive" ' .
                    'data-boomstream-use-fullscreen-mode="0"></span>' .
                    '</div>',
                    $style,
                    $safehost,
                    $media,
                    $safehost,
                    $media
                );
            }

            return sprintf(
                '<iframe width="%d" height="%d" src="https://%s/%s" frameborder="0" allowfullscreen></iframe>',
                $w,
                $h,
                $safehost,
                $media
            );
        }, $text);
    }

    /**
     * Registers the current user as a Boomstream buyer for every matched player and personalises the player URLs.
     *
     * Each matched src attribute is rewritten at its own position, so several embeds of the same video
     * (or an adaptive embed whose config URL starts with the iframe URL) do not affect each other.
     *
     * @param string $text Text to process.
     * @param array $matches Matches of the player pattern, PREG_SET_ORDER | PREG_OFFSET_CAPTURE.
     * @param array $subscriptionoverrides Map media code => subscription code.
     * @return array [processed text, debug trace]
     */
    protected function process($text, array $matches, array $subscriptionoverrides = []) {
        global $USER;

        $debugmessage = '';
        $sitehost = $this->get_site_host();
        $granted = [];
        $sdkrewrites = [];

        // Replace from the end so the offsets of earlier matches stay valid.
        foreach (array_reverse($matches) as $match) {
            [$matchedurl, $offset] = $match[1];
            $identifier = $match[2][0];
            $media = $match[3][0];
            $apihost = $this->hostname !== '' ? $this->hostname : $identifier;

            $debugmessage .= "\nTry working with code: " . $media;
            $debugmessage .= "\nTry working with url: " . $matchedurl;
            $debugmessage .= "\nTry working with host: " . $identifier;
            $debugmessage .= "\nAPI calls to host: " . $apihost;

            $hash = ($sitehost !== '' ? $sitehost : $identifier) . '|' . $USER->id . '|' . $media;

            $activesubscription = $subscriptionoverrides[$media] ?? $this->subscription;
            $debugmessage .= "\nSubscription for this match: " . $activesubscription
                . (isset($subscriptionoverrides[$media]) ? ' (shortcode override)' : ' (global)');

            if (strcasecmp($identifier, $apihost) !== 0) {
                $sdkrewrites[strtolower($identifier)] = $apihost;
            }

            $cachekey = $apihost . '|' . $activesubscription . '|' . $hash;
            if (!isset($granted[$cachekey])) {
                $granted[$cachekey] = $this->grant_access($apihost, $activesubscription, $media, $hash, $debugmessage);
            } else {
                $debugmessage .= "\nAccess already processed on this page";
            }
            if (!$granted[$cachekey]) {
                continue;
            }

            $rewrittenurl = str_replace('://' . $identifier, '://' . $apihost, $matchedurl);
            $separator = strpos($rewrittenurl, '?') !== false ? '&' : '?';
            $resulturl = $rewrittenurl . $separator . 'id_recovery=' . $hash;

            $text = substr_replace($text, $resulturl, $offset, strlen($matchedurl));

            $debugmessage .= "\nUsed url: " . $resulturl . "\n";
        }

        foreach ($sdkrewrites as $identifier => $apihost) {
            $sdkpattern = "/(https:\/\/)" . preg_quote($identifier, '/') . "(\/assets\/)/i";
            $text = preg_replace($sdkpattern, '${1}' . $apihost . '${2}', $text, -1, $sdkcount);
            if ($sdkcount > 0) {
                $debugmessage .= "\nRewrote SDK asset URLs (" . $sdkcount . ") on host " . $identifier . " to: " . $apihost;
            }
        }

        return [$text, $debugmessage];
    }

    /**
     * Registers the current user as a buyer of the media and activates or extends the access when needed.
     *
     * @param string $apihost Boomstream API host.
     * @param string $subscription Subscription code.
     * @param string $media Media code.
     * @param string $hash Access identifier of the user (id_recovery).
     * @param string $debugmessage Debug trace, appended to.
     * @return bool Whether the buyer was registered and the player URL can be personalised.
     */
    protected function grant_access($apihost, $subscription, $media, $hash, &$debugmessage) {
        global $USER;

        $params = [
            'format' => 'json',
            'apikey' => $this->key,
            'code' => $subscription,
            'media' => $media,
            'email' => $USER->email ?? '',
            'notification' => 0,
            'hash' => $hash,
        ];
        $baseurl = 'https://' . $apihost . '/api/ppv/';

        $result = $this->call_api($baseurl . 'addbuyer', $params, $debugmessage);

        if (!$result || ($result->Status ?? '') !== 'Success') {
            $debugmessage .= "\nResult failed: " . ($result->Message ?? 'Wrong response from boomstream API') . "\n";
            return false;
        }

        $debugmessage .= "\nResult:\n" . $this->dump($result);

        $expiration = $result->AccessExpirationDate ?? null;
        $timezone = new \DateTimeZone('Europe/Moscow');
        $now = new \DateTime('now', $timezone);
        $isaccessexpired = false;
        if ($expiration && $expiration != self::ZERO_TIME) {
            try {
                $isaccessexpired = new \DateTime($expiration, $timezone) < $now;
            } catch (\Exception $e) {
                $debugmessage .= "\nInvalid AccessExpirationDate: " . $expiration;
            }
        }
        $debugmessage .= "\nisAccessExpired: " . ($isaccessexpired ? "yes" : "no") . "\n";

        $recovery = $result->Recovery ?? 0;
        if ($recovery == 0 || $isaccessexpired) {
            $info = $this->call_api($baseurl . 'info', $params, $debugmessage);
            $debugmessage .= "\nSubscription result:\n" . $this->dump($info);

            $infook = $info && ($info->Status ?? '') === 'Success';
            $item = $infook ? ($info->Items['Item'] ?? []) : [];

            if ($recovery == 0 && isset($item['Activation'])) {
                $result = $this->call_api(
                    $baseurl . 'updatebuyer',
                    $params + ['activation' => $item['Activation']],
                    $debugmessage
                );
                $debugmessage .= "\nUpdateBuyer result:\n" . $this->dump($result);
            }

            if ($isaccessexpired && $infook) {
                $accessexpire = null;
                if (!empty($item['AccessExpirationDate'])) {
                    $accessexpire = $item['AccessExpirationDate'];
                } else if (isset($item['Period']) && is_numeric($item['Period'])) {
                    $accessexpire = (clone $now)->add(new \DateInterval('P' . (int)$item['Period'] . 'D'))
                        ->format('Y-m-d H:i:s');
                }
                if ($accessexpire !== null) {
                    $result = $this->call_api(
                        $baseurl . 'updatebuyer',
                        $params + ['access_expire' => $accessexpire],
                        $debugmessage
                    );
                    $debugmessage .= "\nUpdateBuyer result:\n" . $this->dump($result);
                }
            }
        }

        return true;
    }

    /**
     * Calls a Boomstream PPV API endpoint, retrying once on failure.
     *
     * @param string $url Endpoint URL.
     * @param array $params Query parameters.
     * @param string $debugmessage Debug trace, appended to.
     * @return \stdClass|false Decoded response or false on failure.
     */
    protected function call_api($url, array $params, &$debugmessage) {
        $result = $this->call_api_once($url, $params, $debugmessage);
        if ($result === false) {
            $debugmessage .= "\nRetrying api/ppv call: " . $url;
            $result = $this->call_api_once($url, $params, $debugmessage);
        }
        return $result;
    }

    /**
     * Performs a single Boomstream PPV API call.
     *
     * @param string $url Endpoint URL.
     * @param array $params Query parameters.
     * @param string $debugmessage Debug trace, appended to.
     * @return \stdClass|false Decoded response or false on failure.
     */
    protected function call_api_once($url, array $params, &$debugmessage) {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $debugmessage .= "\nTry to call api/ppv: " . $url;

        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_MAXREDIRS' => 5,
            'CURLOPT_CONNECTTIMEOUT' => 5,
            'CURLOPT_TIMEOUT' => 10,
        ]);
        $response = $curl->get($url, $params);

        if ($curl->get_errno()) {
            $debugmessage .= "\nFailed to call api/ppv: " . $url . "\nError: " . $curl->error;
            return false;
        }

        $result = json_decode($response, true);
        if (!is_array($result)) {
            $debugmessage .= "\nFailed to call api/ppv: " . $url . "\nError: invalid response";
            return false;
        }
        unset($result['Versions']);
        return (object)$result;
    }

    /**
     * Returns the host (with port, if any) of this Moodle site.
     *
     * @return string
     */
    protected function get_site_host() {
        global $CFG;
        $parts = parse_url($CFG->wwwroot);
        if (empty($parts['host'])) {
            return '';
        }
        return $parts['host'] . (!empty($parts['port']) ? ':' . $parts['port'] : '');
    }

    /**
     * Whether the current user may see the debug trace (it contains API responses).
     *
     * @return bool
     */
    protected function can_see_debug() {
        return has_capability('moodle/site:config', \context_system::instance());
    }

    /**
     * Masks a secret for debug output.
     *
     * @param string $secret
     * @return string
     */
    protected function mask($secret) {
        return strlen($secret) > 4 ? str_repeat('*', strlen($secret) - 4) . substr($secret, -4) : '****';
    }

    /**
     * Formats an API response for the debug trace.
     *
     * @param mixed $value
     * @return string
     */
    protected function dump($value) {
        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        // The API echoes the request URI, which contains the API key.
        return str_replace($this->key, $this->mask($this->key), $json) . "\n";
    }
}
