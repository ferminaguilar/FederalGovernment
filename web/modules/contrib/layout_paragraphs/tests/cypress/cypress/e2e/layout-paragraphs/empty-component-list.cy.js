Cypress.config('defaultCommandTimeout', 10000);

describe('Layout Paragraphs Empty Component List Tests', () => {
  before(() => {
    cy.drush(
      'en layout_paragraphs_setup_test layout_paragraphs_empty_component_list_test',
    );
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
    // Ensure empty_message is null so the controller's ?? fallback returns the
    // default "No components to add." text (an empty string '' bypasses ??).
    cy.drush('config:delete', ['layout_paragraphs.settings', 'empty_message']);
    cy.drush('cr');
    cy.viewport(1000, 800);
  });

  after(() => {
    cy.drush('pmu layout_paragraphs_empty_component_list_test');
    cy.drush('cr');
  });

  beforeEach(() => {
    cy.loginUserByUid(1);
  });

  it('shows the default empty message when no components are available', () => {
    cy.visit('/node/add/lp_test_ct');
    cy.get('input[name="title[0][value]"]').type('Empty Component List Test');

    // Click the "+" button — the component list should be empty.
    cy.get('.lpb-btn--add').first().click();
    cy.get('.ajax-progress').should('not.exist');

    // The default "No components to add." message should appear.
    cy.contains('No components to add.').should('exist');
  });

  it('shows a custom empty message when configured', () => {
    const customMessage = 'Custom empty message';

    // Set the custom message via the admin UI.
    cy.visit('/admin/config/content/layout_paragraphs/labels');
    cy.get('[name="empty_message"]').clear().type(customMessage);
    cy.get('input[value="Save configuration"]').click();

    cy.visit('/node/add/lp_test_ct');
    cy.get('input[name="title[0][value]"]').type('Custom Empty Message Test');

    // Click the "+" button.
    cy.get('.lpb-btn--add').first().click();
    cy.get('.ajax-progress').should('not.exist');

    // The custom message should appear instead of the default.
    cy.contains(customMessage).should('exist');

    // Restore the default empty message (delete the config key so the
    // controller's ?? fallback fires on the next run).
    cy.drush('config:delete', ['layout_paragraphs.settings', 'empty_message']);
  });
});
