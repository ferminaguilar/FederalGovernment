Cypress.config('defaultCommandTimeout', 15000);

/**
 * Tests various translation scenarios for Layout Paragraphs.
 *
 * Mirrors TranslationTest.php, adapted for the lp_test_ct content type and the
 * lp_test_section / lp_test_text paragraph types used by the Cypress test setup.
 *
 * The layout_paragraphs_translations_test module provides paragraph templates
 * that output "Section component language: [lang]" and
 * "Text component language: [lang]".  Because these templates target the
 * "section" and "text" bundles (not "lp_test_section" / "lp_test_text"), the
 * language-in-template assertions are replaced with equivalent drush php:eval
 * checks where necessary.
 */
describe('Layout Paragraphs Translation Tests', () => {
  /**
   * Helper: enable symmetrical translation for lp_test_ct / paragraphs.
   * Reference field is NOT translatable; paragraphs themselves are translated.
   */
  // Helper: run a PHP snippet via drush php:eval, wrapped in shell single
  // quotes so zsh does not interpret PHP brackets/parens as shell syntax.
  // All PHP string literals must use double quotes inside the snippet.
  function phpEval(code) {
    const cmd = Cypress.env('drushCommand').replace(
      '$COMMAND',
      `php:eval '${code}'`,
    );
    return cy.exec(cmd);
  }

  function enableSymmetricTranslations() {
    phpEval(
      'if (!\\Drupal\\language\\Entity\\ConfigurableLanguage::load("de")) {'
      + ' \\Drupal\\language\\Entity\\ConfigurableLanguage::create(["id" => "de"])->save();'
      + ' }'
      + ' \\Drupal::service("content_translation.manager")->setEnabled("node", "lp_test_ct", TRUE);'
      + ' $fs = \\Drupal::entityTypeManager()->getStorage("field_storage_config")->load("node.field_lp_test_content");'
      + ' if ($fs && $fs->isTranslatable()) { $fs->setTranslatable(FALSE)->save(); }'
      + ' \\Drupal::service("content_translation.manager")->setEnabled("paragraph", "lp_test_text", TRUE);'
      + ' \\Drupal::service("content_translation.manager")->setEnabled("paragraph", "lp_test_section", TRUE);'
      + ' $fc = \\Drupal::entityTypeManager()->getStorage("field_config")->load("paragraph.lp_test_text.field_lp_test_text");'
      + ' if ($fc && !$fc->isTranslatable()) { $fc->setTranslatable(TRUE)->save(); }'
      + ' $lcs = \\Drupal::configFactory()->getEditable("language.content_settings.node.lp_test_ct");'
      + ' $lcs->set("language_alterable", TRUE)->save();',
    );
    cy.drush('cr');
  }

  /**
   * Helper: enable asymmetrical translation for lp_test_ct / paragraphs.
   * Reference field IS translatable; paragraphs are cloned, not translated.
   */
  function enableAsymmetricTranslations() {
    phpEval(
      'if (!\\Drupal\\language\\Entity\\ConfigurableLanguage::load("de")) {'
      + ' \\Drupal\\language\\Entity\\ConfigurableLanguage::create(["id" => "de"])->save();'
      + ' }'
      + ' \\Drupal::service("content_translation.manager")->setEnabled("node", "lp_test_ct", TRUE);'
      + ' $fs = \\Drupal::entityTypeManager()->getStorage("field_storage_config")->load("node.field_lp_test_content");'
      + ' if ($fs && !$fs->isTranslatable()) { $fs->setTranslatable(TRUE)->save(); }'
      + ' \\Drupal::service("content_translation.manager")->setEnabled("paragraph", "lp_test_text", FALSE);'
      + ' \\Drupal::service("content_translation.manager")->setEnabled("paragraph", "lp_test_section", FALSE);'
      + ' $fc = \\Drupal::entityTypeManager()->getStorage("field_config")->load("paragraph.lp_test_text.field_lp_test_text");'
      + ' if ($fc && $fc->isTranslatable()) { $fc->setTranslatable(FALSE)->save(); }',
    );
    cy.drush('cr');
  }

  before(() => {
    cy.drush(
      'en layout_paragraphs_setup_test layout_paragraphs_translations_test content_translation language',
    );
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
    cy.drush('cr');
    cy.viewport(1000, 800);
  });

  after(() => {
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
    cy.drush('pmu layout_paragraphs_translations_test');
    cy.drush('cr');
  });

  beforeEach(() => {
    cy.loginUserByUid(1);
  });

  it('shows the symmetric translation mode warning and hides add/delete controls', () => {
    enableSymmetricTranslations();

    cy.lpCreateTestPage('Symmetric Translation Test', 'lp_test_ct');
    cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');
    cy.lpAddTextComponent(
      'First component',
      '.layout__region--first .lpb-btn--add',
    );
    cy.lpAddTextComponent(
      'Second component',
      '.layout__region--second .lpb-btn--add',
    );
    cy.lpAddTextComponent(
      'Third component',
      '.layout__region--third .lpb-btn--add',
    );
    cy.lpSavePage();

    // Clear render cache immediately after saving the English node.
    // This ensures paragraph entity-view caches (which contain
    // drupalSettings with duplicate controls from the English creation)
    // are invalidated before the translation add form renders.
    cy.drush('cr');

    cy.url().then((url) => {
      const nid = url.match(/node\/(\d+)/)[1];

      // Visit the translation add form.  ContentTranslationController::add()
      // sets form_state['content_translation'], which satisfies LP's
      // isTranslating() condition 1, so the builder renders in symmetric
      // translation mode with no duplicate/delete controls.
      cy.visit(`/node/${nid}/translations/add/en/de`);
    });

    // In symmetric mode the builder shows a translation-mode warning.
    cy.contains(
      'You are in translation mode. You cannot add or remove items while translating.',
    ).should('exist');

    // Duplicate and delete controls must be hidden in symmetric mode.
    cy.get('.lpb-duplicate').should('not.exist');
    cy.get('.lpb-delete').should('not.exist');
  });

  it('preserves English content after editing a German translation (symmetric)', () => {
    enableSymmetricTranslations();

    cy.lpCreateTestPage('Translation Preservation Test', 'lp_test_ct');
    cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');
    cy.lpAddTextComponent(
      'First source',
      '.layout__region--first .lpb-btn--add',
    );
    cy.lpSavePage();

    cy.url().then((url) => {
      const nid = url.match(/node\/(\d+)/)[1];

      // Create the German translation via the add form (saves as-is).
      cy.visit(`/node/${nid}/translations/add/en/de`);
      cy.get('input[name="title[0][value]"]').clear().type('Translation Preservation Test (de)');
      cy.lpSavePage();

      // Clear render cache so entity-view cache (which may have
      // drupalSettings with duplicate controls from the English creation)
      // is invalidated.  LP then re-renders with duplicate_access=FALSE.
      cy.drush('cr');

      // /de/node/{nid}/edit uses getTranslationFromContext to set
      // form_state langcode='de', triggering LP symmetric translation mode.
      cy.visit(`/de/node/${nid}/edit`);

      // Edit first component text in German translation.
      cy.lpFindComponent('First source');
      cy.get('a.lpb-edit:visible').click();
      cy.get('.ck-editor__editable[contenteditable="true"]')
        .clear()
        .type('First source (de)');
      cy.get('.lpb-btn--save:visible').click();
      cy.contains('First source (de)').should('be.visible');

      // Save the German translation.
      cy.lpSavePage();

      // English node should not contain the German text.
      cy.visit(`/node/${nid}`);
      cy.contains('First source').should('exist');
      cy.contains('First source (de)').should('not.exist');

      // German node should show the translated text.
      cy.visit(`/de/node/${nid}`);
      cy.contains('First source (de)').should('exist');
    });
  });

  it('deleting a component in English removes it from a symmetric German translation', () => {
    enableSymmetricTranslations();

    cy.lpCreateTestPage('Symmetric Delete Test', 'lp_test_ct');
    cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');
    cy.lpAddTextComponent(
      'Keep this',
      '.layout__region--first .lpb-btn--add',
    );
    cy.lpAddTextComponent(
      'Delete this',
      '.layout__region--third .lpb-btn--add',
    );
    cy.lpSavePage();

    cy.url().then((url) => {
      const nid = url.match(/node\/(\d+)/)[1];

      // Create German translation (just save as-is).
      cy.visit(`/node/${nid}/translations/add/en/de`);
      cy.get('input[name="title[0][value]"]')
        .clear()
        .type('Symmetric Delete Test (de)');
      cy.lpSavePage();

      // Delete "Delete this" from the primary (English) node.
      cy.visit(`/node/${nid}/edit`);
      cy.lpFindComponent('Delete this');
      cy.get('a.lpb-delete:visible').click();
      cy.get('.ui-dialog-title').should('contain', 'Delete component');
      cy.get('button.lpb-btn--confirm-delete').click();
      cy.contains('Delete this').should('not.exist');
      cy.lpSavePage();

      // Verify "Delete this" is gone from English.
      cy.visit(`/node/${nid}`);
      cy.contains('Delete this').should('not.exist');

      // Symmetric translation: deleting from English also removes from German.
      cy.visit(`/de/node/${nid}`);
      cy.contains('Delete this').should('not.exist');
    });
  });

  it('shows the asymmetric translation warning and keeps add/delete controls', () => {
    enableAsymmetricTranslations();

    cy.lpCreateTestPage('Asymmetric Translation Test', 'lp_test_ct');
    cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');
    cy.lpAddTextComponent(
      'Async component',
      '.layout__region--first .lpb-btn--add',
    );
    cy.lpSavePage();

    // Navigate directly to the add-German-translation form.
    cy.url().then((url) => {
      const nid = url.match(/node\/(\d+)/)[1];
      cy.visit(`/node/${nid}/translations/add/en/de`);
    });

    // In asymmetric mode a different message is shown.
    cy.contains(
      'You are in translation mode. Changes will only affect the current language.',
    ).should('exist');

    // Duplicate and delete controls should be PRESENT in asymmetric mode.
    cy.get('.lpb-duplicate').should('exist');
    cy.get('.lpb-delete').should('exist');
  });

  it('preserves a deleted component in the asymmetric German translation', () => {
    enableAsymmetricTranslations();

    cy.lpCreateTestPage('Asymmetric Delete Isolation Test', 'lp_test_ct');
    cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');
    cy.lpAddTextComponent(
      'Source only',
      '.layout__region--first .lpb-btn--add',
    );
    cy.lpSavePage();

    cy.url().then((url) => {
      const nid = url.match(/node\/(\d+)/)[1];

      // Create German translation and save.
      cy.visit(`/node/${nid}/translations/add/en/de`);
      cy.get('input[name="title[0][value]"]')
        .clear()
        .type('Asymmetric Delete Isolation Test (de)');
      cy.lpSavePage();

      // Delete "Source only" from the primary English node.
      cy.visit(`/node/${nid}/edit`);
      cy.lpFindComponent('Source only');
      cy.get('a.lpb-delete:visible').click();
      cy.get('button.lpb-btn--confirm-delete').click();
      cy.contains('Source only').should('not.exist');
      cy.lpSavePage();

      // English should not contain the component.
      cy.visit(`/node/${nid}`);
      cy.contains('Source only').should('not.exist');

      // Asymmetric translation: German has its own copy so it is unaffected.
      cy.visit(`/de/node/${nid}`);
      cy.contains('Source only').should('exist');
    });
  });

  it('switches paragraph language codes when the host entity language changes', () => {
    enableSymmetricTranslations();

    cy.lpCreateTestPage('Switch Language Test', 'lp_test_ct');
    cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');
    cy.lpAddTextComponent(
      'Language check component',
      '.layout__region--first .lpb-btn--add',
    );
    cy.lpSavePage();

    cy.url().then((url) => {
      const nid = url.match(/node\/(\d+)/)[1];

      // Edit the node and change its language to German.
      cy.visit(`/node/${nid}/edit`);
      cy.get('[name="langcode[0][value]"]').select('de');
      cy.get('input[value="Save"]').filter(':visible').first().click();

      // Verify via drush that the paragraphs now carry the "de" langcode.
      phpEval(
        `$node = \\Drupal::entityTypeManager()->getStorage("node")->load(${nid});`
        + ' $paras = $node->field_lp_test_content->referencedEntities();'
        + ' $langs = array_unique(array_map(fn($p) => $p->language()->getId(), $paras));'
        + ' echo implode(",", $langs);',
      )
        .its('stdout')
        .then((stdout) => {
          // All paragraphs should now be in German.
          const langs = stdout.trim().split(',').filter(Boolean);
          expect(langs).to.deep.equal(['de']);
        });
    });
  });
});
