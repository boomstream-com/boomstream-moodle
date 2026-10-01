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

namespace filter_boomstream\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;

/**
 * Privacy provider.
 *
 * The plugin stores nothing in Moodle, but sends user data to the external Boomstream service.
 * The data held by Boomstream has to be exported or deleted in the Boomstream account.
 *
 * @package    filter_boomstream
 * @copyright  2026 HWD LTD <support@boomstream.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describes the personal data sent to the Boomstream service.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link('boomstream', [
            'userid' => 'privacy:metadata:boomstream:userid',
            'email' => 'privacy:metadata:boomstream:email',
            'sitehost' => 'privacy:metadata:boomstream:sitehost',
        ], 'privacy:metadata:boomstream');
        return $collection;
    }

    /**
     * No user data is stored in Moodle.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        return new contextlist();
    }

    /**
     * No user data is stored in Moodle.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
    }

    /**
     * No user data is stored in Moodle.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
    }

    /**
     * No user data is stored in Moodle.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
    }

    /**
     * No user data is stored in Moodle.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
    }

    /**
     * No user data is stored in Moodle.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
    }
}
