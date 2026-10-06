Cypress.config('defaultCommandTimeout', 10000);

/**
 * Helpers for creating temporary users with specific permissions.
 *
 * Each helper creates a uniquely-named role + user pair, runs the provided
 * callback, and then cleans up after itself.
 */

/**
 * Creates a Drupal role with the given permissions and a user assigned to it.
 * Returns the uid of the newly created user via the yielded value.
 *
 * @param {string} roleId   Machine-safe role ID (must be unique per test run).
 * @param {string[]} perms  Array of permission strings to grant to the role.
 * @param {string} username Username for the test account.
 * @param {string} password Password for the test account.
 */
function createUserWithPerms(roleId, perms, username, password) {
  // Build PHP using double-quoted strings only so the code can be safely
  // wrapped in shell single quotes (which prevent zsh from interpreting
  // PHP brackets, parentheses, or dollar signs as shell syntax).
  const permList = perms.map((p) => `"${p}"`).join(', ');
  const php = [
    `if (!\\Drupal\\user\\Entity\\Role::load("${roleId}")) {`,
    `  $r = \\Drupal\\user\\Entity\\Role::create(["id" => "${roleId}", "label" => "${roleId}"]);`,
    `  foreach ([${permList}] as $p) { $r->grantPermission($p); }`,
    `  $r->save();`,
    `}`,
    // Delete any existing user with this username to avoid duplicate-key errors.
    `$existing = \\Drupal::entityTypeManager()->getStorage("user")->loadByProperties(["name" => "${username}"]);`,
    `if ($existing) { reset($existing)->delete(); }`,
    `$u = \\Drupal\\user\\Entity\\User::create(["name" => "${username}", "status" => 1]);`,
    `$u->setPassword("${password}");`,
    `$u->addRole("${roleId}");`,
    `$u->save();`,
    `echo $u->id();`,
  ].join(' ');

  // Execute via drush php:eval wrapped in shell single quotes.
  const execCommand = Cypress.env('drushCommand').replace(
    '$COMMAND',
    `php:eval '${php}'`,
  );
  return cy.exec(execCommand).its('stdout').then((uid) => uid.trim());
}

describe('Layout Paragraphs Permissions Tests', () => {
  before(() => {
    cy.drush(
      'en layout_paragraphs_setup_test layout_paragraphs_permissions',
    );
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
    cy.drush('cr');
    cy.viewport(1000, 800);
  });

  after(() => {
    // Remove test roles created during this suite.
    const phpEval = (code) => {
      const cmd = Cypress.env('drushCommand').replace(
        '$COMMAND',
        `php:eval '${code}'`,
      );
      return cy.exec(cmd);
    };
    phpEval('foreach (["lp_no_reorder", "lp_with_reorder"] as $rid) { $r = \\Drupal\\user\\Entity\\Role::load($rid); if ($r) { $r->delete(); } }');
    cy.drush('cr');
  });

  it('hides drag/reorder controls when the user lacks the reorder permission', () => {
    createUserWithPerms(
      'lp_no_reorder',
      [
        'access content overview',
        'create lp_test_ct content',
        'edit any lp_test_ct content',
      ],
      'lp_test_no_reorder',
      'TestPass123!',
    ).then((uid) => {
      // Create an lp_test_ct node as admin first so there is something to edit.
      cy.loginUserByUid(1);
      cy.lpCreateTestPage('Permissions No Reorder Test', 'lp_test_ct');
      cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');
      cy.lpSavePage();

      // Now log in as the restricted user and visit the edit page.
      cy.loginAs('lp_test_no_reorder', 'TestPass123!');
      cy.visit('/admin/content');
      cy.get('a[aria-label="Edit Permissions No Reorder Test"]')
        .first()
        .scrollIntoView()
        .click();

      // Without the reorder permission the drag/up/down controls must be absent.
      cy.get('.lpb-drag').should('not.exist');
      cy.get('.lpb-up').should('not.exist');
      cy.get('.lpb-down').should('not.exist');

      // Direct access to the reorder route should be denied.
      cy.get('.lp-builder')
        .invoke('attr', 'data-lpb-id')
        .then((builderId) => {
          cy.request({
            url: `/layout-paragraphs-builder/${builderId}/reorder`,
            failOnStatusCode: false,
          }).then((response) => {
            expect(response.status).to.equal(403);
          });
        });
    });
  });

  it('shows drag/reorder controls when the user has the reorder permission', () => {
    createUserWithPerms(
      'lp_with_reorder',
      [
        'access content overview',
        'create lp_test_ct content',
        'edit any lp_test_ct content',
        'reorder layout paragraphs components',
      ],
      'lp_test_with_reorder',
      'TestPass123!',
    ).then(() => {
      // Create an lp_test_ct node as admin first.
      cy.loginUserByUid(1);
      cy.lpCreateTestPage('Permissions With Reorder Test', 'lp_test_ct');
      cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');
      cy.lpSavePage();

      // Log in as the user that has the reorder permission.
      cy.loginAs('lp_test_with_reorder', 'TestPass123!');
      cy.visit('/admin/content');
      cy.get('a[aria-label="Edit Permissions With Reorder Test"]')
        .first()
        .scrollIntoView()
        .click();

      // With the permission the controls must be present.
      cy.get('.lpb-drag').should('exist');
      cy.get('.lpb-up').should('exist');
      cy.get('.lpb-down').should('exist');

      // The reorder route should be accessible as this user (who owns the
      // builder session and has the reorder permission).
      cy.get('.lp-builder')
        .invoke('attr', 'data-lpb-id')
        .then((builderId) => {
          cy.request(`/layout-paragraphs-builder/${builderId}/reorder`).then(
            (response) => {
              expect(response.status).to.equal(200);
            },
          );
        });
    });
  });
});

describe('Layout Paragraphs Complex Permissions Tests', () => {
  before(() => {
    cy.drush(
      'en layout_paragraphs_setup_test layout_paragraphs_permissions layout_paragraphs_complex_permissions_test',
    );
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
    cy.drush('cr');
    cy.viewport(1000, 800);
  });

  after(() => {
    const phpEval = (code) => {
      const cmd = Cypress.env('drushCommand').replace(
        '$COMMAND',
        `php:eval '${code}'`,
      );
      return cy.exec(cmd);
    };
    phpEval('foreach (["lp_ct_reorder"] as $rid) { $r = \\Drupal\\user\\Entity\\Role::load($rid); if ($r) { $r->delete(); } }');
    cy.drush('pmu layout_paragraphs_complex_permissions_test');
    cy.drush('cr');
  });

  it('grants reorder access per content type', () => {
    // Create a user who can reorder lp_test_ct content but not (e.g.) basic pages.
    createUserWithPerms(
      'lp_ct_reorder',
      [
        'access content overview',
        'create lp_test_ct content',
        'edit any lp_test_ct content',
        'reorder layout paragraph components for lp_test_ct content',
      ],
      'lp_ct_reorder_user',
      'TestPass123!',
    ).then(() => {
      // Create test content as admin.
      cy.loginUserByUid(1);
      cy.lpCreateTestPage('Complex Permissions Test', 'lp_test_ct');
      cy.lpAddSection('layout_threecol_25_50_25', '.lpb-btn--add');
      cy.lpSavePage();

      // The limited user should see drag/reorder controls on lp_test_ct content.
      cy.loginAs('lp_ct_reorder_user', 'TestPass123!');
      cy.visit('/admin/content');
      cy.get('a[aria-label="Edit Complex Permissions Test"]')
        .first()
        .scrollIntoView()
        .click();

      cy.get('.lpb-drag').should('exist');
      cy.get('.lpb-up').should('exist');
      cy.get('.lpb-down').should('exist');
    });
  });
});
