Cypress.config('defaultCommandTimeout', 10000);

describe('Layout Paragraphs Duplicate Components Tests', () => {
  before(() => {
    cy.drush('en layout_paragraphs_setup_test');
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
    cy.drush('cr');
    cy.viewport(1000, 800);
  });

  beforeEach(() => {
    cy.loginUserByUid(1);
  });

  it('duplicates a simple text component inside a section', () => {
    cy.lpCreateTestPage('Duplicate Component Test');

    // Add a one-column section and a text component.
    cy.lpAddSection('layout_onecol', '.lpb-btn--add');
    cy.lpAddTextComponent(
      'Text component.',
      '.layout__region--content .lpb-btn--add',
    );

    // Activate the component and click its duplicate button.
    cy.lpFindComponent('Text component.');
    cy.get('.lpb-duplicate:visible').click();
    cy.get('.ajax-progress').should('not.exist');

    // Save the node.
    cy.lpSavePage();

    // "Text component." should appear exactly twice in the page HTML.
    cy.get('body').then(($body) => {
      const html = $body.html();
      const count = (html.match(/Text component\./g) || []).length;
      expect(count).to.be.at.least(2);
    });
  });

  it('duplicates a section and all of its nested components', () => {
    cy.lpCreateTestPage('Duplicate Section Test');

    // Add a three-column section.
    cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');

    // Add a component to the first column.
    cy.lpAddTextComponent(
      'Component in first column.',
      '.layout__region--first .lpb-btn--add',
    );

    // The second-column add button may be covered by the region overlay after
    // the first component was added, so use { force: true }.
    cy.get('.layout__region--second .lpb-btn--add').first().click({ force: true });
    cy.get('.lpb-component-list a').contains('Text').click();
    cy.lpSetCKEditor5Value('field_lp_test_text', 'Component in second column.');
    cy.get('button.lpb-btn--save:visible').click();
    cy.contains('Component in second column.').should('be.visible');
    cy.get('.ajax-progress').should('not.exist');

    // Activate the section itself (first component, index 1) and duplicate it.
    cy.lpFindComponent(1);
    cy.get('.lpb-duplicate:visible').click();
    cy.get('.ajax-progress').should('not.exist');

    // Save the node.
    cy.lpSavePage();

    // Both nested components should appear twice (once per section copy).
    cy.get('body').then(($body) => {
      const html = $body.html();
      const firstCount = (
        html.match(/Component in first column\./g) || []
      ).length;
      const secondCount = (
        html.match(/Component in second column\./g) || []
      ).length;
      expect(firstCount).to.be.at.least(2);
      expect(secondCount).to.be.at.least(2);
    });
  });
});
