Cypress.config('defaultCommandTimeout', 10000);

describe('Layout Paragraphs Convert Component Tests', () => {
  before(() => {
    cy.drush('en', [
      'layout_paragraphs_setup_test',
      'layout_paragraphs_conversion_test',
      'layout_paragraphs_restrictions',
    ]);
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);

    // Reset any state left over from a previous failed run.
    const cleanupCode = [
      `Drupal::configFactory()->getEditable('layout_paragraphs_restrictions.settings')->delete();`,
      `\\$display = Drupal::entityTypeManager()->getStorage('entity_form_display')->load('node.lp_test_ct.default');`,
      `\\$component = \\$display->getComponent('field_lp_test_content');`,
      `\\$component['settings']['conversion'] = TRUE;`,
      `\\$display->setComponent('field_lp_test_content', \\$component)->save();`,
    ].join(' ');
    cy.exec(
      Cypress.env('drushCommand').replace('$COMMAND', `php:eval "${cleanupCode}"`),
    );

    cy.drush('cr');
    cy.viewport(1000, 800);
  });

  beforeEach(() => {
    cy.loginUserByUid(1);
  });

  it('section component has no convert button', () => {
    cy.lpCreateTestPage('Section No Convert', 'lp_test_ct');
    cy.lpAddSection('layout_onecol', '.lpb-btn--add');
    cy.get('.is-layout .lpb-convert').should('not.exist');
  });

  it('text component has convert button', () => {
    cy.lpCreateTestPage('Text Has Convert', 'lp_test_ct');
    cy.lpAddSection('layout_onecol', '.lpb-btn--add');
    cy.lpAddTextComponent('Hello world.', '.layout__region .lpb-btn--add');
    cy.get('.layout__region .lpb-convert').should('exist');
  });

  it('clicking convert button opens dialog', () => {
    cy.lpCreateTestPage('Convert Dialog Opens', 'lp_test_ct');
    cy.lpAddSection('layout_onecol', '.lpb-btn--add');
    cy.lpAddTextComponent('Hello world.', '.layout__region .lpb-btn--add');
    cy.lpFindComponent('Hello world.');
    cy.get('.lpb-convert:visible').click();
    cy.get('.ajax-progress').should('not.exist');
    cy.get('.lpb-dialog').should('exist');
    cy.get('[name="conversion_plugin"]').should('exist');
  });

  it('full paragraph conversion flow', () => {
    cy.lpCreateTestPage('Full Convert', 'lp_test_ct');
    cy.lpAddSection('layout_onecol', '.lpb-btn--add');
    cy.lpAddTextComponent('Convertible text.', '.layout__region .lpb-btn--add');
    cy.lpFindComponent('Convertible text.');
    cy.get('.lpb-convert:visible').click();
    cy.get('.ajax-progress').should('not.exist');
    // The test plugin should be available and pre-selected (only one option).
    cy.get('[name="conversion_plugin"]').should(
      'have.value',
      'layout_paragraphs_lp_test_text_to_lp_test_text',
    );
    cy.get('.lpb-btn--save:visible').click();
    cy.get('.ajax-progress').should('not.exist');
    // Dialog should close and layout should refresh.
    cy.get('.lpb-dialog').should('not.exist');
    // The text should still be visible in the refreshed layout.
    cy.contains('Convertible text.').should('be.visible');
  });

  it('convert button hidden when conversion disabled', () => {
    // Disable the conversion setting on the entity form display.
    const disableCode = [
      `\\$display = Drupal::entityTypeManager()->getStorage('entity_form_display')->load('node.lp_test_ct.default');`,
      `\\$component = \\$display->getComponent('field_lp_test_content');`,
      `\\$component['settings']['conversion'] = FALSE;`,
      `\\$display->setComponent('field_lp_test_content', \\$component)->save();`,
    ].join(' ');
    cy.exec(
      Cypress.env('drushCommand').replace('$COMMAND', `php:eval "${disableCode}"`),
    );
    cy.drush('cr');

    cy.lpCreateTestPage('No Convert', 'lp_test_ct');
    cy.lpAddSection('layout_onecol', '.lpb-btn--add');
    cy.lpAddTextComponent('No convert.', '.layout__region .lpb-btn--add');
    cy.get('.layout__region .lpb-convert').should('not.exist');

    // Restore the conversion setting.
    const enableCode = [
      `\\$display = Drupal::entityTypeManager()->getStorage('entity_form_display')->load('node.lp_test_ct.default');`,
      `\\$component = \\$display->getComponent('field_lp_test_content');`,
      `\\$component['settings']['conversion'] = TRUE;`,
      `\\$display->setComponent('field_lp_test_content', \\$component)->save();`,
    ].join(' ');
    cy.exec(
      Cypress.env('drushCommand').replace('$COMMAND', `php:eval "${enableCode}"`),
    );
    cy.drush('cr');
  });

  it('convert button respects layout_paragraphs_restrictions', () => {
    cy.lpCreateTestPage('Restriction test', 'lp_test_ct');
    // Add a two-column section.
    cy.lpAddSection('layout_twocol', '.lpb-btn--add');
    cy.lpAddTextComponent('First column.', '.layout__region--first .lpb-btn--add');
    cy.lpAddTextComponent('Second column.', '.layout__region--second .lpb-btn--add');
    // Save the node so paragraph entities are persisted before restricting.
    cy.lpSavePage();

    cy.url().then((url) => {
      const nid = url.match(/\/node\/(\d+)/)[1];

      // Restrict the first column of layout_twocol: exclude lp_test_text so
      // no valid conversion targets remain there (sections cannot be nested).
      const setCode = [
        `\\$cfg = Drupal::configFactory()->getEditable('layout_paragraphs_restrictions.settings');`,
        `\\$cfg->set('restrictions', [['context' => ['layout' => 'layout_twocol', 'region' => 'first'], 'exclude_components' => ['lp_test_text']]])->save();`,
      ].join(' ');
      cy.exec(
        Cypress.env('drushCommand').replace('$COMMAND', `php:eval "${setCode}"`),
      );
      cy.drush('cr');

      cy.visit(`/node/${nid}/edit`);
      // Wait for the two-column layout to render.
      cy.get('.layout__region--second').should('exist');
      // No conversion targets in the restricted column.
      cy.get('.layout__region--first .lpb-convert').should('not.exist');
      // The unrestricted column still allows conversion.
      cy.get('.layout__region--second .lpb-convert').should('exist');

      // Clean up restriction config so other tests are not affected.
      const deleteCode = `Drupal::configFactory()->getEditable('layout_paragraphs_restrictions.settings')->delete();`;
      cy.exec(
        Cypress.env('drushCommand').replace(
          '$COMMAND',
          `php:eval "${deleteCode}"`,
        ),
      );
    });
  });
});
