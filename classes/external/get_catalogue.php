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
 * External function returning facets and course cards for the catalogue.
 *
 * @package    local_omnicatalogue
 * @copyright  2026 Robert Bellamy <darwin5k@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_omnicatalogue\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use local_omnicatalogue\catalogue;

/**
 * Returns the facet sidebar data and course cards needed to render (or re-render)
 * the catalogue in response to a filter change or page navigation.
 *
 * @package    local_omnicatalogue
 * @copyright  2026 Robert Bellamy <darwin5k@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_catalogue extends external_api {
    /**
     * Describes the input parameters.
     *
     * @since  Moodle 5.1
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'filters' => new external_multiple_structure(
                new external_single_structure([
                    'facetkey' => new external_value(PARAM_ALPHANUMEXT, 'Facet key (e.g. cf_3, cat, et, tg_7)'),
                    'values'   => new external_multiple_structure(
                        new external_value(PARAM_NOTAGS, 'Selected filter value')
                    ),
                ]),
                'Active filter set',
                VALUE_DEFAULT,
                []
            ),
            'page' => new external_value(PARAM_INT, 'Zero-based page number', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Returns facets and course cards for the given filter state.
     *
     * @since  Moodle 5.1
     * @param  array $filters Array of {facetkey, values[]} objects.
     * @param  int   $page    Zero-based page number.
     * @return array
     */
    public static function execute(array $filters = [], int $page = 0): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'filters' => $filters,
            'page'    => $page,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/omnicatalogue:view', $context);

        // Normalise [{facetkey, values}] → [facetkey => values].
        $activefilters = [];
        foreach ($params['filters'] as $f) {
            if (!empty($f['values'])) {
                $activefilters[$f['facetkey']] = $f['values'];
            }
        }

        $perpage = (int)(get_config('local_omnicatalogue', 'perpage') ?: 20);
        $page    = max(0, (int)$params['page']);

        $display    = catalogue::get_card_display_settings();
        $facets     = catalogue::get_facets($activefilters);
        $result     = catalogue::get_courses($activefilters, $page, $perpage);
        $cardfields = catalogue::get_card_fields();

        // Pre-fetch enrolment data once for the whole response.
        $enrolledids  = $display['showenrolstatus'] ? catalogue::get_enrolled_course_ids() : [];
        $completedids = $display['showenrolstatus'] ? catalogue::get_completed_course_ids() : [];

        // Build course card data (mirrors index.php logic).
        $courses = [];
        foreach ($result['courses'] as $course) {
            $summary = format_text(
                $course->summary,
                $course->summaryformat,
                ['context' => $context, 'para' => false]
            );
            $summary = html_to_text($summary, 0, false);
            if (\core_text::strlen($summary) > 200) {
                $summary = \core_text::substr($summary, 0, 200) . '…';
            }

            $fieldvalues  = catalogue::get_course_card_fieldvalues($course, $cardfields);
            $contacts     = $display['showcontacts'] ? catalogue::get_course_contacts_string($course) : '';
            $enroltype    = $display['showenroltype'] ? catalogue::get_course_enrol_type($course) : '';
            $iscompleted  = $display['showenrolstatus'] && in_array((int)$course->id, $completedids, true);
            $isenrolled   = $display['showenrolstatus'] && in_array((int)$course->id, $enrolledids, true);
            $enrolledonly = $isenrolled && !$iscompleted;

            $courses[] = [
                'showimage'       => $display['showimage'],
                'showsummary'     => $display['showsummary'],
                'showcategory'    => $display['showcategory'],
                'showcontacts'    => $display['showcontacts'],
                'showenroltype'   => $display['showenroltype'],
                'showenrolstatus' => $display['showenrolstatus'],
                'id'              => (int)$course->id,
                'fullname'        => format_string($course->fullname),
                'summary'         => $summary,
                'category'        => format_string($course->categoryname),
                'courseurl'       => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'imageurl'        => catalogue::get_course_image_url($course),
                'hasfieldvalues'  => !empty($fieldvalues),
                'fieldvalues'     => array_values($fieldvalues),
                'contacts'        => $contacts,
                'enroltype'       => $enroltype,
                'completed'       => $iscompleted,
                'enrolledonly'    => $enrolledonly,
                'hasbadges'       => $iscompleted || $enrolledonly || ($enroltype !== ''),
            ];
        }

        $total   = (int)$result['total'];
        $hasprev = $page > 0;
        $hasnext = ($page + 1) * $perpage < $total;

        return [
            'facets'       => array_values($facets),
            'courses'      => $courses,
            'total'        => $total,
            'resultstring' => get_string('results', 'local_omnicatalogue', $total),
            'hasfilters'   => !empty($activefilters),
            'nocourses'    => empty($courses),
            'page'         => $page,
            'perpage'      => $perpage,
            'haspages'     => $total > $perpage,
            'hasprev'      => $hasprev,
            'hasnext'      => $hasnext,
            'prevpage'     => $hasprev ? $page - 1 : 0,
            'nextpage'     => $page + 1,
        ];
    }

    /**
     * Describes the return structure.
     *
     * @since  Moodle 5.1
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $valuedef = new external_single_structure([
            'value'    => new external_value(PARAM_NOTAGS, 'Raw filter value (ID or name string)'),
            'label'    => new external_value(PARAM_TEXT, 'Human-readable display label'),
            'count'    => new external_value(PARAM_INT, 'Number of matching courses'),
            'selected' => new external_value(PARAM_BOOL, 'Whether this value is currently selected'),
            'facetkey' => new external_value(PARAM_ALPHANUMEXT, 'Facet key used in the checkbox name attribute'),
        ]);

        $facetdef = new external_single_structure([
            'facetkey'  => new external_value(PARAM_ALPHANUMEXT, 'Facet key'),
            'fieldname' => new external_value(PARAM_TEXT, 'Human-readable facet name'),
            'hasactive' => new external_value(PARAM_BOOL, 'Whether any values in this facet are selected'),
            'values'    => new external_multiple_structure($valuedef),
        ]);

        $fieldvaluedef = new external_single_structure([
            'fieldname' => new external_value(PARAM_TEXT, 'Field display name'),
            'values'    => new external_value(PARAM_TEXT, 'Comma-separated selected option labels'),
        ]);

        $coursedef = new external_single_structure([
            'showimage'       => new external_value(PARAM_BOOL, 'Show course image'),
            'showsummary'     => new external_value(PARAM_BOOL, 'Show course summary'),
            'showcategory'    => new external_value(PARAM_BOOL, 'Show category name'),
            'showcontacts'    => new external_value(PARAM_BOOL, 'Show course contacts'),
            'showenroltype'   => new external_value(PARAM_BOOL, 'Show enrolment type badge'),
            'showenrolstatus' => new external_value(PARAM_BOOL, 'Show enrolment status badge'),
            'id'              => new external_value(PARAM_INT, 'Course ID'),
            'fullname'        => new external_value(PARAM_TEXT, 'Course full name'),
            'summary'         => new external_value(PARAM_RAW, 'Plain-text summary excerpt'),
            'category'        => new external_value(PARAM_TEXT, 'Category name'),
            'courseurl'       => new external_value(PARAM_URL, 'URL to the course page'),
            'imageurl'        => new external_value(PARAM_RAW, 'URL or data URI of the course overview image'),
            'hasfieldvalues'  => new external_value(PARAM_BOOL, 'Whether any card fields have values'),
            'fieldvalues'     => new external_multiple_structure($fieldvaluedef),
            'contacts'        => new external_value(PARAM_TEXT, 'Comma-separated course contact names'),
            'enroltype'       => new external_value(PARAM_TEXT, 'Short enrolment method label'),
            'completed'       => new external_value(PARAM_BOOL, 'Whether the current user has completed this course'),
            'enrolledonly'    => new external_value(PARAM_BOOL, 'Whether the user is enrolled but not yet completed'),
            'hasbadges'       => new external_value(PARAM_BOOL, 'Whether any badge should be shown on the card'),
        ]);

        return new external_single_structure([
            'facets'       => new external_multiple_structure($facetdef),
            'courses'      => new external_multiple_structure($coursedef),
            'total'        => new external_value(PARAM_INT, 'Total number of matching courses'),
            'resultstring' => new external_value(PARAM_TEXT, 'Localised result-count string'),
            'hasfilters'   => new external_value(PARAM_BOOL, 'Whether any filters are currently active'),
            'nocourses'    => new external_value(PARAM_BOOL, 'Whether the result set is empty'),
            'page'         => new external_value(PARAM_INT, 'Current zero-based page number'),
            'perpage'      => new external_value(PARAM_INT, 'Items per page'),
            'haspages'     => new external_value(PARAM_BOOL, 'Whether pagination controls are needed'),
            'hasprev'      => new external_value(PARAM_BOOL, 'Whether a previous page exists'),
            'hasnext'      => new external_value(PARAM_BOOL, 'Whether a next page exists'),
            'prevpage'     => new external_value(PARAM_INT, 'Previous page number (0 if none)'),
            'nextpage'     => new external_value(PARAM_INT, 'Next page number'),
        ]);
    }
}
