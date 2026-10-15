Cypress.config('defaultCommandTimeout', 15000);

describe('Layout Paragraphs Layout Switch Orphans Tests', () => {
  function phpEval(code) {
    const cmd = Cypress.env('drushCommand').replace(
      '$COMMAND',
      `php:eval '${code}'`,
    );
    return cy.exec(cmd);
  }

  function setSectionLayouts(add) {
    phpEval(
      '$t = \\Drupal\\paragraphs\\Entity\\ParagraphsType::load("lp_test_section");'
      + ' $p = $t->get("behavior_plugins");'
      + (add
        ? ' $p["layout_paragraphs"]["available_layouts"]["layout_twocol_section"] = "Two column";'
          + ' $p["layout_paragraphs"]["available_layouts"]["layout_threecol_section"] = "Three column";'
        : ' unset($p["layout_paragraphs"]["available_layouts"]["layout_twocol_section"], $p["layout_paragraphs"]["available_layouts"]["layout_threecol_section"]);')
      + ' $t->set("behavior_plugins", $p)->save();',
    );
  }

  function editSection(layout) {
    cy.get(`[data-layout="${layout}"]`)
      .closest('[data-uuid]')
      .invoke('attr', 'data-uuid')
      .then((uuid) => {
        cy.lpSelectComponent(uuid);
        cy.get(`[data-uuid="${uuid}"] > .lpb-controls a.lpb-edit`).click();
      });
    cy.get('.ui-dialog-title').should('contain', 'Edit Section');
    cy.get('.ajax-progress').should('not.exist');
  }

  before(() => {
    cy.drush('en layout_paragraphs_setup_test layout_builder');
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
    setSectionLayouts(true);
    cy.drush('cr');
    cy.viewport(1000, 800);
  });

  after(() => {
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
    setSectionLayouts(false);
    cy.drush('pmu layout_builder');
    cy.drush('cr');
  });

  beforeEach(() => {
    cy.loginUserByUid(1);
  });

  it('offers to move the items of a removed region when switching three columns to two', () => {
    cy.lpCreateTestPage('Layout Switch Test');
    cy.lpAddSection('layout_threecol_section', '.lpb-btn--add');
    cy.lpAddTextComponent('First text', '.layout__region--first .lpb-btn--add');
    cy.lpAddTextComponent('Second text', '.layout__region--second .lpb-btn--add');
    cy.lpAddTextComponent('Third text', '.layout__region--third .lpb-btn--add');

    editSection('layout_threecol_section');
    cy.get('.layout-select__item input[value="layout_twocol_section"] + label').click();
    cy.get('.ajax-progress').should('not.exist');
    cy.get('.ui-dialog').should('contain', 'Move Orphaned Items');
    cy.get('.ui-dialog select[name="layout_paragraphs[move_items][third]"]').select('second');
    cy.get('button.lpb-btn--save').click();
    cy.get('.layout-paragraphs-component-form').should('not.exist');

    cy.get('[data-layout="layout_twocol_section"]').should('exist');
    cy.get('.layout__region--second').should('contain', 'Third text');
    cy.lpSavePage();
    cy.get('.layout__region--second').should('contain', 'Third text');
  });
});
