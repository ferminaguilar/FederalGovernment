Cypress.config('defaultCommandTimeout', 10000);

/**
 * Tests non-default view modes and form modes for Layout Paragraphs.
 *
 * Uses drush php:eval to configure entity display/form modes programmatically,
 * mirroring the PHP setup in DisplayModeTest::setUp().
 */
describe('Layout Paragraphs Display Mode Tests', () => {
  before(() => {
    cy.drush('en layout_paragraphs_setup_test');
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);

    // Run PHP via drush php:eval wrapped in shell single quotes so that zsh
    // does not interpret PHP brackets or parentheses as shell syntax.
    // All PHP string literals use double quotes to avoid conflicting with the
    // surrounding shell single quotes.
    const phpEval = (code) => {
      const cmd = Cypress.env('drushCommand').replace(
        '$COMMAND',
        `php:eval '${code}'`,
      );
      return cy.exec(cmd);
    };

    // Create "alternative" paragraph view mode and enable its view display
    // (skip creation if they already exist from a previous run, but always
    // ensure field_lp_test_text is configured on the view display).
    phpEval(
      '$vm = \\Drupal\\Core\\Entity\\Entity\\EntityViewMode::load("paragraph.alternative");'
      + ' if (!$vm) { \\Drupal\\Core\\Entity\\Entity\\EntityViewMode::create(["id" => "paragraph.alternative", "targetEntityType" => "paragraph", "label" => "Alternative", "status" => TRUE])->save(); }'
      + ' $vd = \\Drupal\\Core\\Entity\\Entity\\EntityViewDisplay::load("paragraph.lp_test_text.alternative");'
      + ' if (!$vd) { $vd = \\Drupal\\Core\\Entity\\Entity\\EntityViewDisplay::create(["targetEntityType" => "paragraph", "bundle" => "lp_test_text", "mode" => "alternative", "status" => TRUE]); }'
      + ' $vd->setComponent("field_lp_test_text", ["type" => "text_default", "label" => "hidden"])->save();',
    );

    // Configure node view display to use the "alternative" paragraph view mode.
    phpEval(
      '$d = \\Drupal\\Core\\Entity\\Entity\\EntityViewDisplay::load("node.lp_test_ct.default");'
      + ' $d->setComponent("field_lp_test_content", ["type" => "layout_paragraphs", "settings" => ["view_mode" => "alternative"]])->save();',
    );

    // Create "alternative" paragraph form mode and copy the default form display
    // (skip creation if they already exist from a previous run).
    phpEval(
      '$fm = \\Drupal\\Core\\Entity\\Entity\\EntityFormMode::load("paragraph.alternative");'
      + ' if (!$fm) { \\Drupal\\Core\\Entity\\Entity\\EntityFormMode::create(["id" => "paragraph.alternative", "targetEntityType" => "paragraph", "label" => "Alternative", "status" => TRUE])->save(); }'
      + ' $fd = \\Drupal\\Core\\Entity\\Entity\\EntityFormDisplay::load("paragraph.lp_test_text.alternative");'
      + ' if (!$fd) { $copy = \\Drupal\\Core\\Entity\\Entity\\EntityFormDisplay::load("paragraph.lp_test_text.default")->createCopy("alternative"); $copy->setComponent("created", ["type" => "datetime_timestamp"])->save(); }',
    );

    // Configure node form display to use alternative form and preview modes.
    phpEval(
      '$d = \\Drupal\\Core\\Entity\\Entity\\EntityFormDisplay::load("node.lp_test_ct.default");'
      + ' $d->setComponent("field_lp_test_content", ["type" => "layout_paragraphs", "settings" => ["form_display_mode" => "alternative", "preview_view_mode" => "alternative"]])->save();',
    );

    cy.drush('cr');
    cy.viewport(1000, 800);
  });

  after(() => {
    // Reset node displays back to defaults.
    const phpEval = (code) => {
      const cmd = Cypress.env('drushCommand').replace(
        '$COMMAND',
        `php:eval '${code}'`,
      );
      return cy.exec(cmd);
    };
    phpEval(
      '$d = \\Drupal\\Core\\Entity\\Entity\\EntityViewDisplay::load("node.lp_test_ct.default");'
      + ' $d->setComponent("field_lp_test_content", ["type" => "layout_paragraphs"])->save();'
      + ' $d2 = \\Drupal\\Core\\Entity\\Entity\\EntityFormDisplay::load("node.lp_test_ct.default");'
      + ' $d2->setComponent("field_lp_test_content", ["type" => "layout_paragraphs"])->save();',
    );
    cy.drush('cr');
  });

  beforeEach(() => {
    cy.loginUserByUid(1);
  });

  it('renders paragraphs with the alternative view mode', () => {
    cy.lpCreateTestPage('Display Mode View Test');

    // Add a one-column section and a text component.
    cy.lpAddSection('layout_onecol', '.lpb-btn--add');
    cy.lpAddTextComponent(
      'Alternative view mode text',
      '.layout__region--content .lpb-btn--add',
    );

    // Save the node.
    cy.lpSavePage();

    // The text should be visible.
    cy.contains('Alternative view mode text').should('be.visible');

    // The paragraph should carry the "alternative" view mode CSS class.
    cy.get('.paragraph--view-mode--alternative').should('exist');
  });

  it('renders the alternative preview view mode in the builder', () => {
    cy.lpCreateTestPage('Display Mode Preview Test');

    // Add a one-column section and a text component.
    cy.lpAddSection('layout_onecol', '.lpb-btn--add');
    cy.lpAddTextComponent(
      'Alternative preview text',
      '.layout__region--content .lpb-btn--add',
    );

    // The component preview inside the builder should use the alternative mode.
    cy.get('.paragraph--view-mode--alternative').should('exist');
  });

  it('shows the extra "Authored on" field in the alternative form mode', () => {
    cy.lpCreateTestPage('Display Mode Form Test');

    // Add a one-column section and a text component.
    cy.lpAddSection('layout_onecol', '.lpb-btn--add');
    cy.lpAddTextComponent(
      'Alternative form mode text',
      '.layout__region--content .lpb-btn--add',
    );

    // Open the edit dialog for the text component.
    cy.lpFindComponent('Alternative form mode text');
    cy.get('a.lpb-edit:visible').click();

    // The "Authored on" (created) field should appear in the alternative form mode.
    cy.get('.ui-dialog').should('contain', 'Authored on');
  });
});
