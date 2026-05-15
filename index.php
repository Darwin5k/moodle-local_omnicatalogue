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
 * Course catalogue page.
 *
 * @package    local_omnicatalogue
 * @copyright  2026 Your Name <you@example.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('local/omnicatalogue:view', context_system::instance());

use local_omnicatalogue\catalogue;

// Parse filter params: f[fieldid][] = value.
// optional_param_array() cannot handle nested arrays, so read $_GET directly
// and sanitize each scalar value individually with clean_param().
$rawf = (isset($_GET['f']) && is_array($_GET['f'])) ? $_GET['f'] : [];
$activefilters = [];
foreach ($rawf as $fieldid => $values) {
    $fieldid = (int)$fieldid;
    if ($fieldid <= 0 || !is_array($values)) {
        continue;
    }
    $clean = [];
    foreach ($values as $v) {
        $v = clean_param($v, PARAM_NOTAGS);
        if ($v !== '') {
            $clean[] = $v;
        }
    }
    if (!empty($clean)) {
        $activefilters[$fieldid] = $clean;
    }
}

$page    = max(0, optional_param('page', 0, PARAM_INT));
$perpage = (int)(get_config('local_omnicatalogue', 'perpage') ?: 20);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/omnicatalogue/index.php'));
$PAGE->set_title(get_string('catalogue', 'local_omnicatalogue'));
$PAGE->set_heading(get_string('catalogue', 'local_omnicatalogue'));
$PAGE->set_pagelayout('base');

$facets     = catalogue::get_facets($activefilters);
$result     = catalogue::get_courses($activefilters, $page, $perpage);
$cardfields = catalogue::get_card_fields();

// Build course card data.
$cards = [];
foreach ($result['courses'] as $course) {
    $summary = format_text(
        $course->summary,
        $course->summaryformat,
        ['context' => context_system::instance(), 'para' => false]
    );
    // Truncate summary for card display.
    $summary = html_to_text($summary, 0, false);
    if (core_text::strlen($summary) > 200) {
        $summary = core_text::substr($summary, 0, 200) . '…';
    }
    $cards[] = [
        'id'          => $course->id,
        'fullname'    => format_string($course->fullname),
        'summary'     => $summary,
        'category'    => format_string($course->categoryname),
        'courseurl'   => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
        'imageurl'    => catalogue::get_course_image_url($course),
        'fieldvalues' => catalogue::get_course_card_fieldvalues($course, $cardfields),
        'hasfieldvalues' => !empty(catalogue::get_course_card_fieldvalues($course, $cardfields)),
    ];
}

// Build a form action URL preserving current filters for pagination links.
$baseurl = new moodle_url('/local/omnicatalogue/index.php');

// Encode active filters as flat hidden-input data for pagination.
$filterparams = [];
foreach ($activefilters as $fieldid => $values) {
    foreach ($values as $v) {
        $filterparams[] = ['name' => "f[{$fieldid}][]", 'value' => $v];
    }
}

$templatecontext = [
    'facets'        => $facets,
    'hasfacets'     => !empty($facets),
    'courses'       => $cards,
    'totalcount'    => $result['total'],
    'resultstring'  => get_string('results', 'local_omnicatalogue', $result['total']),
    'hasfilters'    => !empty($activefilters),
    'nocourses'     => empty($cards),
    'formaction'    => $baseurl->out(false),
    'filterparams'  => $filterparams,
    'page'          => $page,
    'perpage'       => $perpage,
    'haspages'      => $result['total'] > $perpage,
    'prevpage'      => $page > 0 ? $page - 1 : null,
    'nextpage'      => ($page + 1) * $perpage < $result['total'] ? $page + 1 : null,
    'hasprev'       => $page > 0,
    'hasnext'       => ($page + 1) * $perpage < $result['total'],
    'clearurl'      => $baseurl->out(false),
    'applyfilters'  => get_string('applyfilters', 'local_omnicatalogue'),
    'clearfilters'  => get_string('clearfilters', 'local_omnicatalogue'),
    'filterby'      => get_string('filterby', 'local_omnicatalogue'),
    'nocoursestr'   => get_string('nocourses', 'local_omnicatalogue'),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_omnicatalogue/catalogue_page', $templatecontext);
echo $OUTPUT->footer();
