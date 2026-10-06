Cypress.config('defaultCommandTimeout', 10000);

/**
 * Tests that dialog action buttons remain reachable when a component form
 * causes the modal to grow taller than the viewport.
 */
describe('Layout Paragraphs Modal Height Tests', () => {
  before(() => {
    cy.drush('en layout_paragraphs_setup_test');
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
    cy.drush('cr');
    cy.viewport(1000, 800);
  });

  beforeEach(() => {
    cy.loginUserByUid(1);
  });

  it('keeps the save button reachable when the form is taller than the viewport', () => {
    cy.visit('/node/add/lp_test_ct');
    cy.get('input[name="title[0][value]"]').type('Modal Height Test');

    // Open the component chooser.
    cy.get('.lpb-btn--add').first().click();
    cy.get('.ui-dialog-title').should('contain', 'Choose a component');

    // Choose the "Text" component to open its creation form.
    cy.get('.type-lp_test_text a').contains('Text').click();
    cy.get('.ui-dialog-title').should('contain', 'Create new Text');
    cy.get('.ajax-progress').should('not.exist');

    // Artificially expand the form height far beyond the viewport.
    cy.get('.layout-paragraphs-component-form').then(($form) => {
      $form[0].style.height = '2000px';
    });

    // Brief pause to let the dialog react to the size change.
    cy.wait(500);

    // The save button in the dialog footer must still be visible in the
    // viewport (the dialog should scroll or remain fixed at the bottom).
    cy.get('.ui-dialog-buttonpane .lpb-btn--save').should('be.visible');
  });
});
