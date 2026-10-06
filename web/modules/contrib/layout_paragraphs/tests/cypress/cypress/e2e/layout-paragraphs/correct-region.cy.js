Cypress.config('defaultCommandTimeout', 10000);

/**
 * Tests that a component is placed in the correct region after keyboard
 * reordering moves it between layout regions.
 *
 * @see https://www.drupal.org/project/layout_paragraphs/issues/3281169
 */
describe('Layout Paragraphs Correct Region Tests', () => {
  before(() => {
    cy.drush(
      'en layout_paragraphs_setup_test layout_paragraphs_correct_region_test',
    );
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
    cy.drush('cr');
    cy.viewport(1000, 800);
  });

  after(() => {
    cy.drush('pmu layout_paragraphs_correct_region_test');
    cy.drush('cr');
  });

  beforeEach(() => {
    cy.loginUserByUid(1);
  });

  it('places a new component in the correct region after keyboard reorder', () => {
    cy.lpCreateTestPage('Correct Region Test');

    // Add a three-column section.
    cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');

    // Add one item per column.
    cy.lpAddTextComponent(
      'First item',
      '.layout__region--first .lpb-btn--add',
    );
    cy.lpAddTextComponent(
      'Second item',
      '.layout__region--second .lpb-btn--add',
    );
    cy.lpAddTextComponent(
      'Third item',
      '.layout__region--third .lpb-btn--add',
    );

    // Click the drag button on the "Third item" to enter keyboard-drag mode.
    cy.lpFindComponent('Third item');
    cy.get('.lpb-drag:visible').click();

    // ArrowUp moves the item through positions:
    //   third-region → bottom of second-region
    cy.realPress('ArrowUp');
    cy.get('.ajax-progress').should('not.exist');
    //   → top of second-region
    cy.realPress('ArrowUp');
    cy.get('.ajax-progress').should('not.exist');
    //   → bottom of first-region
    cy.realPress('ArrowUp');
    cy.get('.ajax-progress').should('not.exist');
    //   → top of first-region
    cy.realPress('ArrowUp');
    cy.get('.ajax-progress').should('not.exist');

    // Confirm placement.
    cy.realPress('Enter');
    cy.get('.ajax-progress').should('not.exist');

    // Now add a "Fourth item" to the first region.  After keyboard navigation
    // the controls overlay may cover the add button, so use { force: true } to
    // bypass the coverage check — equivalent to the PHP test's forceHidden().
    // @see https://www.drupal.org/project/layout_paragraphs/issues/3281169
    cy.get('.layout__region--first .lpb-btn--add').first().click({ force: true });
    cy.get('.lpb-component-list a').contains('Text').click();
    cy.lpSetCKEditor5Value('field_lp_test_text', 'Fourth item');
    cy.get('button.lpb-btn--save:visible').click();
    cy.contains('Fourth item').should('be.visible');

    // The order on the page should be: Fourth, Third, First, Second.
    cy.get('body').then(($body) => {
      const html = $body.html();
      const fourthIdx = html.indexOf('Fourth item');
      const thirdIdx = html.indexOf('Third item');
      const firstIdx = html.indexOf('First item');
      const secondIdx = html.indexOf('Second item');

      expect(fourthIdx).to.be.lessThan(thirdIdx);
      expect(thirdIdx).to.be.lessThan(firstIdx);
      expect(firstIdx).to.be.lessThan(secondIdx);
    });

    // Save and verify the order is preserved.
    cy.lpSavePage();

    cy.get('body').then(($body) => {
      const html = $body.html();
      const fourthIdx = html.indexOf('Fourth item');
      const thirdIdx = html.indexOf('Third item');
      const firstIdx = html.indexOf('First item');
      const secondIdx = html.indexOf('Second item');

      expect(fourthIdx).to.be.lessThan(thirdIdx);
      expect(thirdIdx).to.be.lessThan(firstIdx);
      expect(firstIdx).to.be.lessThan(secondIdx);
    });
  });
});
