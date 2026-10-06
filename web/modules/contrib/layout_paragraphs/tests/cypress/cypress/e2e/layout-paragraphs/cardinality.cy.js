Cypress.config('defaultCommandTimeout', 10000);

describe('Layout Paragraphs Cardinality Tests', () => {
  before(() => {
    // Install the Layout Paragraphs setup test module.
    cy.drush('en layout_paragraphs_setup_test');
    // Clear the cache.
    cy.drush('cr');
    
    // Delete all existing test content to allow config changes
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
    
    // Set cardinality to 2 for the field.
    cy.setFieldCardinality('node.field_lp_test_content', 2);
    
    // Clear cache again after config changes
    cy.drush('cr');
    
    // Give us a taller viewport to work with.
    cy.viewport(1000, 800);
  });

  after(() => {
    // Reset cardinality to unlimited for other tests
    cy.setFieldCardinality('node.field_lp_test_content', -1);
    cy.drush('cr');
  });

  beforeEach(() => {
    // Login as admin.
    cy.loginUserByUid(1);
  });

  it('respects cardinality settings for field widgets', () => {
    cy.lpCreateTestPage('Cardinality Test', 'lp_test_ct');

    // Add a three-column section (count = 1)
    cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');

    // Wait for the section to fully render
    cy.get('.layout__region--first').should('be.visible');
    cy.get('.layout__region--first .lpb-btn--add').should('exist');
    
    // Wait for animations/transitions to complete
    cy.wait(500);

    // Cardinality is set to 2. We should still have (+) buttons.
    cy.get('.layout__region--first .lpb-btn--add').should('exist');

    // Add a text component (count = 2, at limit)
    cy.get('.layout__region--first .lpb-btn--add').click({ force: true });
    
    // Wait for component chooser dialog
    cy.get('.ui-dialog-title').should('contain', 'Choose a component');
    
    // Click Text component
    cy.get('.type-lp_test_text a').contains('Text').should('be.visible').click();
    
    // Wait for CKEditor to load - check for the editor instance
    cy.get('.ck-content').should('exist').should('be.visible');
    
    // Wait for AJAX to complete
    cy.get('.ajax-progress').should('not.exist');
    
    // Type text into CKEditor
    cy.get('.ck-content').click().type('Some arbitrary text');
    
    // Save the component
    cy.get('button.lpb-btn--save').click();
    
    // Wait for save to complete
    cy.contains('Some arbitrary text').should('be.visible');
    cy.get('.ajax-progress').should('not.exist');

    // Maximum number has been reached. There should be no more (+) buttons.
    cy.get('.layout__region--first .lpb-btn--add').should('not.exist');
    cy.get('.layout__region--second .lpb-btn--add').should('not.exist');
    cy.get('.layout__region--third .lpb-btn--add').should('not.exist');

    // Remove the component
    cy.get('.layout__region--first a.lpb-delete').click();
    cy.get('button.lpb-btn--confirm-delete').click();
    
    // Wait for deletion to complete
    cy.get('.ajax-progress').should('not.exist');

    // We no longer have the maximum allowed items, and should have (+) buttons.
    cy.get('.layout__region--first .lpb-btn--add').should('exist');
  });

  it('verifies cardinality limits across different regions', () => {
    cy.lpCreateTestPage('Multi-Region Cardinality Test', 'lp_test_ct');

    // Add a three-column section (count = 1)
    cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');

    // Wait for the section to fully render
    cy.get('.layout__region--first').should('be.visible');
    cy.get('.layout__region--first .lpb-btn--add').should('exist');
    
    // Wait for animations/transitions to complete
    cy.wait(500);

    // Add one text component to reach cardinality limit (count = 2, at limit)
    cy.get('.layout__region--first .lpb-btn--add').click({ force: true });
    cy.get('.ui-dialog-title').should('contain', 'Choose a component');
    cy.get('.type-lp_test_text a').contains('Text').should('be.visible').click();
    cy.get('.ck-content').should('exist').should('be.visible');
    cy.get('.ajax-progress').should('not.exist');
    cy.get('.ck-content').click().type('First component');
    cy.get('button.lpb-btn--save').click();
    cy.contains('First component').should('be.visible');
    cy.get('.ajax-progress').should('not.exist');

    // All regions should now be at cardinality limit
    cy.get('.layout__region--first .lpb-btn--add').should('not.exist');
    cy.get('.layout__region--second .lpb-btn--add').should('not.exist');
    cy.get('.layout__region--third .lpb-btn--add').should('not.exist');

    // Remove the component
    cy.get('.layout__region--first a.lpb-delete').click();
    cy.get('button.lpb-btn--confirm-delete').click();
    cy.get('.ajax-progress').should('not.exist');

    // Add buttons should be available again
    cy.get('.layout__region--first .lpb-btn--add').should('exist');
    cy.get('.layout__region--second .lpb-btn--add').should('exist');
    cy.get('.layout__region--third .lpb-btn--add').should('exist');
  });

  it('handles cardinality when at limit', () => {
    cy.lpCreateTestPage('Cardinality Limit Test', 'lp_test_ct');

    // Add a three-column section (count = 1)
    cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');

    // Wait for the section to fully render
    cy.get('.layout__region--first').should('be.visible');
    cy.get('.layout__region--first .lpb-btn--add').should('exist');
    
    // Wait for animations/transitions to complete
    cy.wait(500);

    // Add one text component to reach limit (count = 2, at limit)
    cy.get('.layout__region--first .lpb-btn--add').click({ force: true });
    cy.get('.ui-dialog-title').should('contain', 'Choose a component');
    cy.get('.type-lp_test_text a').contains('Text').should('be.visible').click();
    cy.get('.ck-content').should('exist').should('be.visible');
    cy.get('.ajax-progress').should('not.exist');
    cy.get('.ck-content').click().type('Component at limit');
    cy.get('button.lpb-btn--save').click();
    cy.contains('Component at limit').should('be.visible');
    cy.get('.ajax-progress').should('not.exist');

    // Verify component exists
    cy.contains('Component at limit').should('be.visible');

    // Verify add buttons are not available due to cardinality
    cy.get('.layout__region--first .lpb-btn--add').should('not.exist');
    cy.get('.layout__region--second .lpb-btn--add').should('not.exist');
    cy.get('.layout__region--third .lpb-btn--add').should('not.exist');
  });
});
