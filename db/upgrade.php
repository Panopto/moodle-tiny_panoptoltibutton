<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Upgrade steps for the Tiny Panopto LTI Video plugin.
 *
 * @package    tiny_panoptoltibutton
 * @copyright  2026 Panopto
 * @author     Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the Tiny Panopto LTI Video plugin.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool always true on success
 */
function xmldb_tiny_panoptoltibutton_upgrade($oldversion) {

    if ($oldversion < 2026092200) {
        // Grant the capability to student and teacher.
        $systemcontext = context_system::instance();

        foreach (['student', 'teacher'] as $archetype) {
            foreach (get_archetype_roles($archetype) as $role) {
                // Leave an existing prevent or prohibit in place.
                assign_capability(
                    'tiny/panoptoltibutton:use',
                    CAP_ALLOW,
                    $role->id,
                    $systemcontext->id,
                    false
                );
            }
        }

        // Panopto savepoint reached.
        upgrade_plugin_savepoint(true, 2026092200, 'tiny', 'panoptoltibutton');
    }

    return true;
}
