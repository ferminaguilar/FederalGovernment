Cypress.config('defaultCommandTimeout', 15000);

describe('Layout Paragraphs Component Form Entity API Tests', () => {
  function phpEval(code) {
    const cmd = Cypress.env('drushCommand').replace(
      '$COMMAND',
      `php:eval '${code}'`,
    );
    return cy.exec(cmd);
  }

  before(() => {
    cy.drush(
      'en layout_paragraphs_setup_test layout_paragraphs_component_form_test',
    );
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);

    phpEval(
      '\\Drupal\\Core\\Entity\\Entity\\EntityFormMode::create(["id" => "paragraph.component_form_test", "targetEntityType" => "paragraph", "label" => "Component form test", "status" => TRUE])->save();'
      + ' \\Drupal\\Core\\Entity\\Entity\\EntityFormDisplay::load("paragraph.lp_test_text.default")->createCopy("component_form_test")->save();',
    );
    phpEval(
      '$d = \\Drupal\\Core\\Entity\\Entity\\EntityFormDisplay::load("node.lp_test_ct.default");'
      + ' $d->setComponent("field_lp_test_content", ["type" => "layout_paragraphs", "settings" => ["form_display_mode" => "component_form_test"]])->save();',
    );

    cy.drush('cr');
    cy.viewport(1000, 800);
  });

  after(() => {
    phpEval(
      '$d = \\Drupal\\Core\\Entity\\Entity\\EntityFormDisplay::load("node.lp_test_ct.default");'
      + ' $d->setComponent("field_lp_test_content", ["type" => "layout_paragraphs"])->save();'
      + ' \\Drupal\\Core\\Entity\\Entity\\EntityFormDisplay::load("paragraph.lp_test_text.component_form_test")->delete();'
      + ' \\Drupal\\Core\\Entity\\Entity\\EntityFormMode::load("paragraph.component_form_test")->delete();',
    );
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
    cy.drush('pmu layout_paragraphs_component_form_test');
    cy.drush('cr');
  });

  beforeEach(() => {
    cy.loginUserByUid(1);
  });

  it('exposes the paragraph and the form display to form alters', () => {
    cy.lpCreateTestPage('Component Form Entity API Test');
    cy.lpAddSection('layout_onecol', '.lpb-btn--add');

    cy.get('.layout__region--content .lpb-btn--add')
      .first()
      .click({ force: true });
    cy.get('.lpb-component-list a').contains('Text').click();
    cy.get('.ui-dialog .lp-component-form-test').should(
      'have.text',
      'lp_test_text paragraph.lp_test_text.component_form_test',
    );
    cy.lpSetCKEditor5Value('field_lp_test_text', 'Entity API text');
    cy.get('.lpb-btn--save:visible').click();
    cy.get('.layout-paragraphs-component-form').should('not.exist');

    cy.lpFindComponent('Entity API text');
    cy.get('a.lpb-edit:visible').click();
    cy.get('.ui-dialog .lp-component-form-test').should(
      'have.text',
      'lp_test_text paragraph.lp_test_text.component_form_test',
    );
  });
});
