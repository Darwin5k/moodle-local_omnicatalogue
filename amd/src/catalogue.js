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
 * AMD module for the course catalogue AJAX interactions.
 *
 * Intercepts the filter form submission and pagination clicks, calls the
 * local_omnicatalogue_get_catalogue web service, and re-renders the facet
 * sidebar and course grid in place without a full page reload.
 *
 * Filter checkboxes use the name format f[facetkey][] where facetkey follows
 * the scheme: cf_{id} (custom field), cat (category), et (enrolment type),
 * tg_{id} (tag group).
 *
 * @module     local_omnicatalogue/catalogue
 * @copyright  2026 Your Name <you@example.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'core/ajax',
    'core/templates',
    'core/notification',
], function(Ajax, Templates, Notification) {

    'use strict';

    /** Module-level config set during init(). */
    var Config = {
        baseUrl:     '',
        perpage:     20,
        nocoursestr: '',
        page:        0,
    };

    // Helpers.

    /**
     * Reads all checked filter checkboxes from the form and returns them as
     * the [{facetkey, values}] array the web service expects.
     *
     * Checkbox names follow the pattern f[facetkey][] where facetkey is an
     * alphanumeric-plus-underscore string (e.g. cf_3, cat, et, tg_7).
     *
     * @param  {HTMLFormElement} form
     * @return {Array}
     */
    var collectFilters = function(form) {
        var filters = {};
        form.querySelectorAll('input[type="checkbox"]:checked').forEach(function(cb) {
            var match = cb.name.match(/^f\[([a-zA-Z0-9_]+)\]\[\]$/);
            if (match) {
                var fkey = match[1];
                if (!filters[fkey]) {
                    filters[fkey] = [];
                }
                filters[fkey].push(cb.value);
            }
        });
        return Object.keys(filters).map(function(fkey) {
            return {facetkey: fkey, values: filters[fkey]};
        });
    };

    /**
     * Builds a bookmarkable catalogue URL from the given filter state and page.
     * Uses the f[facetkey][] query-string format PHP expects.
     *
     * @param  {Array}  filters [{facetkey, values}]
     * @param  {number} page
     * @return {string}
     */
    var buildUrl = function(filters, page) {
        var parts = ['page=' + page];
        filters.forEach(function(f) {
            f.values.forEach(function(v) {
                parts.push('f[' + f.facetkey + '][]=' + encodeURIComponent(v));
            });
        });
        return Config.baseUrl + (parts.length ? '?' + parts.join('&') : '');
    };

    /**
     * Toggles the loading overlay on the course grid.
     *
     * @param {boolean} loading
     */
    var setLoading = function(loading) {
        var el = document.getElementById('omnicatalogue-loading');
        if (el) {
            el.classList.toggle('d-none', !loading);
        }
    };

    // Core fetch-and-render cycle.

    /**
     * Calls the external function for the given filter state and page, then
     * re-renders the facets sidebar and course grid in place.
     *
     * @param {HTMLFormElement} form        The filter form element.
     * @param {Array}           filters     [{facetkey, values}]
     * @param {number}          page        Zero-based page number.
     * @param {boolean}         pushState   Whether to push a new browser history entry.
     */
    var fetchAndRender = function(form, filters, page, pushState) {
        setLoading(true);

        Ajax.call([{
            methodname: 'local_omnicatalogue_get_catalogue',
            args: {filters: filters, page: page},
        }])[0].then(function(data) {

            // Update result-count text.
            var countEl = document.getElementById('omnicatalogue-resultcount');
            if (countEl) {
                countEl.textContent = data.resultstring;
            }

            // Show or hide the "Clear filters" link.
            var clearLink = document.getElementById('omnicatalogue-clear-link');
            if (clearLink) {
                clearLink.classList.toggle('d-none', !data.hasfilters);
            }

            // Snapshot which facet panels are currently expanded so we can
            // restore that state after the re-render.  Without this, a panel
            // the user was working in collapses when they clear its last value.
            var openFacetIds = [];
            var facetsEl = document.getElementById('omnicatalogue-facets');
            if (facetsEl) {
                facetsEl.querySelectorAll('.collapse.show[id]').forEach(function(el) {
                    openFacetIds.push(el.id);
                });
            }

            // Re-render the facet list.
            return Templates.renderForPromise(
                'local_omnicatalogue/catalogue_facets',
                {facets: data.facets}
            ).then(function(facetResult) {
                if (facetsEl) {
                    Templates.replaceNodeContents(facetsEl, facetResult.html, facetResult.js);

                    // Re-open any panel that was expanded before the re-render,
                    // regardless of whether it now has active filters.
                    openFacetIds.forEach(function(id) {
                        var panel = document.getElementById(id);
                        if (panel) {
                            panel.classList.add('show');
                            // Keep the toggle button's aria-expanded in sync so
                            // the caret rotates correctly.
                            var btn = facetsEl.querySelector('[data-bs-target="#' + id + '"]');
                            if (btn) {
                                btn.setAttribute('aria-expanded', 'true');
                            }
                        }
                    });
                }

                // Re-render the course grid + pagination.
                return Templates.renderForPromise(
                    'local_omnicatalogue/catalogue_courses',
                    {
                        courses:     data.courses,
                        nocourses:   data.nocourses,
                        nocoursestr: Config.nocoursestr,
                        haspages:    data.haspages,
                        hasprev:     data.hasprev,
                        hasnext:     data.hasnext,
                        prevpage:    data.prevpage,
                        nextpage:    data.nextpage,
                    }
                );
            }).then(function(coursesResult) {
                var coursesEl = document.getElementById('omnicatalogue-courses-inner');
                if (coursesEl) {
                    Templates.replaceNodeContents(coursesEl, coursesResult.html, coursesResult.js);
                }

                // Push a new browser-history entry so the URL stays bookmarkable
                // and back/forward navigation works.
                if (pushState) {
                    history.pushState(
                        {omnicatalogue: true, filters: filters, page: page},
                        '',
                        buildUrl(filters, page)
                    );
                }

                setLoading(false);
            });

        }).catch(function(err) {
            setLoading(false);
            Notification.exception(err);
        });
    };

    // Public API.

    return {

        /**
         * Initialises the catalogue AJAX behaviour.
         *
         * Called from index.php via $PAGE->requires->js_call_amd().
         *
         * @param {Object} options
         * @param {string} options.baseUrl      Base URL of the catalogue page.
         * @param {number} options.perpage      Configured items per page.
         * @param {string} options.nocoursestr  Translated "no courses" string.
         * @param {number} options.page         Current page number (from PHP).
         */
        init: function(options) {
            if (options) {
                Config.baseUrl     = options.baseUrl     || '';
                Config.perpage     = options.perpage     || 20;
                Config.nocoursestr = options.nocoursestr || '';
                Config.page        = options.page        || 0;
            }

            var form = document.getElementById('omnicatalogue-filter-form');
            if (!form) {
                return;
            }

            // Intercept the Apply Filters button (form submit).
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                fetchAndRender(form, collectFilters(form), 0, true);
            });

            // Intercept the Clear Filters link so it also works via AJAX
            // and unchecks all checkboxes without a page reload.
            var clearLink = document.getElementById('omnicatalogue-clear-link');
            if (clearLink) {
                clearLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    form.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
                        cb.checked = false;
                    });
                    fetchAndRender(form, [], 0, true);
                });
            }

            // Intercept pagination button clicks (delegated because the courses
            // area is replaced on every AJAX response).
            document.addEventListener('click', function(e) {
                var btn = e.target.closest('[data-omnicatalogue-page]');
                if (btn) {
                    e.preventDefault();
                    var pg = parseInt(btn.getAttribute('data-omnicatalogue-page'), 10);
                    fetchAndRender(form, collectFilters(form), pg, true);
                    window.scrollTo({top: 0, behavior: 'smooth'});
                }
            });

            // Restore filter state on browser back/forward.
            window.addEventListener('popstate', function(e) {
                if (e.state && e.state.omnicatalogue) {
                    fetchAndRender(form, e.state.filters, e.state.page, false);
                }
            });

            // Record the initial page state so the very first back-press works.
            history.replaceState(
                {omnicatalogue: true, filters: collectFilters(form), page: Config.page},
                '',
                window.location.href
            );
        },
    };
});
