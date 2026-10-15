Cypress.config('defaultCommandTimeout', 15000);

describe('Layout Paragraphs Builder Formatter Access Cache Tests', () => {
  function phpEval(code) {
    const cmd = Cypress.env('drushCommand').replace(
      '$COMMAND',
      `php:eval '${code}'`,
    );
    return cy.exec(cmd);
  }

  function createEditor(username) {
    return phpEval(
      `$existing = \\Drupal::entityTypeManager()->getStorage("user")->loadByProperties(["name" => "${username}"]);`
      + ' if ($existing) { reset($existing)->delete(); }'
      + ` $u = \\Drupal\\user\\Entity\\User::create(["name" => "${username}", "status" => 1]);`
      + ` $u->setPassword("${username}");`
      + ' $u->addRole("lp_cache_editor");'
      + ' $u->save();'
      + ' echo $u->id();',
    )
      .its('stdout')
      .then((uid) => uid.trim());
  }

  before(() => {
    cy.drush('en layout_paragraphs_setup_test');
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
    phpEval(
      'if (!\\Drupal\\user\\Entity\\Role::load("lp_cache_editor")) {'
      + ' $r = \\Drupal\\user\\Entity\\Role::create(["id" => "lp_cache_editor", "label" => "lp_cache_editor"]);'
      + ' $r->grantPermission("access content");'
      + ' $r->grantPermission("edit own lp_test_ct content");'
      + ' $r->save(); }',
    );
    phpEval(
      '$d = \\Drupal\\Core\\Entity\\Entity\\EntityViewDisplay::load("node.lp_test_ct.default");'
      + ' $d->setComponent("field_lp_test_content", ["type" => "layout_paragraphs_builder"])->save();',
    );
    cy.drush('cr');
    cy.viewport(1000, 800);
  });

  after(() => {
    phpEval(
      '$d = \\Drupal\\Core\\Entity\\Entity\\EntityViewDisplay::load("node.lp_test_ct.default");'
      + ' $d->setComponent("field_lp_test_content", ["type" => "layout_paragraphs"])->save();',
    );
    cy.drush('entity:delete', ['node', '--bundle=lp_test_ct', '-y']);
    phpEval(
      'foreach (["lp_cache_author", "lp_cache_other"] as $name) {'
      + ' foreach (\\Drupal::entityTypeManager()->getStorage("user")->loadByProperties(["name" => $name]) as $u) { $u->delete(); } }'
      + ' \\Drupal\\user\\Entity\\Role::load("lp_cache_editor")?->delete();',
    );
    cy.drush('cr');
  });

  it('does not show the builder to an editor who cannot edit the node', () => {
    createEditor('lp_cache_author').then((authorUid) => {
      createEditor('lp_cache_other');
      phpEval(
        '$n = \\Drupal\\node\\Entity\\Node::create(["type" => "lp_test_ct", "title" => "Access Cache Test",'
        + ` "uid" => ${authorUid}]); $n->save(); echo $n->id();`,
      )
        .its('stdout')
        .then((stdout) => {
          const nid = stdout.trim();

          cy.loginAs('lp_cache_author', 'lp_cache_author');
          cy.visit(`/node/${nid}`);
          cy.get('.lpb-formatter').should('exist');

          cy.loginAs('lp_cache_other', 'lp_cache_other');
          cy.visit(`/node/${nid}`);
          cy.contains('h1', 'Access Cache Test').should('exist');
          cy.get('.lpb-formatter').should('not.exist');
        });
    });
  });
});
