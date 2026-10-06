Cypress.config('defaultCommandTimeout', 15000);

describe('Layout Paragraphs Workspaces Compatibility Tests', () => {
  const workspaceId = 'lp_test_workspace';
  const workspaceLabel = 'LP Test Workspace';

  /** Build a drush php:eval exec command with failOnNonZeroExit: false. */
  function phpExec(code) {
    const cmd = Cypress.env('drushCommand').replace(
      '$COMMAND',
      `php:eval '${code}'`,
    );
    return cy.exec(cmd, { failOnNonZeroExit: false });
  }

  before(() => {
    cy.drush('en layout_paragraphs_setup_test workspaces workspaces_ui');
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct']);
    // Clean up any leftover workspace from a previous incomplete test run.
    phpExec(`$ws = \\Drupal::entityTypeManager()->getStorage("workspace")->load("${workspaceId}"); if ($ws) { $ws->delete(); }`);
    cy.drush('cr');
    cy.viewport(1000, 800);
  });

  after(() => {
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct']);
    // Delete the test workspace entity, ignoring failures if the workspace
    // does not exist (e.g., a previous partial run already deleted it).
    phpExec(`$ws = \\Drupal::entityTypeManager()->getStorage("workspace")->load("${workspaceId}"); if ($ws) { $ws->delete(); }`);
    // Disable only the UI module; the base workspaces module can remain enabled
    // between test runs without affecting other tests (no active workspace = live context).
    cy.drush('pmu workspaces_ui');
    cy.drush('cr');
  });

  beforeEach(() => {
    cy.loginUserByUid(1);
  });

  it('creates a workspace, adds LP content inside it, and publishes without errors', () => {
    // --- Step 1: Create and activate a new workspace ---
    cy.visit('/admin/config/workflow/workspaces/add');
    cy.get('#edit-label').type(workspaceLabel);
    // Wait for the machine-name widget to auto-populate from the label.
    cy.get('#edit-id').should('have.value', workspaceId);
    // "Save and switch" creates the workspace and immediately activates it.
    cy.contains('input', 'Save and switch').click();
    cy.contains(`${workspaceLabel} is now the active workspace`).should('be.visible');

    // --- Step 2: Create Layout Paragraphs content inside the workspace ---
    cy.lpCreateTestPage('Workspace Test Page', 'lp_test_ct');
    cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');
    cy.lpAddTextComponent(
      'Content created inside a workspace',
      '.layout__region--first .lpb-btn--add',
    );
    cy.lpSavePage();
    cy.contains('Workspace Test Page').should('be.visible');
    cy.contains('Content created inside a workspace').should('be.visible');
    // Verify no Drupal error messages appeared after saving.
    cy.get('.messages--error').should('not.exist');

    // --- Step 3: Publish the workspace to promote content to Live ---
    cy.visit(`/admin/config/workflow/workspaces/manage/${workspaceId}/publish`);
    // The submit button label is dynamic ("Publish N items to Live")
    // so target it by its form element ID.
    cy.get('[data-drupal-selector="edit-submit"]').should('not.be.disabled').click();
    cy.contains('Successful publication').should('be.visible');
    // Verify no Drupal error messages appeared after publishing.
    cy.get('.messages--error').should('not.exist');
  });
});
