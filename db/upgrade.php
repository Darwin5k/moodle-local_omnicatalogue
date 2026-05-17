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
 * Upgrade steps for local_omnicatalogue.
 *
 * @package    local_omnicatalogue
 * @copyright  2026 Your Name <you@example.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrades the local_omnicatalogue plugin.
 *
 * @param int $oldversion Previous installed version number.
 * @return bool
 */
function xmldb_local_omnicatalogue_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026051401) {
        // No schema changes in this release — version bump only.
        upgrade_plugin_savepoint(true, 2026051401, 'local', 'omnicatalogue');
    }

    if ($oldversion < 2026051503) {
        // Create tag groups table.
        $table = new xmldb_table('local_omnicatalogue_taggroups');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Create tag-to-group mapping table.
        $table = new xmldb_table('local_omnicatalogue_tgroup_tags');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('tagid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_groupid', XMLDB_KEY_FOREIGN, ['groupid'], 'local_omnicatalogue_taggroups', ['id']);
        $table->add_index('uniq_group_tag', XMLDB_INDEX_UNIQUE, ['groupid', 'tagid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026051503, 'local', 'omnicatalogue');
    }

    return true;
}
