Cypress.config('defaultCommandTimeout', 15000);

describe('Layout Paragraphs Reorder Unlisted Components Tests', () => {
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
    cy.drush('cr');
    cy.viewport(1000, 800);
  });

  after(() => {
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
  });

  beforeEach(() => {
    cy.loginUserByUid(1);
  });

  it('keeps a component the builder does not render when reordering', () => {
    cy.lpCreateTestPage('Reorder Unlisted Test');
    cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');
    cy.lpAddTextComponent('First text', '.layout__region--first .lpb-btn--add');
    cy.lpAddTextComponent(
      'Stranded text',
      '.layout__region--third .lpb-btn--add',
    );
    cy.lpSavePage();

    cy.url().then((url) => {
      const nid = url.match(/node\/(\d+)/)[1];

      cy.visit(`/node/${nid}/edit`);
      cy.lpFindComponent('First text');
      cy.get('[data-active="true"]').trigger('mouseover');
      cy.lpAddTextComponent(
        'Second text',
        '[data-type="lp_test_text"] .lpb-btn--add.after',
      );
      cy.lpSavePage();

      phpEval(
        `$node = \\Drupal::entityTypeManager()->getStorage("node")->load(${nid});`
        + ' foreach ($node->field_lp_test_content->referencedEntities() as $p) {'
        + ' if ($p->bundle() == "lp_test_section") {'
        + ' $s = $p->getAllBehaviorSettings()["layout_paragraphs"];'
        + ' $s["layout"] = "layout_twocol";'
        + ' $p->setBehaviorSettings("layout_paragraphs", $s);'
        + ' $p->save();'
        + ' } }',
      );
      cy.drush('cr');

      cy.visit(`/node/${nid}/edit`);
      cy.contains('First text').should('exist');
      cy.contains('Stranded text').should('not.exist');

      cy.lpFindComponent('Second text');
      cy.get('.lpb-up:visible').click();
      cy.lpSavePage();

      phpEval(
        `$node = \\Drupal::entityTypeManager()->getStorage("node")->load(${nid});`
        + ' echo json_encode(array_values(array_map(fn($p) => trim(strip_tags((string) $p->field_lp_test_text->value)),'
        + ' array_filter($node->field_lp_test_content->referencedEntities(), fn($p) => $p->bundle() == "lp_test_text"))));',
      )
        .its('stdout')
        .then((stdout) => {
          expect(JSON.parse(stdout.trim())).to.deep.equal([
            'Second text',
            'First text',
            'Stranded text',
          ]);
        });
    });
  });
});
