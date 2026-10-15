Cypress.config('defaultCommandTimeout', 15000);

describe('Layout Paragraphs Builder Save and Close Dialogs', () => {
  function phpEval(code) {
    const cmd = Cypress.env('drushCommand').replace(
      '$COMMAND',
      `php:eval '${code}'`,
    );
    return cy.exec(cmd);
  }

  function useFormatter(type) {
    phpEval(
      '$d = \\Drupal\\Core\\Entity\\Entity\\EntityViewDisplay::load("node.lp_test_ct.default");' +
        ` $d->setComponent("field_lp_test_content", ["type" => "${type}"])->save();`,
    );
    cy.drush('cr');
  }

  function revisionLogs(nid) {
    return phpEval(
      '$s = \\Drupal::entityTypeManager()->getStorage("node");' +
        ` $n = $s->load(${nid});` +
        ' echo json_encode(array_map(fn($vid) => $s->loadRevision($vid)->getRevisionLogMessage(), $s->revisionIds($n)));',
    )
      .its('stdout')
      .then((stdout) => JSON.parse(stdout.trim()));
  }

  function createPage(title) {
    cy.lpCreateTestPage(title);
    cy.lpAddSection('layout_onecol', '.lpb-btn--add');
    cy.lpAddTextComponent(
      'First text',
      '.layout__region--content .lpb-btn--add',
    );
    cy.lpSavePage();
    return cy.url().then((url) => url.match(/node\/(\d+)/)[1]);
  }

  function openBuilder(nid) {
    cy.visit(`/node/${nid}`);
    cy.get('.lpb-enable-button').click();
    cy.get('.layout-paragraphs-builder-form .lpb-edit').should('exist');
  }

  function duplicateText() {
    cy.get('.layout-paragraphs-builder-form [data-type="lp_test_text"]').then(
      ($components) => {
        cy.lpFindComponent('First text');
        cy.get('a.lpb-duplicate:visible').click();
        cy.get(
          '.layout-paragraphs-builder-form [data-type="lp_test_text"]',
        ).should('have.length', $components.length + 1);
      },
    );
  }

  function saveBuilder() {
    cy.get(
      '.layout-paragraphs-builder-form .lpb-form__actions [data-drupal-selector="edit-submit"]',
    ).click();
  }

  function dialog() {
    return cy.get('.ui-dialog:visible');
  }

  function beforeUnloadIsPrevented() {
    return cy.window().then((win) => {
      const event = new win.Event('beforeunload', { cancelable: true });
      win.dispatchEvent(event);
      return event.defaultPrevented;
    });
  }

  let errors;

  before(() => {
    cy.drush('en layout_paragraphs_setup_test');
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
    useFormatter('layout_paragraphs_builder');
    cy.viewport(1000, 800);
  });

  after(() => {
    useFormatter('layout_paragraphs');
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
  });

  beforeEach(() => {
    cy.loginUserByUid(1);
    // The support file ignores uncaught exceptions; collect them here instead.
    errors = [];
    cy.on('uncaught:exception', (error) => {
      errors.push(error.message);
      return false;
    });
  });

  afterEach(() => {
    cy.wrap(errors).should((list) => {
      expect(list.join('\n')).to.equal('');
    });
  });

  it('asks for the revision before saving', () => {
    createPage('Save Dialog Test').then((nid) => {
      openBuilder(nid);

      saveBuilder();
      dialog().should('contain', 'There are no changes to save.');
      dialog().find('input[value="Save"]').should('not.exist');
      dialog().find('.dialog-cancel').click();
      cy.get('.ui-dialog:visible').should('not.exist');

      duplicateText();
      saveBuilder();
      dialog().find('input[name="revision"]').should('be.checked');
      dialog().find('textarea[name="revision_log"]').should('have.value', '');
      dialog()
        .find('textarea[name="revision_log"]')
        .type('Duplicated the first text.');
      dialog().find('input[value="Save"]').click();

      cy.contains('has been updated').should('exist');
      cy.get('.ui-dialog:visible').should('not.exist');
      revisionLogs(nid).then((logs) => {
        expect(logs).to.have.length(2);
        expect(logs[1]).to.equal('Duplicated the first text.');
      });
    });
  });

  it('asks before closing with unsaved changes', () => {
    createPage('Close Dialog Test').then((nid) => {
      openBuilder(nid);

      duplicateText();
      cy.get('.layout-paragraphs-builder-form .lpb-btn--cancel').click();
      dialog().should('contain', 'Unsaved changes');
      dialog().find('.dialog-cancel').click();
      cy.get('.layout-paragraphs-builder-form').should('exist');

      // The form is cached by now: saving it saves the current layout.
      saveBuilder();
      dialog().find('input[value="Save"]').click();
      cy.contains('has been updated').should('exist');
      cy.get('.ui-dialog:visible').should('not.exist');
      cy.get(
        '.layout-paragraphs-builder-form [data-type="lp_test_text"]',
      ).should('have.length', 2);

      duplicateText();
      cy.get('.layout-paragraphs-builder-form .lpb-btn--cancel').click();
      dialog().find('input[value="Leave without saving"]').click();
      cy.get('.layout-paragraphs-builder-form').should('not.exist');
      cy.reload();
      cy.get('main')
        .invoke('text')
        .should((text) => {
          expect(text.split('First text')).to.have.length(3);
        });
    });
  });

  it('closes without asking when nothing changed', () => {
    createPage('Close Without Changes Test').then((nid) => {
      openBuilder(nid);
      cy.get('.layout-paragraphs-builder-form .lpb-btn--cancel').click();
      cy.get('.layout-paragraphs-builder-form').should('not.exist');
      cy.get('.ui-dialog:visible').should('not.exist');
    });
  });

  it('warns before leaving the page with unsaved changes', () => {
    createPage('Leave Page Test').then((nid) => {
      openBuilder(nid);
      beforeUnloadIsPrevented().should('equal', false);

      duplicateText();
      beforeUnloadIsPrevented().should('equal', true);

      cy.get('.layout-paragraphs-builder-form .lpb-btn--cancel').click();
      dialog().find('.dialog-cancel').click();
      beforeUnloadIsPrevented().should('equal', true);

      saveBuilder();
      dialog().find('input[value="Save"]').click();
      cy.contains('has been updated').should('exist');
      beforeUnloadIsPrevented().should('equal', false);

      cy.lpFindComponent('First text');
      cy.get('a.lpb-delete:visible').click();
      cy.get('.ui-dialog-title').should('contain', 'Delete component');
      cy.get('button.lpb-btn--confirm-delete').click();
      cy.get(
        '.layout-paragraphs-builder-form [data-type="lp_test_text"]',
      ).should('have.length', 1);
      beforeUnloadIsPrevented().should('equal', true);
    });
  });
});
