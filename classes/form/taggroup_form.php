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
 * Form for creating and editing catalogue tag groups.
 *
 * @package    local_omnicatalogue
 * @copyright  2026 Your Name <you@example.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_omnicatalogue\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Moodleform for creating or editing a catalogue tag group.
 *
 * Custom data:
 *   - groupid (int) — 0 for a new group, existing ID for edits.
 *
 * @package    local_omnicatalogue
 * @copyright  2026 Your Name <you@example.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class taggroup_form extends \moodleform {
    /**
     * Defines form fields.
     */
    public function definition() {
        global $DB;

        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('groupname', 'local_omnicatalogue'), ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Load all tags assigned to at least one visible course.
        $tags = $DB->get_records_sql(
            "SELECT DISTINCT t.id, t.name
               FROM {tag} t
               JOIN {tag_instance} ti ON ti.tagid = t.id
               JOIN {course} c ON c.id = ti.itemid
              WHERE ti.itemtype = 'course'
                AND c.visible = 1
                AND c.id <> :siteid
           ORDER BY t.name ASC",
            ['siteid' => SITEID]
        );

        if ($tags) {
            $mform->addElement('header', 'tagsheader', get_string('selecttags', 'local_omnicatalogue'));
            $mform->setExpanded('tagsheader');
            foreach ($tags as $tag) {
                $mform->addElement('checkbox', 'tag_' . $tag->id, $tag->name);
            }
        } else {
            $mform->addElement('static', 'notags', '', get_string('nocoursetags', 'local_omnicatalogue'));
        }

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        // Preserve the current action (add/edit) so the page can re-enter the
        // correct branch when the form is submitted.
        $action = $this->_customdata['action'] ?? 'add';
        $mform->addElement('hidden', 'action', $action);
        $mform->setType('action', PARAM_ALPHA);

        $this->add_action_buttons(true, get_string('savegroup', 'local_omnicatalogue'));
    }
}
