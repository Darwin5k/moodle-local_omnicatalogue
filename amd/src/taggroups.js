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
 * AMD module for the tag group management page.
 *
 * Attaches a click handler to every delete link that carries a
 * data-confirm attribute. The confirmation text is supplied by PHP
 * (already translated and escaped) so no additional string loading
 * is needed here.
 *
 * @module     local_omnicatalogue/taggroups
 * @copyright  2026 Robert Bellamy <darwin5k@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/notification'], function(Notification) {

    'use strict';

    return {

        /**
         * Initialises delete-confirmation behaviour on the tag groups list page.
         *
         * Called from taggroups.php via $PAGE->requires->js_call_amd().
         * Uses Moodle's Notification.confirm() rather than the native browser
         * confirm() dialog so the experience is consistent with Moodle's UI.
         */
        init: function() {
            document.querySelectorAll('a[data-confirm]').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    var deleteUrl = this.href;
                    var confirmText = this.dataset.confirm;
                    Notification.confirm(
                        '',
                        confirmText,
                        function() {
                            window.location.href = deleteUrl;
                        },
                        null
                    );
                });
            });
        },
    };
});
