Cypress.config('defaultCommandTimeout', 10000);

/**
 * Tests that the LayoutParagraphsSection object is available inside
 * hook_preprocess_layout(), allowing themes to access section paragraph data.
 *
 * @see https://www.drupal.org/project/layout_paragraphs/issues/3296245
 */
describe('Layout Paragraphs Preprocess Layout Tests', () => {
  before(() => {
    cy.drush(
      'en layout_paragraphs_setup_test layout_paragraphs_preprocess_layout_test',
    );
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
    cy.drush('cr');
    cy.viewport(1000, 800);
  });

  after(() => {
    cy.drush('pmu layout_paragraphs_preprocess_layout_test');
    cy.drush('cr');
  });

  beforeEach(() => {
    cy.loginUserByUid(1);
  });

  it('exposes the section paragraph bundle name via preprocess', () => {
    cy.lpCreateTestPage('Preprocess Layout Test');

    // Add a three-column section.  The test module's preprocess hook prepends
    // "bundle:<bundle_name>" to the first region content.
    cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');

    cy.get('.layout__region--first').should('exist');
    cy.get('.layout__region--second').should('exist');
    cy.get('.layout__region--third').should('exist');

    // Save the node.
    cy.lpSavePage();

    // After saving, the preprocess-injected bundle name should be visible.
    // The lp_test_section bundle name is "lp_test_section".
    cy.contains('bundle:lp_test_section').should('exist');
  });
});
