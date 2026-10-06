Cypress.config('defaultCommandTimeout', 10000);

describe('Layout Paragraphs Nested Sections Tests', () => {
  before(() => {
    // Install the Layout Paragraphs setup test module.
    cy.drush('en layout_paragraphs_setup_test');
    // Clear the cache.
    cy.drush('cr');
    
    // Delete all existing test content
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
    
    // Ensure cardinality is unlimited for nested sections
    cy.setFieldCardinality('node.field_lp_test_content', -1);
    
    // Allow nesting sections by updating the form display.
    cy.drush('config-set', [
      'core.entity_form_display.node.lp_test_ct.default',
      'content.field_lp_test_content.settings.nesting_depth',
      '1',
    ]);
    
    // Clear cache again after config changes
    cy.drush('cr');
    
    // Give us a taller viewport to work with.
    cy.viewport(1000, 800);
  });

  beforeEach(() => {
    // Login as admin.
    cy.loginUserByUid(1);
  });

  it('creates and manages nested sections', () => {
    cy.lpCreateTestPage('Nested Sections Test', 'lp_test_ct');

    // Add a two-column section
    cy.lpAddSection('layout_twocol', '.lpb-btn--add');

    // Wait for the section to fully render
    cy.get('.layout__region--first').should('be.visible');
    cy.get('.layout__region--first .lpb-btn--add').should('exist');
    cy.wait(500);

    // Add a one-column section in region 1
    cy.get('.layout__region--first .lpb-btn--add').click({ force: true });
    cy.get('.ui-dialog-title').should('contain', 'Choose a component');
    cy.get('.type-lp_test_section a').contains('Section').should('be.visible').click();
    cy.get('.ui-dialog-title').should('contain', 'Create new Section');
    cy.get('.ajax-progress').should('not.exist');
    cy.get('.layout-select__item input[value="layout_onecol"] + label').should('be.visible').click();
    cy.get('button.lpb-btn--save').click();
    cy.get('[data-layout="layout_onecol"]').should('exist');
    cy.get('.ajax-progress').should('not.exist');
    cy.wait(500);

    // Add a three-column section in region 2
    cy.get('.layout__region--second .lpb-btn--add').click({ force: true });
    cy.get('.ui-dialog-title').should('contain', 'Choose a component');
    cy.get('.type-lp_test_section a').contains('Section').should('be.visible').click();
    cy.get('.ui-dialog-title').should('contain', 'Create new Section');
    cy.get('.ajax-progress').should('not.exist');
    cy.get('.layout-select__item input[value="layout_threecol_25_50_25"] + label').should('be.visible').click();
    cy.get('button.lpb-btn--save').click();
    cy.get('[data-layout="layout_threecol_25_50_25"]').should('exist');
    cy.get('.ajax-progress').should('not.exist');
    cy.wait(500);

    // Add text components in each nested section
    // First component in nested one-column section
    cy.get('.layout__region--first .layout__region--content .lpb-btn--add').click({ force: true });
    cy.get('.ui-dialog-title').should('contain', 'Choose a component');
    cy.get('.type-lp_test_text a').contains('Text').should('be.visible').click();
    cy.get('.ck-content').should('exist').should('be.visible');
    cy.get('.ajax-progress').should('not.exist');
    cy.get('.ck-content').click().type('First');
    cy.get('button.lpb-btn--save').click();
    cy.contains('First').should('be.visible');
    cy.get('.ajax-progress').should('not.exist');

    // Second component in nested three-column section (first column)
    cy.get('.layout__region--second .layout__region--first .lpb-btn--add').click({ force: true });
    cy.get('.ui-dialog-title').should('contain', 'Choose a component');
    cy.get('.type-lp_test_text a').contains('Text').should('be.visible').click();
    cy.get('.ck-content').should('exist').should('be.visible');
    cy.get('.ajax-progress').should('not.exist');
    cy.get('.ck-content').click().type('Second');
    cy.get('button.lpb-btn--save').click();
    cy.contains('Second').should('be.visible');
    cy.get('.ajax-progress').should('not.exist');

    // Third component in nested three-column section (second column)
    cy.get('.layout__region--second .layout__region--second .lpb-btn--add').click({ force: true });
    cy.get('.ui-dialog-title').should('contain', 'Choose a component');
    cy.get('.type-lp_test_text a').contains('Text').should('be.visible').click();
    cy.get('.ck-content').should('exist').should('be.visible');
    cy.get('.ajax-progress').should('not.exist');
    cy.get('.ck-content').click().type('Third');
    cy.get('button.lpb-btn--save').click();
    cy.contains('Third').should('be.visible');
    cy.get('.ajax-progress').should('not.exist');

    // Fourth component in nested three-column section (third column)
    cy.get('.layout__region--second .layout__region--third .lpb-btn--add').click({ force: true });
    cy.get('.ui-dialog-title').should('contain', 'Choose a component');
    cy.get('.type-lp_test_text a').contains('Text').should('be.visible').click();
    cy.get('.ck-content').should('exist').should('be.visible');
    cy.get('.ajax-progress').should('not.exist');
    cy.get('.ck-content').click().type('Fourth');
    cy.get('button.lpb-btn--save').click();
    cy.contains('Fourth').should('be.visible');
    cy.get('.ajax-progress').should('not.exist');

    // Verify all components are present on the edit page
    cy.contains('First').should('be.visible');
    cy.contains('Second').should('be.visible');
    cy.contains('Third').should('be.visible');
    cy.contains('Fourth').should('be.visible');

    // Save the node
    cy.lpSavePage();

    // Check for all the added components still on view tab
    cy.contains('First').should('be.visible');
    cy.contains('Second').should('be.visible');
    cy.contains('Third').should('be.visible');
    cy.contains('Fourth').should('be.visible');
  });

  it('verifies nested layout structure', () => {
    cy.lpCreateTestPage('Nested Structure Test', 'lp_test_ct');

    // Create a complex nested structure
    // Add a two-column section
    cy.lpAddSection('layout_twocol', '.lpb-btn--add');
    
    // Wait for the section to fully render
    cy.get('.layout__region--first').should('be.visible');
    cy.get('.layout__region--first .lpb-btn--add').should('exist');
    cy.wait(500);

    // Add one column in first region
    cy.get('.layout__region--first .lpb-btn--add').click({ force: true });
    cy.get('.ui-dialog-title').should('contain', 'Choose a component');
    cy.get('.type-lp_test_section a').contains('Section').should('be.visible').click();
    cy.get('.ui-dialog-title').should('contain', 'Create new Section');
    cy.get('.ajax-progress').should('not.exist');
    cy.get('.layout-select__item input[value="layout_onecol"] + label').should('be.visible').click();
    cy.get('button.lpb-btn--save').click();
    cy.get('[data-layout="layout_onecol"]').should('exist');
    cy.get('.ajax-progress').should('not.exist');
    cy.wait(500);

    // Add two column in second region
    cy.get('.layout__region--second .lpb-btn--add').click({ force: true });
    cy.get('.ui-dialog-title').should('contain', 'Choose a component');
    cy.get('.type-lp_test_section a').contains('Section').should('be.visible').click();
    cy.get('.ui-dialog-title').should('contain', 'Create new Section');
    cy.get('.ajax-progress').should('not.exist');
    cy.get('.layout-select__item input[value="layout_twocol"] + label').should('be.visible').click();
    cy.get('button.lpb-btn--save').click();
    cy.get('.ajax-progress').should('not.exist');
    cy.wait(500);

    // Verify the structure exists
    cy.get('.layout__region--first .layout--onecol').should('exist');
    cy.get('.layout__region--second .layout--twocol').should('exist');

    // Add content to verify regions are functional
    // Left nested content
    cy.get('.layout__region--first .layout__region--content .lpb-btn--add').click({ force: true });
    cy.get('.ui-dialog-title').should('contain', 'Choose a component');
    cy.get('.type-lp_test_text a').contains('Text').should('be.visible').click();
    cy.get('.ck-content').should('exist').should('be.visible');
    cy.get('.ajax-progress').should('not.exist');
    cy.get('.ck-content').click().type('Left nested content');
    cy.get('button.lpb-btn--save').click();
    cy.contains('Left nested content').should('be.visible');
    cy.get('.ajax-progress').should('not.exist');

    // Right nested left
    cy.get('.layout__region--second .layout__region--first .lpb-btn--add').click({ force: true });
    cy.get('.ui-dialog-title').should('contain', 'Choose a component');
    cy.get('.type-lp_test_text a').contains('Text').should('be.visible').click();
    cy.get('.ck-content').should('exist').should('be.visible');
    cy.get('.ajax-progress').should('not.exist');
    cy.get('.ck-content').click().type('Right nested left');
    cy.get('button.lpb-btn--save').click();
    cy.contains('Right nested left').should('be.visible');
    cy.get('.ajax-progress').should('not.exist');

    // Right nested right
    cy.get('.layout__region--second .layout__region--second .lpb-btn--add').click({ force: true });
    cy.get('.ui-dialog-title').should('contain', 'Choose a component');
    cy.get('.type-lp_test_text a').contains('Text').should('be.visible').click();
    cy.get('.ck-content').should('exist').should('be.visible');
    cy.get('.ajax-progress').should('not.exist');
    cy.get('.ck-content').click().type('Right nested right');
    cy.get('button.lpb-btn--save').click();
    cy.contains('Right nested right').should('be.visible');
    cy.get('.ajax-progress').should('not.exist');

    // Save and verify
    cy.lpSavePage();

    cy.contains('Left nested content').should('be.visible');
    cy.contains('Right nested left').should('be.visible');
    cy.contains('Right nested right').should('be.visible');
  });
});
