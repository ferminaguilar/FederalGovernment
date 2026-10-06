Cypress.config('defaultCommandTimeout', 10000);

/**
 * Tests Layout Paragraphs Builder with altered paragraph templates.
 *
 * The layout_paragraphs_altered_template_test module replaces the default
 * paragraph template with one that only renders a single field and injects
 * "Custom template rendering [bundle] paragraph type." verifying the builder
 * works correctly even when the paragraph template doesn't render the full
 * content array.
 *
 * @see https://www.drupal.org/project/layout_paragraphs/issues/3244055
 */
describe('Layout Paragraphs Altered Templates Tests', () => {
  before(() => {
    cy.drush('en layout_paragraphs_setup_test layout_paragraphs_altered_template_test');
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
    cy.drush('cr');
    cy.viewport(1000, 800);
  });

  after(() => {
    cy.drush('pmu layout_paragraphs_altered_template_test');
    cy.drush('cr');
  });

  beforeEach(() => {
    cy.loginUserByUid(1);
  });

  it('adds components with an altered template', () => {
    cy.lpCreateTestPage('Altered Template Test');

    // Add a three-column section.
    cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');

    // The altered template renders `{{ content.field_text }}` which does NOT
    // exist on lp_test_text (which uses field_lp_test_text).  The typed text
    // will not appear in the builder preview, so we cannot use
    // cy.lpAddTextComponent() which asserts text visibility after save.
    // Instead we add the component manually and skip that assertion.
    cy.get('.layout__region--first .lpb-btn--add').first().click();
    cy.get('.lpb-component-list a').contains('Text').click();
    cy.lpSetCKEditor5Value('field_lp_test_text', 'First text');
    cy.get('button.lpb-btn--save:visible').click();
    cy.get('.ajax-progress').should('not.exist');

    cy.get('.layout__region--first .lpb-btn--add').first().click();
    cy.get('.lpb-component-list a').contains('Text').click();
    cy.lpSetCKEditor5Value('field_lp_test_text', 'Second text');
    cy.get('button.lpb-btn--save:visible').click();
    cy.get('.ajax-progress').should('not.exist');

    // Save the page.
    cy.lpSavePage();

    // Both text components should render the custom template diagnostic string.
    cy.get('body').then(($body) => {
      const count = (
        $body
          .html()
          .match(/Custom template rendering lp_test_text paragraph type\./g) || []
      ).length;
      expect(count).to.be.at.least(2);
    });
  });

  it('reorders components with an altered template', () => {
    cy.lpCreateTestPage('Altered Template Reorder Test');

    // Add a section and first text component (without text-visibility assertion).
    cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');
    cy.lpAddTextComponent('First text', '.layout__region--first .lpb-btn--add');

    // Save and capture the node view URL (new-node save has no destination param,
    // so Drupal redirects to the node view).
    let nodeViewUrl;
    cy.lpSavePage();
    cy.url().then((url) => { nodeViewUrl = url; });
    cy.visit('/admin/content');
    cy.get('a[aria-label="Edit Altered Template Reorder Test"]')
      .first()
      .scrollIntoView()
      .click();

    // Activate the first text component (index 2 = querySelectorAll index 1)
    // and reveal its add-after button.
    cy.lpFindComponent('First text');
    cy.get('[data-active="true"]').trigger('mouseover');

    // Add second text component using the add-after button.
    cy.lpAddTextComponent('Second text', '[data-type="lp_test_text"] .lpb-btn--add.after');

    // Move the second text component (lpFindComponent index 3) up.
    cy.lpFindComponent('Second text');

    cy.get('.lpb-up:visible').click();
    cy.get('.ajax-progress').should('not.exist');

    // After reorder, 'Second text' should precede 'First text' in the DOM.
    cy.get('[data-type="lp_test_text"]').then(($els) => {
      const texts = $els.map((i, el) => Cypress.$(el).text()).get();
      expect(texts[0]).to.include('Second text');
      expect(texts[1]).to.include('First text');
    });

    // Save the page. The edit form was reached via admin/content so Drupal
    // redirects back to admin/content after save — navigate to the node view
    // explicitly to verify the custom template text is present.
    cy.lpSavePage();
    cy.then(() => cy.visit(nodeViewUrl));
    cy.contains('Custom template rendering lp_test_text paragraph type.').should(
      'exist',
    );
  });
});
