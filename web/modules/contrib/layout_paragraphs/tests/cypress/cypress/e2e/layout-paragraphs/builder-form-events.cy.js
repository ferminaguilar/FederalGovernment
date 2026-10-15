Cypress.config('defaultCommandTimeout', 15000);

describe('Layout Paragraphs Builder Form Events Tests', () => {
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

  it('labels the close button "Cancel" and ignores events outside a builder form', () => {
    cy.lpCreateTestPage('Builder Form Events Test');
    cy.lpAddSection('layout_onecol', '.lpb-btn--add');
    cy.lpAddTextComponent('First text', '.layout__region--content .lpb-btn--add');
    cy.lpSavePage();

    cy.get('.lpb-formatter a.lpb-enable-button').click();
    cy.get('[data-lpb-form-id] [data-drupal-selector="edit-close"]').should(
      'have.value',
      'Close',
    );

    // A layout paragraphs element outside the builder form, as the widget of a
    // nested paragraph inside a component dialog is.
    cy.window().then((win) => {
      const wrapper = win.document.createElement('div');
      wrapper.innerHTML = '<div data-lpb-id="outside-the-builder-form"></div>';
      win.document.body.appendChild(wrapper);
      win.Drupal.attachBehaviors(wrapper);
      win.jQuery(wrapper.firstChild).trigger('lpb-component:update');
    });
    cy.get('[data-lpb-form-id] [data-drupal-selector="edit-close"]').should(
      'have.value',
      'Close',
    );

    cy.window().then((win) => {
      win.jQuery('[data-lpb-form-id] [data-lpb-id]').first().trigger('lpb-component:update');
    });
    cy.get('[data-lpb-form-id] [data-drupal-selector="edit-close"]').should(
      'have.value',
      'Cancel',
    );
  });
});
