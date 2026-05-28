# Changelog — local_omnicatalogue

## 1.3.2 (2026052702)
- Added a catalogue URL info box to the settings page showing the catalogue
  link and instructions for adding it to site navigation via Custom menu items.

## 1.3.1 (2026052701)
- Fixed automated precheck errors: removed CSS `!important`, flattened
  promise chain in catalogue.js, replaced `window.confirm()` with Moodle's
  `Notification.confirm()` in taggroups.js, fixed no-multi-spaces warnings,
  added example contexts to all six Mustache templates, rebuilt AMD build files.

## 1.3.0 (2026051503)
- Added tag group facets: administrators can define named groups of Moodle
  course tags and expose each group as a filter in the catalogue sidebar.
- Added a Manage tag groups page (`/local/omnicatalogue/taggroups.php`) for
  creating, editing, and deleting tag groups.
- Added a toggle in plugin settings to enable/disable tag group facets.
- Added an admin bar that appears when Moodle's edit mode is active, with
  direct links to Catalogue settings and Manage tag groups.
- Added category and enrolment-type facets (each controlled by a settings toggle).
- Pagination Previous/Next labels are now translatable lang strings.

## 1.2.0 (2026051401)
- Compatibility update for customfield_omniselect 1.0.1 (opts table redesign).
- Updated catalogue queries to join the new `customfield_omniselect_opts` table.

## 1.1.0
- Added configurable card display options (image, summary, category, contacts,
  enrolment type, enrolment status).
- Added "Enrolled" and "Completed" badges on course cards.
- Facets now preserve panel open/closed state across AJAX re-renders.

## 1.0.0
- Initial release.
- Course catalogue page with omniselect custom field filter sidebar.
- AJAX dynamic filtering — filter changes update the grid without a page reload.
- Bookmarkable URLs and browser history support (pushState).
- Configurable courses-per-page setting.
- PHPUnit and Behat test coverage.
