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
 * Library functions for local_omnicatalogue.
 *
 * @package    local_omnicatalogue
 * @copyright  2026 Your Name <you@example.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Adds a Course Catalogue link to the global navigation for eligible users.
 *
 * @param \global_navigation $nav
 */
function local_omnicatalogue_extend_navigation(\global_navigation $nav): void {
    if (!isloggedin() || isguestuser()) {
        return;
    }
    if (!has_capability('local/omnicatalogue:view', context_system::instance())) {
        return;
    }
    $node = $nav->add(
        get_string('catalogue', 'local_omnicatalogue'),
        new moodle_url('/local/omnicatalogue/index.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'omnicatalogue',
        new pix_icon('i/course', '')
    );
    $node->showinflatnavigation = true;
}
