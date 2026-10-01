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
 * Boomstream filter entry point for Moodle 4.4 and older (Moodle 4.5+ uses classes/text_filter.php).
 *
 * @package    filter_boomstream
 * @copyright  2026 HWD LTD <support@boomstream.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (class_exists(\core_filters\text_filter::class)) {
    // Moodle 4.5+: the filter is implemented by \filter_boomstream\text_filter.
    class_alias(\filter_boomstream\text_filter::class, 'filter_boomstream');
} else {
    /**
     * Boomstream text filter.
     *
     * @package    filter_boomstream
     * @copyright  2026 HWD LTD <support@boomstream.com>
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     */
    class filter_boomstream extends moodle_text_filter {
        /**
         * Filters the text.
         *
         * @param string $text Text to filter.
         * @param array $options Filter options.
         * @return string Filtered text.
         */
        public function filter($text, array $options = []) {
            return (new \filter_boomstream\boomstream())->filter($text, $options);
        }
    }
}
