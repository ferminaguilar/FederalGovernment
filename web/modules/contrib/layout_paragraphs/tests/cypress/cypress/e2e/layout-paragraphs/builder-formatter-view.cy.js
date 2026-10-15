Cypress.config('defaultCommandTimeout', 15000);

describe('Layout Paragraphs Builder Formatter View Tests', () => {
  function phpEval(code) {
    const cmd = Cypress.env('drushCommand').replace(
      '$COMMAND',
      `php:eval '${code}'`,
    );
    return cy.exec(cmd);
  }

  before(() => {
    cy.drush('en layout_paragraphs_setup_test');
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
    phpEval(
      '$d = \\Drupal\\Core\\Entity\\Entity\\EntityViewDisplay::load("node.lp_test_ct.default");'
      + ' $d->setComponent("field_lp_test_content", ["type" => "layout_paragraphs_builder"])->save();',
    );
    cy.drush('cr');
    cy.viewport(1000, 800);
  });

  after(() => {
    phpEval(
      '$d = \\Drupal\\Core\\Entity\\Entity\\EntityViewDisplay::load("node.lp_test_ct.default");'
      + ' $d->setComponent("field_lp_test_content", ["type" => "layout_paragraphs"])->save();',
    );
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
    cy.drush('cr');
  });

  beforeEach(() => {
    cy.loginUserByUid(1);
  });

  it('views a node with the builder formatter without JavaScript errors', () => {
    cy.lpCreateTestPage('Builder Formatter View Test');
    cy.lpAddSection('layout_onecol', '.lpb-btn--add');
    cy.lpAddTextComponent('First text', '.layout__region--content .lpb-btn--add');
    cy.lpSavePage();

    // The support file ignores uncaught exceptions; collect them here instead.
    const errors = [];
    cy.on('uncaught:exception', (error) => {
      errors.push(error.message);
      return false;
    });
    cy.reload();
    cy.get('.lpb-formatter a.lpb-enable-button').should('exist');
    cy.contains('First text').should('be.visible');
    cy.wrap(errors).should((list) => {
      expect(list.join('\n')).to.equal('');
    });
  });
});
