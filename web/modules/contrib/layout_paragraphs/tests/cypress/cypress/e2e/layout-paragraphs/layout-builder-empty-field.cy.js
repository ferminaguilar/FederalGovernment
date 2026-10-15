Cypress.config('defaultCommandTimeout', 15000);

describe('Layout Paragraphs Layout Builder Empty Field Tests', () => {
  function phpEval(code) {
    const cmd = Cypress.env('drushCommand').replace(
      '$COMMAND',
      `php:eval '${code}'`,
    );
    return cy.exec(cmd);
  }

  before(() => {
    cy.drush('en layout_paragraphs_setup_test layout_builder');
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
    phpEval(
      '$d = \\Drupal\\Core\\Entity\\Entity\\EntityViewDisplay::load("node.lp_test_ct.default");'
      + ' $d->setComponent("field_lp_test_content", ["type" => "layout_paragraphs_builder"])->save();'
      + ' \\Drupal\\Core\\Entity\\Entity\\EntityViewDisplay::load("node.lp_test_ct.default")->enableLayoutBuilder()->save();',
    );
    cy.drush('cr');
    cy.viewport(1000, 800);
  });

  after(() => {
    phpEval(
      '$d = \\Drupal\\Core\\Entity\\Entity\\EntityViewDisplay::load("node.lp_test_ct.default");'
      + ' $d->disableLayoutBuilder()->save();'
      + ' $d = \\Drupal\\Core\\Entity\\Entity\\EntityViewDisplay::load("node.lp_test_ct.default");'
      + ' $d->setComponent("field_lp_test_content", ["type" => "layout_paragraphs"])->save();',
    );
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
    cy.drush('pmu layout_builder');
    cy.drush('cr');
  });

  beforeEach(() => {
    cy.loginUserByUid(1);
  });

  it('renders the builder for an empty field on a Layout Builder display', () => {
    phpEval(
      '$n = \\Drupal\\node\\Entity\\Node::create(["type" => "lp_test_ct", "title" => "Empty Layout Builder Test", "uid" => 1]);'
      + ' $n->save(); echo $n->id();',
    )
      .its('stdout')
      .then((stdout) => {
        cy.visit(`/node/${stdout.trim()}`);
        cy.contains('h1', 'Empty Layout Builder Test').should('exist');
        cy.get('.lpb-formatter .lpb-enable__empty-message').should('exist');
        cy.get('.lpb-formatter a.lpb-enable-button').should('exist');
      });
  });
});
