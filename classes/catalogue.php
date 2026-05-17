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
 * Core query and data logic for the course catalogue.
 *
 * @package    local_omnicatalogue
 * @copyright  2026 Your Name <you@example.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_omnicatalogue;

/**
 * Provides course catalogue data: facets, filtered course lists, and card content.
 *
 * ### Facet key scheme
 *
 * Every filter facet is identified by a string key that encodes both the facet
 * type and (where needed) its database ID:
 *
 *  - `cf_{id}`  — omniselect custom field (e.g. `cf_3`)
 *  - `cat`      — course category
 *  - `et`       — enrolment type
 *  - `tg_{id}`  — tag group (e.g. `tg_7`)
 *
 * The key is used as-is in URL query strings (`f[cf_3][]=value`), in the
 * external-function wire format, and as the array key in `$activefilters`.
 *
 * @package    local_omnicatalogue
 * @copyright  2026 Your Name <you@example.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalogue {
    // Custom field helpers.

    /**
     * Returns all omniselect custom fields defined on courses, keyed by field ID.
     *
     * @return \core_customfield\field_controller[]
     */
    public static function get_omniselect_fields(): array {
        $handler = \core_course\customfield\course_handler::create();
        $fields  = $handler->get_fields();
        $result  = [];
        foreach ($fields as $field) {
            if ($field->get('type') === 'omniselect') {
                $result[$field->get('id')] = $field;
            }
        }
        return $result;
    }

    /**
     * Returns omniselect fields enabled as filter facets (defaults to all enabled).
     *
     * @return \core_customfield\field_controller[]
     */
    public static function get_filter_fields(): array {
        $all = self::get_omniselect_fields();
        return array_filter($all, function ($field) {
            $enabled = get_config('local_omnicatalogue', 'filterfield_' . $field->get('id'));
            return $enabled !== false ? (bool)$enabled : true;
        });
    }

    /**
     * Returns omniselect fields configured to appear on course cards.
     *
     * @return \core_customfield\field_controller[]
     */
    public static function get_card_fields(): array {
        $all = self::get_omniselect_fields();
        return array_filter($all, function ($field) {
            return (bool)get_config('local_omnicatalogue', 'cardfield_' . $field->get('id'));
        });
    }

    // Card display settings.

    /**
     * Returns the current card display settings as a boolean map.
     *
     * Settings that haven't been saved yet (get_config returns false) fall back
     * to their intended defaults: image, summary, and category default to on;
     * contacts, enrolment type, and enrolment status default to off.
     *
     * @return array<string,bool>
     */
    public static function get_card_display_settings(): array {
        $raw = function (string $key): mixed {
            return get_config('local_omnicatalogue', $key);
        };

        // Helper: a setting that defaults to ON when not yet saved.
        $defaulton = function (string $key) use ($raw): bool {
            $val = $raw($key);
            return ($val === false) ? true : (bool)$val;
        };

        return [
            'showimage'       => $defaulton('card_showimage'),
            'showsummary'     => $defaulton('card_showsummary'),
            'showcategory'    => $defaulton('card_showcategory'),
            'showcontacts'    => (bool)$raw('card_showcontacts'),
            'showenroltype'   => (bool)$raw('card_showenroltype'),
            'showenrolstatus' => (bool)$raw('card_showenrolstatus'),
        ];
    }

    // Facets.

    /**
     * Returns facet data for the filter sidebar.
     *
     * Facets are sourced from four places (in order): omniselect custom fields,
     * course category (if enabled), enrolment type (if enabled), and tag groups.
     *
     * Each facet value carries a raw `value` (used as the form checkbox value and
     * for filtering) and a `label` (displayed to the user — may differ for
     * categories and tags where the raw value is an ID).
     *
     * @param array $activefilters Keyed by facet key (string), values are arrays of selected strings.
     * @return array
     */
    public static function get_facets(array $activefilters = []): array {
        global $DB;

        $facets = [];

        // Omniselect custom field facets.
        foreach (self::get_filter_fields() as $fieldid => $field) {
            $facetkey = 'cf_' . $fieldid;
            $selected = $activefilters[$facetkey] ?? [];

            $sql = "SELECT oo.id AS optionid, oo.value, COUNT(DISTINCT omv.instanceid) AS cnt
                      FROM {customfield_omniselect_vals} omv
                      JOIN {customfield_omniselect_opts} oo ON oo.id = omv.optionid
                      JOIN {course} c ON c.id = omv.instanceid
                     WHERE omv.fieldid = :fieldid
                       AND c.visible = 1
                       AND c.id <> :siteid
                  GROUP BY oo.id, oo.value
                  ORDER BY oo.value ASC";

            $rows   = $DB->get_records_sql($sql, ['fieldid' => $fieldid, 'siteid' => SITEID]);
            $values = [];
            foreach ($rows as $row) {
                $values[] = [
                    'value'    => $row->value,
                    'label'    => $row->value,
                    'count'    => (int)$row->cnt,
                    'selected' => in_array($row->value, $selected, true),
                    'facetkey' => $facetkey,
                ];
            }

            if (!empty($values)) {
                $facets[] = [
                    'facetkey'  => $facetkey,
                    'fieldname' => $field->get_formatted_name(),
                    'hasactive' => !empty($selected),
                    'values'    => $values,
                ];
            }
        }

        // Course category facet (optional).
        if (get_config('local_omnicatalogue', 'facet_category')) {
            $facetkey = 'cat';
            $selected = $activefilters[$facetkey] ?? [];

            $sql = "SELECT c.category, cat.name, COUNT(DISTINCT c.id) AS cnt
                      FROM {course} c
                      JOIN {course_categories} cat ON cat.id = c.category
                     WHERE c.visible = 1
                       AND c.id <> :siteid
                  GROUP BY c.category, cat.name
                  ORDER BY cat.name ASC";

            $rows   = $DB->get_records_sql($sql, ['siteid' => SITEID]);
            $values = [];
            foreach ($rows as $row) {
                $catid    = (string)$row->category;
                $values[] = [
                    'value'    => $catid,
                    'label'    => format_string($row->name),
                    'count'    => (int)$row->cnt,
                    'selected' => in_array($catid, $selected, true),
                    'facetkey' => $facetkey,
                ];
            }

            if (!empty($values)) {
                $facets[] = [
                    'facetkey'  => $facetkey,
                    'fieldname' => get_string('category', 'local_omnicatalogue'),
                    'hasactive' => !empty($selected),
                    'values'    => $values,
                ];
            }
        }

        // Enrolment type facet (optional).
        if (get_config('local_omnicatalogue', 'facet_enroltype')) {
            $facetkey = 'et';
            $selected = $activefilters[$facetkey] ?? [];

            $sql = "SELECT e.enrol, COUNT(DISTINCT e.courseid) AS cnt
                      FROM {enrol} e
                      JOIN {course} c ON c.id = e.courseid
                     WHERE e.status = 0
                       AND c.visible = 1
                       AND c.id <> :siteid
                  GROUP BY e.enrol
                  ORDER BY e.enrol ASC";

            $rows   = $DB->get_records_sql($sql, ['siteid' => SITEID]);
            $values = [];
            foreach ($rows as $row) {
                $component = 'enrol_' . $row->enrol;
                if (get_string_manager()->string_exists('pluginname', $component)) {
                    $label = get_string('pluginname', $component);
                } else {
                    $label = ucfirst($row->enrol);
                }
                $values[] = [
                    'value'    => $row->enrol,
                    'label'    => $label,
                    'count'    => (int)$row->cnt,
                    'selected' => in_array($row->enrol, $selected, true),
                    'facetkey' => $facetkey,
                ];
            }

            if (!empty($values)) {
                $facets[] = [
                    'facetkey'  => $facetkey,
                    'fieldname' => get_string('enroltype', 'local_omnicatalogue'),
                    'hasactive' => !empty($selected),
                    'values'    => $values,
                ];
            }
        }

        // Tag group facets.
        $taggroups = $DB->get_records('local_omnicatalogue_taggroups', null, 'sortorder ASC, name ASC');
        foreach ($taggroups as $group) {
            $facetkey = 'tg_' . $group->id;
            $selected = $activefilters[$facetkey] ?? [];

            $sql = "SELECT t.id AS tagid, t.name, COUNT(DISTINCT ti.itemid) AS cnt
                      FROM {local_omnicatalogue_tgroup_tags} tgt
                      JOIN {tag} t ON t.id = tgt.tagid
                      JOIN {tag_instance} ti ON ti.tagid = t.id
                      JOIN {course} c ON c.id = ti.itemid
                     WHERE tgt.groupid = :groupid
                       AND ti.itemtype = 'course'
                       AND c.visible = 1
                       AND c.id <> :siteid
                  GROUP BY t.id, t.name
                  ORDER BY t.name ASC";

            $rows   = $DB->get_records_sql($sql, ['groupid' => $group->id, 'siteid' => SITEID]);
            $values = [];
            foreach ($rows as $row) {
                $tagidstr = (string)$row->tagid;
                $values[] = [
                    'value'    => $tagidstr,
                    'label'    => $row->name,
                    'count'    => (int)$row->cnt,
                    'selected' => in_array($tagidstr, $selected, true),
                    'facetkey' => $facetkey,
                ];
            }

            if (!empty($values)) {
                $facets[] = [
                    'facetkey'  => $facetkey,
                    'fieldname' => format_string($group->name),
                    'hasactive' => !empty($selected),
                    'values'    => $values,
                ];
            }
        }

        return $facets;
    }

    // Course list.

    /**
     * Returns a paginated list of visible courses matching the given filters.
     *
     * Filters are ANDed between facets and ORed within a facet. The filter key
     * determines the join strategy (see class-level docblock for the key scheme).
     *
     * @param array $activefilters Keyed by facet key (string), values are arrays of selected strings.
     * @param int   $page          Zero-based page number.
     * @param int   $perpage       Rows per page.
     * @return array{courses: \stdClass[], total: int}
     */
    public static function get_courses(array $activefilters = [], int $page = 0, int $perpage = 20): array {
        global $DB;

        $params = ['siteid' => SITEID];
        $joins  = [];
        $wheres = [];
        $i      = 0;

        foreach ($activefilters as $facetkey => $values) {
            if (empty($values)) {
                continue;
            }
            $i++;

            if (str_starts_with($facetkey, 'cf_')) {
                // Omniselect custom field filter: match on option display value.
                $fieldid = (int)substr($facetkey, 3);
                [$insql, $inparams] = $DB->get_in_or_equal(
                    array_values($values),
                    SQL_PARAMS_NAMED,
                    "v{$i}_"
                );
                $joins[] = "JOIN {customfield_omniselect_vals} omv{$i}
                              ON omv{$i}.instanceid = c.id
                             AND omv{$i}.fieldid = :fid{$i}
                            JOIN {customfield_omniselect_opts} oo{$i}
                              ON oo{$i}.id = omv{$i}.optionid
                             AND oo{$i}.value {$insql}";
                $params["fid{$i}"] = $fieldid;
                $params             = array_merge($params, $inparams);
            } else if ($facetkey === 'cat') {
                // Category filter: restrict by category ID directly on {course}.
                [$insql, $inparams] = $DB->get_in_or_equal(
                    array_map('intval', $values),
                    SQL_PARAMS_NAMED,
                    "cat{$i}_"
                );
                $wheres[] = "c.category {$insql}";
                $params   = array_merge($params, $inparams);
            } else if ($facetkey === 'et') {
                // Enrolment type filter: course must have an active instance of the type.
                [$insql, $inparams] = $DB->get_in_or_equal(
                    array_values($values),
                    SQL_PARAMS_NAMED,
                    "et{$i}_"
                );
                $joins[] = "JOIN {enrol} enr{$i}
                              ON enr{$i}.courseid = c.id
                             AND enr{$i}.status = 0
                             AND enr{$i}.enrol {$insql}";
                $params  = array_merge($params, $inparams);
            } else if (str_starts_with($facetkey, 'tg_')) {
                // Tag group filter: course must be tagged with at least one of the selected tag IDs.
                [$insql, $inparams] = $DB->get_in_or_equal(
                    array_map('intval', $values),
                    SQL_PARAMS_NAMED,
                    "tg{$i}_"
                );
                $joins[] = "JOIN {tag_instance} ti{$i}
                              ON ti{$i}.itemid = c.id
                             AND ti{$i}.itemtype = 'course'
                             AND ti{$i}.tagid {$insql}";
                $params  = array_merge($params, $inparams);
            }
        }

        $joinsql  = implode("\n", $joins);
        $wheresql = $wheres ? 'AND ' . implode(' AND ', $wheres) : '';

        $countsql = "SELECT COUNT(DISTINCT c.id)
                       FROM {course} c
                            {$joinsql}
                      WHERE c.visible = 1
                        AND c.id <> :siteid
                            {$wheresql}";

        $selectsql = "SELECT DISTINCT c.id, c.fullname, c.shortname,
                             c.summary, c.summaryformat, c.enablecompletion,
                             cat.name AS categoryname
                        FROM {course} c
                        JOIN {course_categories} cat ON cat.id = c.category
                             {$joinsql}
                       WHERE c.visible = 1
                         AND c.id <> :siteid
                             {$wheresql}
                    ORDER BY c.fullname ASC";

        $total   = $DB->count_records_sql($countsql, $params);
        $courses = $DB->get_records_sql($selectsql, $params, $page * $perpage, $perpage);

        return ['courses' => array_values($courses), 'total' => $total];
    }

    // Enrolment helpers (called once per page, not per course).

    /**
     * Returns the set of course IDs the current user is enrolled in.
     *
     * A single DB-backed Moodle API call; the result should be cached by the
     * caller across the card-building loop.
     *
     * @return int[]
     */
    public static function get_enrolled_course_ids(): array {
        if (!isloggedin() || isguestuser()) {
            return [];
        }
        $courses = enrol_get_my_courses('id');
        return array_map('intval', array_keys($courses));
    }

    /**
     * Returns the set of course IDs the current user has fully completed.
     *
     * @return int[]
     */
    public static function get_completed_course_ids(): array {
        global $DB, $USER;

        if (!isloggedin() || isguestuser()) {
            return [];
        }

        return array_map('intval', $DB->get_fieldset_select(
            'course_completions',
            'course',
            'userid = ? AND timecompleted IS NOT NULL',
            [$USER->id]
        ));
    }

    // Per-course card data helpers.

    /**
     * Returns the URL of the course overview image, or a generated placeholder.
     *
     * @param \stdClass $course
     * @return string Absolute URL string.
     */
    public static function get_course_image_url(\stdClass $course): string {
        global $OUTPUT;

        $courseinlist = new \core_course_list_element($course);
        foreach ($courseinlist->get_course_overviewfiles() as $file) {
            if ($file->is_valid_image()) {
                return \moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    null,
                    $file->get_filepath(),
                    $file->get_filename()
                )->out(false);
            }
        }

        return $OUTPUT->get_generated_image_for_id($course->id);
    }

    /**
     * Returns a comma-separated string of course contact names, or empty string if none.
     *
     * Uses the site-level "Course contacts" role setting ($CFG->coursecontact).
     * Capped at three names to keep cards readable.
     *
     * @param \stdClass $course Course record (must include id).
     * @return string
     */
    public static function get_course_contacts_string(\stdClass $course): string {
        global $CFG, $DB;

        if (empty($CFG->coursecontact)) {
            return '';
        }

        $roleids = array_filter(array_map('intval', explode(',', $CFG->coursecontact)));
        if (empty($roleids)) {
            return '';
        }

        $context = \context_course::instance($course->id);
        [$insql, $params] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'role');
        $params['contextid'] = $context->id;

        $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname,
                       u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename
                  FROM {user} u
                  JOIN {role_assignments} ra ON ra.userid = u.id
                 WHERE ra.contextid = :contextid
                   AND ra.roleid {$insql}
              ORDER BY u.lastname ASC, u.firstname ASC";

        $users = $DB->get_records_sql($sql, $params, 0, 3);
        if (empty($users)) {
            return '';
        }

        return implode(', ', array_map('fullname', $users));
    }

    /**
     * Returns a short label for the most prominent active enrolment method on a course.
     *
     * Priority: self-enrolment > guest access > fee > first available instance.
     * Returns an empty string when no active enrolment instances exist.
     *
     * @param \stdClass $course
     * @return string
     */
    public static function get_course_enrol_type(\stdClass $course): string {
        $instances = enrol_get_instances($course->id, true);
        if (empty($instances)) {
            return '';
        }

        $priority = ['self' => 1, 'guest' => 2, 'fee' => 3];
        $best     = null;
        $bestrank = PHP_INT_MAX;

        foreach ($instances as $instance) {
            $rank = $priority[$instance->enrol] ?? 100;
            if ($rank < $bestrank) {
                $bestrank = $rank;
                $best     = $instance;
            }
        }

        if (!$best) {
            $best = reset($instances);
        }

        $component = 'enrol_' . $best->enrol;
        if (get_string_manager()->string_exists('pluginname', $component)) {
            return get_string('pluginname', $component);
        }

        return ucfirst($best->enrol);
    }

    /**
     * Returns card field values for a given course, formatted for template rendering.
     *
     * @param \stdClass $course
     * @param \core_customfield\field_controller[] $cardfields
     * @return array
     */
    public static function get_course_card_fieldvalues(\stdClass $course, array $cardfields): array {
        global $DB;

        $result = [];
        foreach ($cardfields as $fieldid => $field) {
            $rows = $DB->get_records_sql(
                "SELECT oo.value
                   FROM {customfield_omniselect_vals} omv
                   JOIN {customfield_omniselect_opts} oo ON oo.id = omv.optionid
                  WHERE omv.fieldid = :fieldid
                    AND omv.instanceid = :instanceid
               ORDER BY oo.sortorder ASC, oo.value ASC",
                ['fieldid' => $fieldid, 'instanceid' => $course->id]
            );
            if (!empty($rows)) {
                $result[] = [
                    'fieldname' => $field->get_formatted_name(),
                    'values'    => implode(', ', array_column((array)$rows, 'value')),
                ];
            }
        }
        return $result;
    }

    /**
     * Builds a catalogue URL for the given page, preserving active filters.
     *
     * @param array $activefilters Keyed by facet key, values are arrays of strings.
     * @param int   $page          Zero-based page number.
     * @return \moodle_url
     */
    public static function build_filter_url(array $activefilters, int $page = 0): \moodle_url {
        $params = ['page' => $page];
        foreach ($activefilters as $facetkey => $values) {
            foreach ($values as $v) {
                $params["f[{$facetkey}][]"] = $v;
            }
        }
        return new \moodle_url('/local/omnicatalogue/index.php', $params);
    }
}
