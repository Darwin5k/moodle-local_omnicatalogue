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
 * Admin settings for local_omnicatalogue.
 *
 * Dynamically generates a filter/card visibility toggle for every omniselect
 * custom field defined on courses.
 *
 * @package    local_omnicatalogue
 * @copyright  2026 Your Name <you@example.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_omnicatalogue', get_string('settings', 'local_omnicatalogue'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext(
        'local_omnicatalogue/perpage',
        get_string('perpage', 'local_omnicatalogue'),
        get_string('perpage_desc', 'local_omnicatalogue'),
        20,
        PARAM_INT
    ));

    // Dynamically add a toggle per omniselect field for filter sidebar and course cards.
    try {
        $handler = \core_course\customfield\course_handler::create();
        $fields  = $handler->get_fields();
        foreach ($fields as $field) {
            if ($field->get('type') !== 'omniselect') {
                continue;
            }
            $id   = $field->get('id');
            $name = $field->get_formatted_name();

            $settings->add(new admin_setting_configcheckbox(
                'local_omnicatalogue/filterfield_' . $id,
                get_string('showfilterfield', 'local_omnicatalogue', $name),
                '',
                1
            ));

            $settings->add(new admin_setting_configcheckbox(
                'local_omnicatalogue/cardfield_' . $id,
                get_string('showcardfield', 'local_omnicatalogue', $name),
                '',
                0
            ));
        }
    } catch (\Throwable $e) {
        // Fields not yet available during initial install; settings populate after upgrade.
        debugging('local_omnicatalogue settings: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}
