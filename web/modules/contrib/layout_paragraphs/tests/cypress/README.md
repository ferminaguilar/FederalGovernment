# Layout Paragraphs Cypress Tests

This directory contains Cypress end-to-end tests for the Layout Paragraphs module, converted from the original WebDriverTestBase PHP tests.

## Setup

1. Install Node.js dependencies:
   ```bash
   cd web/modules/contrib/layout_paragraphs/tests/cypress
   npm install
   ```

2. Ensure the layout_paragraphs_setup_test module is available and can be enabled.

3. Make sure your Drupal site is running and accessible at the URL configured in `cypress.config.js`.

## Running Tests

### Interactive Mode (Cypress GUI)
```bash
npm run cy:open
```

### Headless Mode
```bash
npm run cy:run
```

## Test Structure

The tests are organized into the following files:

- **builder.cy.js** - Core Layout Paragraphs builder functionality (converted from BuilderTest.php)
- **nested-sections.cy.js** - Nested section testing (converted from NestedSectionsTest.php)
- **cardinality.cy.js** - Field cardinality testing (converted from CardinalityTest.php)

## Custom Commands

The tests use custom Cypress commands defined in `cypress/support/commands.js`:

- `cy.lpCreateTestPage(title, contentType)` - Creates a new page for testing
- `cy.lpAddSection(layoutChoice, selector)` - Adds a section with specified layout
- `cy.lpAddTextComponent(text, selector)` - Adds a text component
- `cy.lpSavePage()` - Saves the current page
- And many more...

## Test Setup Module

The tests depend on the `layout_paragraphs_setup_test` module which:

- Creates test paragraph types (lp_test_section, lp_test_text)
- Creates test content types with layout paragraphs fields
- Provides helper functions for test setup
- Handles all the configuration that was previously in BuilderTestBase

## Configuration

The Cypress configuration is in `cypress.config.js` and includes:

- Base URL for the test site
- Drush command configuration for test setup
- Video and screenshot settings
- Test file patterns

## Converting Additional Tests

To convert more PHP WebDriverTestBase tests to Cypress:

1. Examine the PHP test file to understand the test logic
2. Create a new `.cy.js` file in the appropriate subdirectory
3. Use the existing custom commands or create new ones as needed
4. Follow the existing patterns for test structure and assertions
5. Ensure proper test isolation and cleanup

## Benefits of Cypress over WebDriverTestBase

- Faster test execution
- Better debugging with time-travel snapshots
- More reliable element selection and interaction
- Better CI/CD integration
- Real browser testing environment
- Better error reporting and screenshots
