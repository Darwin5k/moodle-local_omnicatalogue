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
 * Unit tests for local_omnicatalogue catalogue class.
 *
 * @package    local_omnicatalogue
 * @copyright  2026 Your Name <you@example.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_omnicatalogue;

use advanced_testcase;

/**
 * Tests for catalogue::get_facets() and catalogue::get_courses().
 *
 * @covers \local_omnicatalogue\catalogue
 */
final class catalogue_test extends advanced_testcase {
    /** @var int */
    private int $fieldid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $category  = $generator->create_category(['component' => 'core_course', 'area' => 'course']);
        $field     = $generator->create_field([
            'categoryid' => $category->get('id'),
            'type'       => 'omniselect',
            'shortname'  => 'region',
            'name'       => 'Region',
            'configdata' => json_encode(['options' => "North\nSouth\nEast\nWest"]),
        ]);
        $this->fieldid = $field->get('id');

        // Enable this field as a filter.
        set_config('filterfield_' . $this->fieldid, 1, 'local_omnicatalogue');
    }

    /**
     * get_facets() returns only values that have been used on at least one course.
     */
    public function test_get_facets_returns_used_values(): void {
        $this->create_course_with_values(['North', 'East']);
        $this->create_course_with_values(['North']);

        $facets = catalogue::get_facets();

        $this->assertCount(1, $facets);
        $values = array_column($facets[0]['values'], 'value');
        $this->assertContains('North', $values);
        $this->assertContains('East', $values);
        $this->assertNotContains('South', $values);
    }

    /**
     * Counts in facets reflect how many courses carry each value.
     */
    public function test_get_facets_counts_correctly(): void {
        $this->create_course_with_values(['North', 'East']);
        $this->create_course_with_values(['North']);

        $facets  = catalogue::get_facets();
        $byvalue = array_column($facets[0]['values'], null, 'value');

        $this->assertSame(2, $byvalue['North']['count']);
        $this->assertSame(1, $byvalue['East']['count']);
    }

    /**
     * Active filter values are marked selected in the returned facets.
     */
    public function test_get_facets_marks_active_filters_selected(): void {
        $this->create_course_with_values(['North']);

        $facets  = catalogue::get_facets([$this->fieldid => ['North']]);
        $byvalue = array_column($facets[0]['values'], null, 'value');

        $this->assertTrue($byvalue['North']['selected']);
    }

    /**
     * Hidden courses are excluded from facet counts.
     */
    public function test_get_facets_excludes_hidden_courses(): void {
        global $DB;

        $course = $this->create_course_with_values(['West']);
        $DB->set_field('course', 'visible', 0, ['id' => $course->id]);

        $facets = catalogue::get_facets();

        $this->assertEmpty($facets);
    }

    /**
     * With no filters, all visible non-site courses are returned.
     */
    public function test_get_courses_returns_all_visible_courses(): void {
        $this->create_course_with_values(['North']);
        $this->create_course_with_values(['South']);

        $result = catalogue::get_courses();

        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['courses']);
    }

    /**
     * A single-facet filter returns only matching courses.
     */
    public function test_get_courses_filters_by_single_facet(): void {
        $c1 = $this->create_course_with_values(['North']);
        $c2 = $this->create_course_with_values(['South']);

        $result = catalogue::get_courses([$this->fieldid => ['North']]);

        $this->assertSame(1, $result['total']);
        $this->assertSame($c1->id, $result['courses'][0]->id);
    }

    /**
     * Multiple values within the same facet use OR logic (either value matches).
     */
    public function test_get_courses_or_within_facet(): void {
        $c1 = $this->create_course_with_values(['North']);
        $c2 = $this->create_course_with_values(['South']);
        $c3 = $this->create_course_with_values(['East']);

        $result = catalogue::get_courses([$this->fieldid => ['North', 'South']]);

        $this->assertSame(2, $result['total']);
        $ids = array_column($result['courses'], 'id');
        $this->assertContains($c1->id, $ids);
        $this->assertContains($c2->id, $ids);
        $this->assertNotContains($c3->id, $ids);
    }

    /**
     * Pagination limits rows returned while total remains accurate.
     */
    public function test_get_courses_pagination(): void {
        $this->create_course_with_values(['North']);
        $this->create_course_with_values(['North']);
        $this->create_course_with_values(['North']);

        $result = catalogue::get_courses([], 0, 2);

        $this->assertSame(3, $result['total']);
        $this->assertCount(2, $result['courses']);
    }

    /**
     * Hidden courses are excluded from results.
     */
    public function test_get_courses_excludes_hidden_courses(): void {
        global $DB;

        $visible = $this->create_course_with_values(['North']);
        $hidden  = $this->create_course_with_values(['North']);
        $DB->set_field('course', 'visible', 0, ['id' => $hidden->id]);

        $result = catalogue::get_courses();

        $this->assertSame(1, $result['total']);
        $this->assertSame($visible->id, $result['courses'][0]->id);
    }

    /**
     * Creates a visible course and inserts omniselect selections for it.
     *
     * Each label string is looked up in customfield_omniselect_opts to find its
     * stable option ID. Vals rows now store optionid (int), not the string value.
     *
     * @param string[] $values Option label strings (must match options defined in setUp).
     * @return \stdClass The created course record.
     */
    private function create_course_with_values(array $values): \stdClass {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['visible' => 1]);
        foreach ($values as $value) {
            $opt = $DB->get_record('customfield_omniselect_opts', [
                'fieldid' => $this->fieldid,
                'value'   => $value,
            ]);
            if ($opt) {
                $DB->insert_record('customfield_omniselect_vals', (object)[
                    'fieldid'    => $this->fieldid,
                    'instanceid' => $course->id,
                    'optionid'   => (int)$opt->id,
                ]);
            }
        }
        return $course;
    }
}
