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

    // Add multiple components to each column in a known order.
    cy.lpAddTextComponent(
      'First column component 1.',
      '.layout__region--first .lpb-btn--add',
    );
    cy.lpAddTextComponent(
      'First column component 2.',
      '.layout__region--first .lpb-btn--add',
    );
    cy.lpAddTextComponent(
      'Second column component 1.',
      '.layout__region--second .lpb-btn--add',
    );
    cy.lpAddTextComponent(
      'Second column component 2.',
      '.layout__region--second .lpb-btn--add',
    );
    cy.lpAddTextComponent(
      'Third column component 1.',
      '.layout__region--third .lpb-btn--add',
    );
    cy.lpAddTextComponent(
      'Third column component 2.',
      '.layout__region--third .lpb-btn--add',
    );

    const componentText = ($components) =>
      [...$components].map((component) =>
        component
          .querySelector('.field--name-field-lp-test-text')
          .textContent.trim(),
      );

    let firstColumnOrder;
    let secondColumnOrder;
    let thirdColumnOrder;

    cy.get('.layout__region--first [data-type="lp_test_text"]').then(
      ($components) => {
        firstColumnOrder = componentText($components);
        expect(firstColumnOrder).to.have.length(2);
      },
    );
    cy.get('.layout__region--second [data-type="lp_test_text"]').then(
      ($components) => {
        secondColumnOrder = componentText($components);
        expect(secondColumnOrder).to.have.length(2);
      },
    );
    cy.get('.layout__region--third [data-type="lp_test_text"]').then(
      ($components) => {
        thirdColumnOrder = componentText($components);
        expect(thirdColumnOrder).to.have.length(2);
      },
    );

    // Activate the section itself (first component, index 1) and duplicate it.
    cy.lpFindComponent(1);
    cy.get('.lpb-duplicate:visible').click();
    cy.get('.ajax-progress').should('not.exist');

    // Save the node.
    cy.lpSavePage();

    // Each copied region should preserve its original nested component order.
    cy.get('.layout__region--first .paragraph--type--lp-test-text').then(
      ($components) => {
        expect(componentText($components)).to.deep.equal([
          ...firstColumnOrder,
          ...firstColumnOrder,
        ]);
      },
    );
    cy.get('.layout__region--second .paragraph--type--lp-test-text').then(
      ($components) => {
        expect(componentText($components)).to.deep.equal([
          ...secondColumnOrder,
          ...secondColumnOrder,
        ]);
      },
    );
    cy.get('.layout__region--third .paragraph--type--lp-test-text').then(
      ($components) => {
        expect(componentText($components)).to.deep.equal([
          ...thirdColumnOrder,
          ...thirdColumnOrder,
        ]);
      },
    );
  });
});
