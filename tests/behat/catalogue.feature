@local_omnicatalogue @javascript
Feature: Course catalogue with faceted filtering
  As an authenticated user
  I want to browse and filter the course catalogue
  So I can discover courses that match my criteria

  Background:
    Given the following "custom field category" exist:
      | name     | component   | area   |
      | Taxonomy | core_course | course |
    And the following "custom fields" exist:
      | name   | category | type        | shortname | configdata                               |
      | Region | Taxonomy | omniselect  | region    | {"options":"North\nSouth\nEast\nWest"}   |
    And the following "courses" exist:
      | fullname      | shortname | visible |
      | Northern Arts | NARTS     | 1       |
      | Southern Tech | STECH     | 1       |
      | East History  | EHIST     | 1       |
    And the following config values are set as admin:
      | filterfield_{region_field_id} | 1 | local_omnicatalogue |
    And I log in as "admin"

  @local_omnicatalogue_view
  Scenario: Authenticated user can view the course catalogue
    When I navigate to "/local/omnicatalogue/index.php"
    Then I should see "Course Catalogue"
    And I should see "Northern Arts"
    And I should see "Southern Tech"
    And I should see "East History"

  @local_omnicatalogue_filter
  Scenario: Filtering by a single facet value narrows results
    Given course "NARTS" has omniselect field "region" set to "North"
    And course "STECH" has omniselect field "region" set to "South"
    And course "EHIST" has omniselect field "region" set to "East"
    When I navigate to "/local/omnicatalogue/index.php"
    And I click on "North" "checkbox"
    And I press "Apply filters"
    Then I should see "Northern Arts"
    And I should not see "Southern Tech"
    And I should not see "East History"

  @local_omnicatalogue_filter_or
  Scenario: Selecting multiple values in one facet uses OR logic
    Given course "NARTS" has omniselect field "region" set to "North"
    And course "STECH" has omniselect field "region" set to "South"
    And course "EHIST" has omniselect field "region" set to "East"
    When I navigate to "/local/omnicatalogue/index.php"
    And I click on "North" "checkbox"
    And I click on "South" "checkbox"
    And I press "Apply filters"
    Then I should see "Northern Arts"
    And I should see "Southern Tech"
    And I should not see "East History"

  @local_omnicatalogue_clear
  Scenario: Clearing filters restores all courses
    Given course "NARTS" has omniselect field "region" set to "North"
    And course "STECH" has omniselect field "region" set to "South"
    When I navigate to "/local/omnicatalogue/index.php"
    And I click on "North" "checkbox"
    And I press "Apply filters"
    And I should not see "Southern Tech"
    When I click on "Clear filters" "link"
    Then I should see "Northern Arts"
    And I should see "Southern Tech"

  @local_omnicatalogue_guest
  Scenario: Guests cannot access the catalogue
    Given I log out
    When I navigate to "/local/omnicatalogue/index.php"
    Then I should see "You need to log in"
