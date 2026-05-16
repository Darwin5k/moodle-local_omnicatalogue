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
 * @package    local_omnicatalogue
 * @copyright  2026 Your Name <you@example.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalogue {
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
            // Defaults to enabled when no config value has been saved yet.
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

    /**
     * Returns facet data for the filter sidebar.
     *
     * Each facet contains the field name, a list of values with counts, and which
     * values are currently selected according to $activefilters.
     *
     * Active filters carry option display strings (from the URL), so the selected
     * check compares by string value rather than option ID.
     *
     * @param array $activefilters Keyed by field ID, values are arrays of selected strings.
     * @return array
     */
    public static function get_facets(array $activefilters = []): array {
        global $DB;

        $facets = [];
        foreach (self::get_filter_fields() as $fieldid => $field) {
            // Join with opts to get the display label from the stable opts table,
            // not from the vals table (which stores option IDs, not strings).
            $sql = "SELECT oo.id AS optionid, oo.value, COUNT(DISTINCT omv.instanceid) AS cnt
                      FROM {customfield_omniselect_vals} omv
                      JOIN {customfield_omniselect_opts} oo ON oo.id = omv.optionid
                      JOIN {course} c ON c.id = omv.instanceid
                     WHERE omv.fieldid = :fieldid
                       AND c.visible = 1
                       AND c.id <> :siteid
                  GROUP BY oo.id, oo.value
                  ORDER BY oo.value ASC";

            $rows     = $DB->get_records_sql($sql, ['fieldid' => $fieldid, 'siteid' => SITEID]);
            $selected = $activefilters[$fieldid] ?? [];

            $values = [];
            foreach ($rows as $row) {
                $values[] = [
                    'value'    => $row->value,
                    'count'    => (int)$row->cnt,
                    'selected' => in_array($row->value, $selected, true),
                    'filterid' => $fieldid,
                ];
            }

            if (!empty($values)) {
                $facets[] = [
                    'fieldid'   => $fieldid,
                    'fieldname' => $field->get_formatted_name(),
                    'values'    => $values,
                    'hasactive' => !empty($selected),
                ];
            }
        }

        return $facets;
    }

    /**
     * Returns a paginated list of visible courses matching the given filters.
     *
     * Filters are ANDed between facets and ORed within a facet. Active filters
     * carry option display strings (from the URL); the query joins with
     * customfield_omniselect_opts to translate strings to option IDs on the fly,
     * so URL parameters remain human-readable.
     *
     * @param array $activefilters Keyed by field ID, values are arrays of selected strings.
     * @param int   $page          Zero-based page number.
     * @param int   $perpage       Rows per page.
     * @return array{courses: \stdClass[], total: int}
     */
    public static function get_courses(array $activefilters = [], int $page = 0, int $perpage = 20): array {
        global $DB;

        $params = ['siteid' => SITEID];
        $joins  = [];
        $i      = 0;

        foreach ($activefilters as $fieldid => $values) {
            if (empty($values)) {
                continue;
            }
            $i++;
            [$insql, $inparams] = $DB->get_in_or_equal(
                array_values($values),
                SQL_PARAMS_NAMED,
                "v{$i}_"
            );
            // Join vals + opts so we can filter by display string while storing IDs.
            $joins[] = "JOIN {customfield_omniselect_vals} omv{$i}
                          ON omv{$i}.instanceid = c.id
                         AND omv{$i}.fieldid = :fid{$i}
                        JOIN {customfield_omniselect_opts} oo{$i}
                          ON oo{$i}.id = omv{$i}.optionid
                         AND oo{$i}.value {$insql}";
            $params["fid{$i}"] = (int)$fieldid;
            $params             = array_merge($params, $inparams);
        }

        $joinsql = implode("\n", $joins);

        $countsql = "SELECT COUNT(DISTINCT c.id)
                       FROM {course} c
                            {$joinsql}
                      WHERE c.visible = 1
                        AND c.id <> :siteid";

        $selectsql = "SELECT DISTINCT c.id, c.fullname, c.shortname,
                             c.summary, c.summaryformat, cat.name AS categoryname
                        FROM {course} c
                        JOIN {course_categories} cat ON cat.id = c.category
                             {$joinsql}
                       WHERE c.visible = 1
                         AND c.id <> :siteid
                    ORDER BY c.fullname ASC";

        $total   = $DB->count_records_sql($countsql, $params);
        $courses = $DB->get_records_sql($selectsql, $params, $page * $perpage, $perpage);

        return ['courses' => array_values($courses), 'total' => $total];
    }

    /**
     * Returns the URL of the course overview image, or a generated placeholder.
     *
     * @param \stdClass $course
     * @return string Absolute URL string.
     */
    public static function get_course_image_url(\stdClass $course): string {
        global $OUTPUT;

        // Same API Moodle's course cache uses.
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

        // Fall back to the same generated gradient used by the myoverview block.
        return $OUTPUT->get_generated_image_for_id($course->id);
    }

    /**
     * Returns card field values for a given course, formatted for template rendering.
     *
     * Joins with opts to resolve option IDs to display labels, ordered by sortorder.
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
     * Note: moodle_url does not natively support array-style params (f[id][]),
     * so filter params are encoded as hidden form inputs in index.php instead.
     *
     * @param array $activefilters Keyed by field ID, values are arrays of strings.
     * @param int   $page          Zero-based page number.
     * @return \moodle_url
     */
    public static function build_filter_url(array $activefilters, int $page = 0): \moodle_url {
        return new \moodle_url('/local/omnicatalogue/index.php', ['page' => $page]);
    }
}
