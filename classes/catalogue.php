<?php
namespace local_omnicatalogue;

defined('MOODLE_INTERNAL') || die();

class catalogue {

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

    public static function get_filter_fields(): array {
        $all = self::get_omniselect_fields();
        return array_filter($all, function($field) {
            $enabled = get_config('local_omnicatalogue', 'filterfield_' . $field->get('id'));
            return $enabled !== false ? (bool)$enabled : true; // default on
        });
    }

    public static function get_card_fields(): array {
        $all = self::get_omniselect_fields();
        return array_filter($all, function($field) {
            return (bool)get_config('local_omnicatalogue', 'cardfield_' . $field->get('id'));
        });
    }

    public static function get_facets(array $activefilters = []): array {
        global $DB;

        $facets = [];
        foreach (self::get_filter_fields() as $fieldid => $field) {
            $sql = "SELECT omv.value, COUNT(DISTINCT omv.instanceid) AS cnt
                      FROM {customfield_omniselect_vals} omv
                      JOIN {course} c ON c.id = omv.instanceid
                     WHERE omv.fieldid = :fieldid
                       AND c.visible = 1
                       AND c.id <> :siteid
                  GROUP BY omv.value
                  ORDER BY omv.value ASC";

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
                array_values($values), SQL_PARAMS_NAMED, "v{$i}"
            );
            $joins[] = "JOIN {customfield_omniselect_vals} omv{$i}
                          ON omv{$i}.instanceid = c.id
                         AND omv{$i}.fieldid = :fid{$i}
                         AND omv{$i}.value {$insql}";
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

    public static function get_course_image_url(\stdClass $course): string {
        global $OUTPUT;

        // Use core_course_list_element — the same API Moodle's course cache uses.
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

        // Fall back to Moodle's generated gradient image — deterministic per course ID,
        // the same fallback used by the myoverview block.
        return $OUTPUT->get_generated_image_for_id($course->id);
    }

    public static function get_course_card_fieldvalues(\stdClass $course, array $cardfields): array {
        global $DB;

        $result = [];
        foreach ($cardfields as $fieldid => $field) {
            $values = $DB->get_fieldset_select(
                'customfield_omniselect_vals',
                'value',
                'fieldid = ? AND instanceid = ?',
                [$fieldid, $course->id]
            );
            if (!empty($values)) {
                $result[] = [
                    'fieldname' => $field->get_formatted_name(),
                    'values'    => implode(', ', $values),
                ];
            }
        }
        return $result;
    }

    public static function build_filter_url(array $activefilters, int $page = 0): \moodle_url {
        $params = ['page' => $page];
        foreach ($activefilters as $fieldid => $values) {
            foreach ($values as $v) {
                // moodle_url doesn't support array params natively, handled via index.php
            }
        }
        return new \moodle_url('/local/omnicatalogue/index.php', $params);
    }
}
