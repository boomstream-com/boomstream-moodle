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
 * Base class of the filter: \core_filters\text_filter on Moodle 4.5+, \moodle_text_filter on older versions.
 *
 * Loaded by the autoloader when \filter_boomstream\text_filter is declared.
 *
 * @package    filter_boomstream
 * @copyright  2026 HWD LTD <support@boomstream.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (class_exists(\core_filters\text_filter::class)) {
    class_alias(\core_filters\text_filter::class, \filter_boomstream\base_text_filter::class);
} else {
    class_alias(\moodle_text_filter::class, \filter_boomstream\base_text_filter::class);
}
