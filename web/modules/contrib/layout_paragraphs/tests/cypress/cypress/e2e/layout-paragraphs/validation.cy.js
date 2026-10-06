Cypress.config('defaultCommandTimeout', 10000);

/**
 * Tests that a paragraph-level validation constraint surfaces an error
 * message inside the Layout Paragraphs Builder dialog and prevents the
 * dialog from closing prematurely.
 *
 * The layout_paragraphs_entity_validator_test module adds a constraint to ALL
 * paragraph entities that always fails with the message
 * "Failed Layout Paragraphs test validation."
 */
describe('Layout Paragraphs Validation Constraint Tests', () => {
  before(() => {
    cy.drush(
      'en layout_paragraphs_setup_test layout_paragraphs_entity_validator_test',
    );
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
    cy.drush('cr');
    cy.viewport(1000, 800);
  });

  after(() => {
    cy.drush('pmu layout_paragraphs_entity_validator_test');
    cy.drush('cr');
  });

  beforeEach(() => {
    cy.loginUserByUid(1);
  });

  it('shows validation errors and keeps the form open on constraint failure', () => {
    cy.visit('/node/add/lp_test_ct');
    cy.get('input[name="title[0][value]"]').type('Validation Test');

    // Open the component chooser.
    cy.get('.lpb-btn--add').first().click();
    cy.get('.ui-dialog-title').should('contain', 'Choose a component');

    // Choose the Text component.
    cy.get('.type-lp_test_text a').contains('Text').click();
    cy.get('.ajax-progress').should('not.exist');

    // Type some text.
    cy.get('.ck-editor__editable[contenteditable="true"]')
      .should('be.visible')
      .click()
      .type('Test text');

    // Attempt to save the component — the constraint validator always fails.
    cy.get('.lpb-btn--save:visible').click();
    cy.get('.ajax-progress').should('not.exist');

    // The error message from the constraint must appear.
    cy.contains('Failed Layout Paragraphs test validation.').should('exist');

    // The component form must still be open (it should not have been dismissed).
    cy.get('form.layout-paragraphs-component-form').should('exist');
  });
});
