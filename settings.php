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
 * @package    local_omnicatalogue
 * @copyright  2026 Robert Bellamy <darwin5k@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_omnicatalogue', get_string('settings', 'local_omnicatalogue'));
    $ADMIN->add('localplugins', $settings);

    // Tag group management — separate admin external page.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_omnicatalogue_taggroups',
        get_string('managetaggroups', 'local_omnicatalogue'),
        new moodle_url('/local/omnicatalogue/taggroups.php'),
        'local/omnicatalogue:managecatalogue'
    ));

    // General settings.
    $settings->add(new admin_setting_configtext(
        'local_omnicatalogue/perpage',
        get_string('perpage', 'local_omnicatalogue'),
        get_string('perpage_desc', 'local_omnicatalogue'),
        20,
        PARAM_INT
    ));

    // Card display options.
    $settings->add(new admin_setting_heading(
        'local_omnicatalogue/carddisplay_heading',
        get_string('carddisplay', 'local_omnicatalogue'),
        get_string('carddisplay_desc', 'local_omnicatalogue')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_omnicatalogue/card_showimage',
        get_string('card_showimage', 'local_omnicatalogue'),
        get_string('card_showimage_desc', 'local_omnicatalogue'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_omnicatalogue/card_showsummary',
        get_string('card_showsummary', 'local_omnicatalogue'),
        get_string('card_showsummary_desc', 'local_omnicatalogue'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_omnicatalogue/card_showcategory',
        get_string('card_showcategory', 'local_omnicatalogue'),
        get_string('card_showcategory_desc', 'local_omnicatalogue'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_omnicatalogue/card_showcontacts',
        get_string('card_showcontacts', 'local_omnicatalogue'),
        get_string('card_showcontacts_desc', 'local_omnicatalogue'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_omnicatalogue/card_showenroltype',
        get_string('card_showenroltype', 'local_omnicatalogue'),
        get_string('card_showenroltype_desc', 'local_omnicatalogue'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_omnicatalogue/card_showenrolstatus',
        get_string('card_showenrolstatus', 'local_omnicatalogue'),
        get_string('card_showenrolstatus_desc', 'local_omnicatalogue'),
        0
    ));

    // Additional filter facets.
    $settings->add(new admin_setting_heading(
        'local_omnicatalogue/additionalfacets_heading',
        get_string('additionalfacets', 'local_omnicatalogue'),
        get_string('additionalfacets_desc', 'local_omnicatalogue')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_omnicatalogue/facet_category',
        get_string('facet_category', 'local_omnicatalogue'),
        get_string('facet_category_desc', 'local_omnicatalogue'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_omnicatalogue/facet_enroltype',
        get_string('facet_enroltype', 'local_omnicatalogue'),
        get_string('facet_enroltype_desc', 'local_omnicatalogue'),
        0
    ));

    $taggroupsurl = new moodle_url('/local/omnicatalogue/taggroups.php');
    $settings->add(new admin_setting_configcheckbox(
        'local_omnicatalogue/facet_taggroups',
        get_string('facet_taggroups', 'local_omnicatalogue'),
        get_string('facet_taggroups_desc', 'local_omnicatalogue', $taggroupsurl->out(false)),
        0
    ));

    // Custom field filters and card fields.
    $settings->add(new admin_setting_heading(
        'local_omnicatalogue/filterfields_heading',
        get_string('filterfields', 'local_omnicatalogue'),
        get_string('filterfields_desc', 'local_omnicatalogue')
    ));

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
