Cypress.config('defaultCommandTimeout', 10000);

describe('Layout Paragraphs Builder Tests', () => {
  before(() => {
    // Install the Layout Paragraphs setup test module.
    cy.drush('en layout_paragraphs_setup_test');

    // Delete all existing test content
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);

    // Clear the cache.
    cy.drush('cr');

    // Give us a taller viewport to work with.
    cy.viewport(1000, 800);
  });

  beforeEach(() => {
    // Login as admin.
    cy.loginUserByUid(1);
  });

  it('adds a section component to a new page', () => {
    cy.lpCreateTestPage('Test Page');

    // Add a three-column section
    cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');

    // Assert that three columns now exist
    cy.get('.layout__region--first').should('exist');
    cy.get('.layout__region--second').should('exist');
    cy.get('.layout__region--third').should('exist');
  });

  it('switches between layouts', () => {
    cy.lpCreateTestPage('Test Layout Switch');

    // Add a section component with a three-column layout
    cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');
    cy.get('.layout--threecol-25-50-25').should('exist');

    // Edit the section and switch to 1-column layout
    cy.get('.lpb-edit').click();
    cy.get(`.layout-select__item input[value="layout_onecol"] + label`).click();
    cy.get('button.lpb-btn--save').click();

    // Should now be a 1-column layout
    cy.get('.layout--onecol').should('exist');
  });

  it('adds a component into a section', () => {
    cy.lpCreateTestPage('Test Add Component');

    // Add a section first
    cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');

    // Add a text component to the first column
    cy.lpAddTextComponent(
      'Some arbitrary text',
      '.layout__region--first .lpb-btn--add',
    );

    // Save the page
    cy.lpSavePage();

    // Verify content is saved
    cy.contains('Test Add Component').should('be.visible');
    cy.contains('Some arbitrary text').should('be.visible');
  });

  it('edits a paragraph component', () => {
    cy.lpCreateTestPage('Test Edit Component');

    // Add a section and text component
    cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');
    cy.lpAddTextComponent(
      'Some arbitrary text',
      '.layout__region--first .lpb-btn--add',
    );

    // Save the page first
    cy.lpSavePage();

    // Go to edit mode
    cy.visit('/admin/content');
    cy.get('a[aria-label="Edit Test Edit Component"]')
      .first()
      .scrollIntoView()
      .click();

    cy.lpFindComponent('Some arbitrary text');
    // Edit the component
    cy.get('a.lpb-edit:visible').click();
    cy.get('.ui-dialog-title').should('contain', 'Edit Text');
  });

  it('deletes a component', () => {
    cy.lpCreateTestPage('Test Delete Component');

    // Add a section and text component
    cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');
    cy.lpAddTextComponent(
      'Text to delete',
      '.layout__region--first .lpb-btn--add',
    );

    // Save and go to edit mode
    cy.lpSavePage();

    // Go to edit mode
    cy.visit('/admin/content');
    cy.get('a[aria-label="Edit Test Delete Component"]')
      .first()
      .scrollIntoView()
      .click();

    // Delete the component
    cy.lpFindComponent('Text to delete');
    cy.get('a.lpb-delete:visible').click();
    cy.get('.ui-dialog-title').should('contain', 'Delete component');
    cy.get('button.lpb-btn--confirm-delete').click();

    // Component should no longer be on page
    cy.contains('Text to delete').should('not.exist');
  });

  it('cancels component deletion', () => {
    cy.lpCreateTestPage('Test Cancel Delete');

    // Add a section and text component
    cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');
    cy.lpAddTextComponent(
      'Text to keep',
      '.layout__region--first .lpb-btn--add',
    );

    // Save and go to edit mode
    cy.lpSavePage();

    cy.visit('/admin/content');
    cy.get('a[aria-label="Edit Test Cancel Delete"]')
      .first()
      .scrollIntoView()
      .click();

    // Start delete but cancel
    cy.lpFindComponent('Text to keep');
    cy.get('a.lpb-delete:visible').click();
    cy.get('.ui-dialog-title').should('contain', 'Delete component');
    cy.get('button.dialog-cancel').click();

    // Component should still be on page
    cy.contains('Text to keep').should('exist');
  });

  it('reorders components with the move up button', () => {
    cy.lpCreateTestPage('Test Reorder');

    // Add a section and two text components
    cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');
    cy.lpAddTextComponent('First text', '.layout__region--first .lpb-btn--add');

    // Save and go to edit mode to add second component
    cy.lpSavePage();

    cy.visit('/admin/content');
    cy.get('a[aria-label="Edit Test Reorder"]')
      .first()
      .scrollIntoView()
      .click();

    // Add second component after the first
    cy.lpFindComponent('First text');
    // Hover over the active component.
    cy.get('[data-active="true"]').trigger('mouseover');

    cy.lpAddTextComponent(
      'Second text',
      '[data-type="lp_test_text"] .lpb-btn--add.after',
    );

    // Move the second component up
    cy.lpFindComponent('Second text');
    cy.get('.lpb-up:visible').click();

    // Save the page
    cy.lpSavePage();
    cy.visit('/admin/content');
    cy.get('a[aria-label="Edit Test Reorder"]')
      .first()
      .scrollIntoView()
      .click();

    // Verify the order changed - Second text should appear before First text in the HTML
    cy.get('body').then(($body) => {
      const html = $body.html();
      const firstIndex = html.indexOf('First text');
      const secondIndex = html.indexOf('Second text');
      expect(secondIndex).to.be.lessThan(firstIndex);
    });
  });

  function pressUntil(keys, selector, maxTabs = 50) {
    let count = 0;

    const step = () => {
      cy.document().then((doc) => {
        const $ae = Cypress.$(doc.activeElement);

        if ($ae.is(selector)) return;

        if (!$ae.length || $ae.is('body')) {
          doc.body.focus();
          return cy.realPress(keys).then(step);
        }

        if (count++ >= maxTabs) {
          throw new Error(
            `Reached ${maxTabs} tabs without focusing ${selector}`,
          );
        }

        return cy.realPress(keys).then(() => {
          // $ae should no longer be focused.
          cy.get($ae).should('not.have.focus');
          step();
        });
      });
    };

    step();
  }
  /**
   * @todo Remove the `.skip` when keyboard navigation tests are finished.
   */
  it('creates a basic layout with keyboard navigation', () => {
    cy.visit('node/add/lp_test_ct');
    cy.get('body').realPress('Tab');
    pressUntil('Tab', 'input[name="title[0][value]"]');
    cy.realType('Keyboard Navigation Test');
    pressUntil('Tab', '.lpb-btn--add');
    cy.get('.lpb-btn--add').should('have.focus');
    cy.realPress('Enter');
    cy.get('.lpb-component-list').should('exist');
    cy.get('.ajax-progress').should('not.exist');
    cy.wait(300); // Wait for dialog to be fully interactive
    // Tab to the Section link specifically
    cy.get('.type-lp_test_section a').contains('Section').should('be.visible');
    pressUntil('Tab', '.type-lp_test_section a');
    cy.realPress('Enter');
    cy.get('legend').contains('Choose a layout').should('exist');
    cy.realPress('ArrowRight');
    cy.get('.ajax-progress').should('not.exist');
    cy.realPress('ArrowRight');
    cy.get('.ajax-progress').should('not.exist');
    cy.realPress('ArrowRight');
    cy.get('.ajax-progress').should('not.exist');
    cy.get(`input[value="layout_threecol_25_50_25"]`).should('have.focus');
    pressUntil('Tab', 'button.lpb-btn--save');
    cy.realPress('Enter');
    cy.get(`[data-layout="layout_threecol_25_50_25"]`).should('exist');
    pressUntil('Tab', '.layout__region--first .lpb-btn--add');
    cy.realPress('Enter');
    // Wait for component chooser dialog
    cy.get('.ui-dialog-title').should('contain', 'Choose a component');
    // Navigate to and select Text component
    cy.get('.type-lp_test_text a').contains('Text').should('be.visible');
    pressUntil('Tab', '.type-lp_test_text a');
    cy.realPress('Enter');
    // Now wait for CKEditor to load
    cy.get('.ck-content').should('exist');
    // Tab until we reach the CKEditor content area
    pressUntil('Tab', '.ck-content');
    cy.realType('Text added via keyboard navigation');
    pressUntil('Tab', 'button.lpb-btn--save');
    cy.realPress('Enter');
    cy.contains('Text added via keyboard navigation').should('be.visible');
    cy.get('.ajax-progress').should('not.exist');
    cy.wait(300); // Wait for dialog to close and UI to stabilize
    pressUntil('Tab', '.layout__region--second .lpb-btn--add');
    cy.realPress('Enter');
    // Wait for component chooser and select Text
    cy.get('.ui-dialog-title').should('contain', 'Choose a component');
    pressUntil('Tab', '.type-lp_test_text a');
    cy.realPress('Enter');
    cy.get('.ck-content').should('exist');
    // Tab until we reach the CKEditor content area
    pressUntil('Tab', '.ck-content');
    cy.realType('Second text added via keyboard navigation');
    pressUntil('Tab', 'button.lpb-btn--save');
    cy.realPress('Enter');
    cy.contains('Second text added via keyboard navigation').should(
      'be.visible',
    );
    cy.get('.ajax-progress').should('not.exist');
    cy.wait(300); // Wait for dialog to close and UI to stabilize
    // Add a third text component via keyboard navigation in the third column.
    pressUntil('Tab', '.layout__region--third .lpb-btn--add');
    cy.realPress('Enter');
    // Wait for component chooser and select Text
    cy.get('.ui-dialog-title').should('contain', 'Choose a component');
    pressUntil('Tab', '.type-lp_test_text a');
    cy.realPress('Enter');
    cy.get('.ck-content').should('exist');
    // Tab until we reach the CKEditor content area
    pressUntil('Tab', '.ck-content');
    cy.realType('Third text added via keyboard navigation');
    pressUntil('Tab', 'button.lpb-btn--save');
    cy.realPress('Enter');
    cy.contains('Third text added via keyboard navigation').should(
      'be.visible',
    );
    cy.get('.ajax-progress').should('not.exist');
    cy.wait(300); // Wait for dialog to close and UI to stabilize

    // Save the page to verify keyboard navigation workflow is complete
    cy.lpSavePage();

    // Verify all three components are present on the saved page
    cy.contains('Text added via keyboard navigation').should('be.visible');
    cy.contains('Second text added via keyboard navigation').should('be.visible');
    cy.contains('Third text added via keyboard navigation').should('be.visible');
  });

  // @todo: Add test for reordering components via keyboard navigation
  // Note: The "move up" button only reorders items within the same region,
  // not between regions. To test reordering:
  // 1. Add multiple components to the same region
  // 2. Use Shift+Tab to navigate to the "move up" button
  // 3. Press Enter to move the component up within that region
  // Example:
  // it('reorders components within a region via keyboard', () => {
  //   // Add two text components to the same region
  //   // Use keyboard to move the second one up
  //   // Verify the order changed within that region
  // });

  // @todo: Add test for alternative approach if keyboard navigation is too flaky
  // This approach uses the helper commands instead of keyboard navigation
  // Example:
  // it('adds components using helper commands', () => {
  //   cy.lpCreateTestPage('Alternative Approach Test');
  //   cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');
  //   cy.lpAddTextComponent('First item', '.layout__region--first .lpb-btn--add');
  //   cy.lpAddTextComponent('Second item', '.layout__region--second .lpb-btn--add');
  //   cy.lpAddTextComponent('Third item', '.layout__region--third .lpb-btn--add');
  //   cy.lpSavePage();
  //   cy.contains('First item').should('be.visible');
  //   cy.contains('Second item').should('be.visible');
  //   cy.contains('Third item').should('be.visible');
  // });
});
