# local_omnicatalogue

A faceted course catalogue for Moodle. Learners can browse and discover courses
using filter sidebars driven by `customfield_omniselect` fields — one
collapsible accordion panel per field, with live counts.

## Features

- Filterable course grid with Bootstrap 5 cards (image, category, title, summary).
- Filter sidebar with checkbox facets and counts, driven by custom fields.
- AND logic between facets, OR logic within a facet.
- Configurable courses-per-page setting.
- Per-field toggles to show or hide each field in the filter sidebar and on cards.
- Pagination with active filters preserved.
- Cross-database: PostgreSQL and MySQL compatible.

## Requirements

- Moodle 5.1 or later (requires Moodle 2025092600+)
- `customfield_omniselect` 2026051401+

## Installation

1. Copy this folder to `<moodleroot>/public/local/omnicatalogue/`.
2. Install `customfield_omniselect` first (see its README).
3. Log in as admin and navigate to **Site administration → Notifications**.
4. Click **Upgrade Moodle database now**.

## Configuration

Go to **Site administration → Plugins → Local plugins → Catalogue settings**:

| Setting | Description |
|---------|-------------|
| Courses per page | Number of cards per page (default: 20). |
| Show _field_ as a filter | Display this omniselect field in the filter sidebar. |
| Show _field_ on course cards | Display selected values on each course card. |

The settings list updates automatically as you add or remove omniselect fields.

## Capability

`local/omnicatalogue:view` — assigned to authenticated users by default; guests
are prevented.

## License

GNU GPL v3 or later — https://www.gnu.org/copyleft/gpl.html
