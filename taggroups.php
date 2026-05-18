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
 * Tag group management page for local_omnicatalogue.
 *
 * Allows administrators to create named tag groups and assign Moodle
 * course tags to them. Only tags that belong to a group appear in the
 * catalogue filter sidebar.
 *
 * @package    local_omnicatalogue
 * @copyright  2026 Robert Bellamy <darwin5k@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('local/omnicatalogue:managecatalogue', $context);

$action  = optional_param('action', 'list', PARAM_ALPHA);
$groupid = optional_param('id', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/omnicatalogue/taggroups.php'));
$PAGE->set_title(get_string('managetaggroups', 'local_omnicatalogue'));
$PAGE->set_heading(get_string('managetaggroups', 'local_omnicatalogue'));
$PAGE->set_pagelayout('admin');

// Delete action — requires sesskey, removes group and its tag assignments.
if ($action === 'delete' && $groupid > 0) {
    require_sesskey();
    $groupname = $DB->get_field('local_omnicatalogue_taggroups', 'name', ['id' => $groupid]);
    $DB->delete_records('local_omnicatalogue_tgroup_tags', ['groupid' => $groupid]);
    $DB->delete_records('local_omnicatalogue_taggroups', ['id' => $groupid]);
    redirect(
        new moodle_url('/local/omnicatalogue/taggroups.php'),
        get_string('groupdeleted', 'local_omnicatalogue'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Add / edit — show form.
if ($action === 'add' || ($action === 'edit' && $groupid > 0)) {
    if ($groupid > 0) {
        $group = $DB->get_record('local_omnicatalogue_taggroups', ['id' => $groupid], '*', MUST_EXIST);
    } else {
        $group = null;
    }

    $form = new \local_omnicatalogue\form\taggroup_form(null, ['groupid' => $groupid, 'action' => $action]);

    if ($group) {
        // Pre-populate form with existing data.
        $existingtagids = $DB->get_fieldset_select(
            'local_omnicatalogue_tgroup_tags',
            'tagid',
            'groupid = ?',
            [$groupid]
        );
        $formdata = ['id' => $group->id, 'name' => $group->name, 'action' => 'save'];
        foreach ($existingtagids as $tagid) {
            $formdata['tag_' . $tagid] = 1;
        }
        $form->set_data((object)$formdata);
    }

    if ($form->is_cancelled()) {
        redirect(new moodle_url('/local/omnicatalogue/taggroups.php'));
    }

    if ($data = $form->get_data()) {
        // Persist the group record.
        if (!empty($data->id)) {
            $DB->update_record('local_omnicatalogue_taggroups', (object)[
                'id'           => $data->id,
                'name'         => $data->name,
                'timemodified' => time(),
            ]);
            $savedgroupid = (int)$data->id;
        } else {
            $savedgroupid = $DB->insert_record('local_omnicatalogue_taggroups', (object)[
                'name'         => $data->name,
                'sortorder'    => 0,
                'timecreated'  => time(),
                'timemodified' => time(),
            ]);
        }

        // Rebuild tag assignments from submitted checkboxes.
        $DB->delete_records('local_omnicatalogue_tgroup_tags', ['groupid' => $savedgroupid]);
        foreach ((array)$data as $key => $val) {
            if (str_starts_with($key, 'tag_') && $val) {
                $tagid = (int)substr($key, 4);
                if ($tagid > 0) {
                    $DB->insert_record('local_omnicatalogue_tgroup_tags', (object)[
                        'groupid' => $savedgroupid,
                        'tagid'   => $tagid,
                    ]);
                }
            }
        }

        redirect(
            new moodle_url('/local/omnicatalogue/taggroups.php'),
            get_string('groupsaved', 'local_omnicatalogue'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    echo $OUTPUT->header();
    echo $form->render();
    echo $OUTPUT->footer();
    exit;
}

// List view — display existing groups with edit / delete actions.
$groups    = $DB->get_records('local_omnicatalogue_taggroups', null, 'sortorder ASC, name ASC');
$grouprows = [];
foreach ($groups as $group) {
    $tagcount  = $DB->count_records('local_omnicatalogue_tgroup_tags', ['groupid' => $group->id]);
    $editurl   = new moodle_url('/local/omnicatalogue/taggroups.php', ['action' => 'edit', 'id' => $group->id]);
    $deleteurl = new moodle_url('/local/omnicatalogue/taggroups.php', [
        'action'  => 'delete',
        'id'      => $group->id,
        'sesskey' => sesskey(),
    ]);
    $grouprows[] = [
        'name'          => format_string($group->name),
        'tagcount'      => $tagcount,
        'editurl'       => $editurl->out(false),
        'deleteurl'     => $deleteurl->out(false),
        'deleteconfirm' => get_string('confirmdelete', 'local_omnicatalogue', format_string($group->name)),
    ];
}

$templatecontext = [
    'addurl'    => (new moodle_url('/local/omnicatalogue/taggroups.php', ['action' => 'add']))->out(false),
    'groups'    => $grouprows,
    'hasgroups' => !empty($grouprows),
    'addgroup'  => get_string('addgroup', 'local_omnicatalogue'),
    'nogroups'  => get_string('nogroups', 'local_omnicatalogue'),
    'groupname' => get_string('groupname', 'local_omnicatalogue'),
    'tagcount'  => get_string('tagcount', 'local_omnicatalogue'),
];

$PAGE->requires->js_call_amd('local_omnicatalogue/taggroups', 'init');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_omnicatalogue/taggroups_page', $templatecontext);
echo $OUTPUT->footer();
