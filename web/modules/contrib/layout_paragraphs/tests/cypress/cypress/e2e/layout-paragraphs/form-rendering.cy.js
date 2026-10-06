Cypress.config('defaultCommandTimeout', 10000);

/**
 * Tests that forms embedded inside paragraph view output render correctly
 * within the Layout Paragraphs Builder without causing JS or form conflicts.
 *
 * @see https://www.drupal.org/project/layout_paragraphs/issues/3263715
 */
describe('Layout Paragraphs Form Rendering Tests', () => {
  before(() => {
    cy.drush(
      'en layout_paragraphs_setup_test layout_paragraphs_form_rendering_test',
    );
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
    cy.drush('cr');
    cy.viewport(1000, 800);
  });

  after(() => {
    cy.drush('pmu layout_paragraphs_form_rendering_test');
    cy.drush('cr');
  });

  beforeEach(() => {
    cy.loginUserByUid(1);
  });

  it('renders an embedded form inside a paragraph without errors', () => {
    cy.lpCreateTestPage('Form Rendering Test');

    // Add a one-column section component.  The test module injects a
    // "Test field" form element into every paragraph view, so it should
    // appear inside the builder preview as soon as the section is added.
    cy.lpAddSection('layout_onecol', '.lpb-btn--add');

    // "Test field" should be visible inside the builder preview.
    cy.contains('Test field').should('be.visible');

    // Save the node and verify the form element is present on the view page.
    cy.lpSavePage();

    cy.contains('Form Rendering Test').should('be.visible');
    cy.contains('Test field').should('be.visible');
  });
});
