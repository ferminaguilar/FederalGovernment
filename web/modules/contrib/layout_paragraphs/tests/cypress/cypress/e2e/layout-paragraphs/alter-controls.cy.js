Cypress.config('defaultCommandTimeout', 10000);

describe('Layout Paragraphs Alter Controls Tests', () => {
  before(() => {
    cy.drush('en layout_paragraphs_setup_test layout_paragraphs_alter_controls_test');
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
    cy.drush('cr');
    cy.viewport(1000, 800);
  });

  after(() => {
    cy.drush('pmu layout_paragraphs_alter_controls_test');
    cy.drush('cr');
  });

  beforeEach(() => {
    cy.loginUserByUid(1);
  });

  it('alters the controls UI element', () => {
    cy.lpCreateTestPage('Test Alter Controls');

    // Add a three-column section.
    cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');

    // Activate the section (it's the first component).
    cy.lpFindComponent(1);

    // The drag button should still exist.
    cy.get('.lpb-drag').should('exist');

    // The custom test element should have been added.
    cy.get('.lpb-alter-controls-test-element').should('exist');

    // The edit link should NOT exist (it was hidden by the alter hook).
    cy.get('.lpb-edit').should('not.exist');

    // The delete link should NOT exist (it was removed by the alter hook).
    cy.get('.lpb-delete').should('not.exist');

    // The custom span should contain text.
    cy.get('span.lpb-alter-controls-test-element')
      .invoke('text')
      .should('not.be.empty');
  });
});
