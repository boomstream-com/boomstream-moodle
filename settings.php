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
 * Boomstream filter settings.
 *
 * @package    filter_boomstream
 * @copyright  2026 HWD LTD <support@boomstream.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'filter_boomstream/intro',
        '',
        new lang_string('settingsintro', 'filter_boomstream')
    ));

    $settings->add(new admin_setting_configtext(
        'filter_boomstream/hostname',
        new lang_string('hostname', 'filter_boomstream'),
        new lang_string('hostname_desc', 'filter_boomstream'),
        'play.boomstream.com',
        PARAM_HOST
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'filter_boomstream/key',
        new lang_string('key', 'filter_boomstream'),
        new lang_string('key_desc', 'filter_boomstream'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'filter_boomstream/subscription',
        new lang_string('subscription', 'filter_boomstream'),
        new lang_string('subscription_desc', 'filter_boomstream'),
        '',
        PARAM_ALPHANUM
    ));

    $settings->add(new admin_setting_configcheckbox(
        'filter_boomstream/debug',
        new lang_string('debug', 'filter_boomstream'),
        new lang_string('debug_desc', 'filter_boomstream'),
        0
    ));
}
