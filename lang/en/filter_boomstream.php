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
 * English strings for filter_boomstream.
 *
 * @package    filter_boomstream
 * @copyright  2026 HWD LTD <support@boomstream.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['debug'] = 'Debug mode';
$string['debug_desc'] = 'Append a trace of the filter work (matched players, API calls and responses) as an HTML comment to filtered pages. The trace is shown to site administrators only. Keep disabled in production.';
$string['filtername'] = 'Boomstream video platform';
$string['hostname'] = 'Hostname';
$string['hostname_desc'] = 'Boomstream player hostname, for example play.boomstream.com or your custom domain. It is used for API calls and replaces the host of embedded players. Leave empty to keep the host written in each embed.';
$string['key'] = 'API key';
$string['key_desc'] = 'Project API key. You can find it in your Boomstream account: Project Settings → Integration.';
$string['pluginname'] = 'Boomstream video platform';
$string['privacy:metadata:boomstream'] = 'To give the user personal access to embedded Boomstream videos, the plugin registers the user as a buyer in the Boomstream Pay Per View service.';
$string['privacy:metadata:boomstream:email'] = 'The email address of the user, used as the Boomstream buyer email.';
$string['privacy:metadata:boomstream:sitehost'] = 'The host name of this Moodle site, part of the user access identifier.';
$string['privacy:metadata:boomstream:userid'] = 'The Moodle ID of the user, part of the user access identifier.';
$string['settingsintro'] = 'The Boomstream filter binds embedded Boomstream players to the logged-in user and checks access against your Boomstream Pay Per View subscription. A Boomstream account is required, see <a href="https://boomstream.com" target="_blank" rel="noopener">boomstream.com</a>. After saving the settings, enable the filter at Site administration → Plugins → Filters → Manage filters.';
$string['subscription'] = 'Subscription code';
$string['subscription_desc'] = 'Code of the Boomstream subscription users are attached to. You can find it in your Boomstream account: Subscriptions → subscription name.';
