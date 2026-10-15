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

  /**
   * Helper: hide untranslatable fields on the translation forms of lp_test_ct.
   * With the reference field untranslatable, a translation then cannot change
   * the layout structure shared by all languages.
   *
   * @param {boolean} hide Whether to hide the untranslatable fields.
   */
  function hideUntranslatableFields(hide) {
    phpEval(
      '\\Drupal::service("content_translation.manager")'
      + ` ->setBundleTranslationSettings("node", "lp_test_ct", ["untranslatable_fields_hide" => ${hide ? 'TRUE' : 'FALSE'}]);`,
    );
    cy.drush('cr');
  }

  /**
   * Helper: count the Sortable instances the builder created on the page.
   */
  function countSortableInstances() {
    return cy
      .window()
      .then((win) =>
        [...win.Drupal.LPSortableInstances.instances.values()].reduce(
          (total, instances) => total + instances.size,
          0,
        ),
      );
  }

  function assertLayoutLocked(scope) {
    cy.contains(
      'You are in translation mode. You cannot add, remove or reorder items while translating.',
    ).should('exist');
    cy.get(`${scope} .lpb-edit`).should('exist');
    cy.get(`${scope} .lpb-drag`).should('not.exist');
    cy.get(`${scope} .lpb-up`).should('not.exist');
    cy.get(`${scope} .lpb-down`).should('not.exist');
    cy.get(`${scope} .lpb-btn--add`).should('not.exist');
    countSortableInstances().should('equal', 0);
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
    hideUntranslatableFields(false);
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

  it('keeps the symmetric restrictions after editing a component', () => {
    enableSymmetricTranslations();

    cy.lpCreateTestPage('Symmetric Component Edit Test', 'lp_test_ct');
    cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');
    cy.lpAddTextComponent('Edit me', '.layout__region--first .lpb-btn--add');
    cy.lpAddTextComponent('Leave me', '.layout__region--third .lpb-btn--add');
    cy.lpSavePage();
    cy.drush('cr');

    cy.url().then((url) => {
      const nid = url.match(/node\/(\d+)/)[1];
      cy.visit(`/node/${nid}/translations/add/en/de`);
    });
    cy.contains(
      'You are in translation mode. You cannot add or remove items while translating.',
    ).should('exist');
    cy.get('.lpb-duplicate').should('not.exist');
    cy.get('.lpb-delete').should('not.exist');

    cy.lpFindComponent('Edit me');
    cy.get('a.lpb-edit:visible').click();
    cy.get('.ck-editor__editable[contenteditable="true"]')
      .clear()
      .type('Edit me (de)');
    cy.get('.lpb-btn--save:visible').click();
    cy.contains('Edit me (de)').should('be.visible');
    cy.get('.lpb-edit').should('have.length.at.least', 2);
    cy.get('.lpb-duplicate').should('not.exist');
    cy.get('.lpb-delete').should('not.exist');
  });

  describe('with untranslatable fields hidden', () => {
    after(() => {
      hideUntranslatableFields(false);
    });

    it('locks the layout structure of a symmetric translation', () => {
      enableSymmetricTranslations();
      hideUntranslatableFields(true);

      cy.lpCreateTestPage('Locked Layout Test', 'lp_test_ct');
      cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');
      cy.lpAddTextComponent(
        'Locked first',
        '.layout__region--first .lpb-btn--add',
      );
      cy.lpAddTextComponent(
        'Locked third',
        '.layout__region--third .lpb-btn--add',
      );
      cy.lpSavePage();
      cy.drush('cr');

      cy.url().then((url) => {
        const nid = url.match(/node\/(\d+)/)[1];

        // The original language still changes the structure.
        cy.visit(`/node/${nid}/edit`);
        cy.get('.lpb-drag').should('exist');
        countSortableInstances().should('be.greaterThan', 0);

        cy.visit(`/node/${nid}/translations/add/en/de`);
        assertLayoutLocked('.lp-builder');

        // Translating a component keeps the lock.
        cy.lpFindComponent('Locked first');
        cy.get('a.lpb-edit:visible').click();
        cy.get('.ck-editor__editable[contenteditable="true"]')
          .clear()
          .type('Locked first (de)');
        cy.get('.lpb-btn--save:visible').click();
        cy.contains('Locked first (de)').should('be.visible');
        assertLayoutLocked('.lp-builder');
      });
    });
  });

  describe('at the cardinality limit', () => {
    after(() => {
      cy.setFieldCardinality('node.field_lp_test_content', -1);
    });

    it('keeps the asymmetric warning after the layout is refreshed', () => {
      enableAsymmetricTranslations();

      cy.lpCreateTestPage('Asymmetric Refresh Test', 'lp_test_ct');
      cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');
      cy.lpAddTextComponent('Refresh first', '.layout__region--first .lpb-btn--add');
      cy.lpAddTextComponent('Refresh third', '.layout__region--third .lpb-btn--add');
      cy.lpSavePage();

      cy.setFieldCardinality('node.field_lp_test_content', 3);
      cy.drush('cr');

      cy.url().then((url) => {
        const nid = url.match(/node\/(\d+)/)[1];
        cy.visit(`/node/${nid}/translations/add/en/de`);
      });
      cy.contains(
        'You are in translation mode. Changes will only affect the current language.',
      ).should('exist');
      cy.get('.lpb-btn--add').should('not.exist');

      cy.lpFindComponent('Refresh third');
      cy.get('a.lpb-delete:visible').click();
      cy.get('button.lpb-btn--confirm-delete').click();
      cy.contains('Refresh third').should('not.exist');

      cy.get('.lpb-btn--add').should('exist');
      cy.contains(
        'You are in translation mode. Changes will only affect the current language.',
      ).should('exist');
    });
  });

  describe('frontend builder', () => {
    function useFormatter(type) {
      phpEval(
        '\\Drupal::service("entity_display.repository")->getViewDisplay("node", "lp_test_ct")'
        + ` ->setComponent("field_lp_test_content", ["type" => "${type}", "label" => "hidden"])->save();`,
      );
      cy.drush('cr');
    }

    function createPageWithGermanTranslation(title, text) {
      cy.lpCreateTestPage(title, 'lp_test_ct');
      cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');
      cy.lpAddTextComponent(text, '.layout__region--first .lpb-btn--add');
      cy.lpSavePage();
      cy.drush('cr');
      return cy.url().then((url) => {
        const nid = url.match(/node\/(\d+)/)[1];
        cy.visit(`/node/${nid}/translations/add/en/de`);
        cy.get('input[name="title[0][value]"]').clear().type(`${title} (de)`);
        cy.lpSavePage();
        cy.drush('cr');
        return cy.wrap(nid);
      });
    }

    function openFrontendBuilder(path) {
      cy.visit(path);
      cy.get('.lpb-enable-button').click();
      cy.get('.layout-paragraphs-builder-form').should('exist');
      cy.get('.layout-paragraphs-builder-form .lpb-edit').should('exist');
    }

    function saveFrontendBuilder() {
      cy.get(
        '.layout-paragraphs-builder-form .lpb-form__actions [data-drupal-selector="edit-submit"]',
      ).click();
      cy.get('.ui-dialog:visible input[value="Save"]').click();
      cy.contains('has been updated').should('exist');
    }

    function assertSymmetricRestrictions() {
      cy.get('.layout-paragraphs-builder-form .lpb-edit').should('exist');
      cy.get('.layout-paragraphs-builder-form .lpb-duplicate').should('not.exist');
      cy.get('.layout-paragraphs-builder-form .lpb-delete').should('not.exist');
      cy.get('.layout-paragraphs-builder-form .lpb-btn--add').should('not.exist');
    }

    before(() => {
      useFormatter('layout_paragraphs_builder');
    });

    after(() => {
      useFormatter('layout_paragraphs');
    });

    it('edits the German translation without changing English (symmetric)', () => {
      enableSymmetricTranslations();

      createPageWithGermanTranslation('Builder Symmetric Test', 'Builder source').then((nid) => {
        openFrontendBuilder(`/de/node/${nid}`);
        cy.contains(
          'You are in translation mode. You cannot add or remove items while translating.',
        ).should('exist');
        assertSymmetricRestrictions();

        cy.lpFindComponent('Builder source');
        cy.get('a.lpb-edit:visible').click();
        cy.get('.ck-editor__editable[contenteditable="true"]')
          .clear()
          .type('Builder source (de)');
        cy.get('.lpb-btn--save:visible').click();
        cy.contains('Builder source (de)').should('be.visible');

        assertSymmetricRestrictions();
        saveFrontendBuilder();

        phpEval(
          `$node = \\Drupal::entityTypeManager()->getStorage("node")->load(${nid});`
          + ' $p = $node->field_lp_test_content->referencedEntities();'
          + ' $text = array_values(array_filter($p, fn($e) => $e->bundle() == "lp_test_text"))[0];'
          + ' echo json_encode(array_map(fn($l) => strip_tags($text->getTranslation($l)->field_lp_test_text->value), array_combine(array_keys($text->getTranslationLanguages()), array_keys($text->getTranslationLanguages()))));',
        )
          .its('stdout')
          .then((stdout) => {
            expect(JSON.parse(stdout.trim())).to.deep.equal({
              en: 'Builder source',
              de: 'Builder source (de)',
            });
          });

        cy.visit(`/node/${nid}`);
        cy.contains('Builder source').should('exist');
        cy.contains('Builder source (de)').should('not.exist');
        cy.visit(`/de/node/${nid}`);
        cy.contains('Builder source (de)').should('exist');
      });
    });

    it('does not enter translation mode on the original language', () => {
      enableSymmetricTranslations();

      createPageWithGermanTranslation('Builder Original Test', 'Original source').then((nid) => {
        openFrontendBuilder(`/node/${nid}`);
        cy.contains('You are in translation mode.').should('not.exist');
        cy.get('.layout-paragraphs-builder-form .lpb-delete').should('exist');
      });
    });

    it('locks the layout structure of a symmetric translation', () => {
      enableSymmetricTranslations();
      hideUntranslatableFields(true);

      createPageWithGermanTranslation(
        'Builder Locked Test',
        'Locked source',
      ).then((nid) => {
        openFrontendBuilder(`/de/node/${nid}`);
        assertLayoutLocked('.layout-paragraphs-builder-form');
      });
      hideUntranslatableFields(false);
    });

    it('changes only the German copy (asymmetric)', () => {
      enableAsymmetricTranslations();

      createPageWithGermanTranslation('Builder Asymmetric Test', 'Asymmetric source').then((nid) => {
        openFrontendBuilder(`/de/node/${nid}`);
        cy.contains(
          'You are in translation mode. Changes will only affect the current language.',
        ).should('exist');

        cy.lpFindComponent('Asymmetric source');
        cy.get('a.lpb-delete:visible').click();
        cy.get('button.lpb-btn--confirm-delete').click();
        cy.get('.layout-paragraphs-builder-form').contains('Asymmetric source').should('not.exist');
        saveFrontendBuilder();

        cy.visit(`/de/node/${nid}`);
        cy.contains('Asymmetric source').should('not.exist');
        cy.visit(`/node/${nid}`);
        cy.contains('Asymmetric source').should('exist');
      });
    });
  });
});
