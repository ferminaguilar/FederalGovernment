<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_paragraphs\Kernel;

use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the revision user of a save from the frontend builder.
 *
 * @group layout_paragraphs
 */
#[RunTestsInSeparateProcesses]
#[Group('layout_paragraphs')]
class BuilderRevisionUserTest extends KernelTestBase {

  use UserCreationTrait;
  use ParagraphsTestBaseTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'text',
    'node',
    'file',
    'field',
    'layout_discovery',
    'entity_reference_revisions',
    'paragraphs',
    'layout_paragraphs',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('system', ['sequences']);
    $this->installEntitySchema('user');
    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installConfig(['user']);

    $this->addParagraphsType('text');
    $this->addFieldtoParagraphType('text', 'field_text', 'string');
    $this->addParagraphedContentType('page', 'field_content', 'layout_paragraphs');
    $this->container->get('entity_display.repository')
      ->getViewDisplay('node', 'page')
      ->setComponent('field_content', ['type' => 'layout_paragraphs_builder'])
      ->save();

    // Needed for the 'created' field on the node.
    DateFormat::create([
      'id' => 'fallback',
      'pattern' => 'D, m/d/Y - H:i',
    ])->save();
  }

  /**
   * Tests that a save from the builder records the user who saved.
   */
  public function testRevisionUserIsCurrentUser() {
    // Uid 1 bypasses access checks, so take it before the test users.
    $this->createUser();
    $author = $this->createUser(['access content', 'edit any page content']);
    $editor = $this->createUser(['access content', 'edit any page content']);

    $paragraph = $this->container->get('entity_type.manager')->getStorage('paragraph')->create([
      'type' => 'text',
      'field_text' => 'Text',
    ]);
    $paragraph->save();
    $node = Node::create([
      'type' => 'page',
      'title' => 'Node title',
      'uid' => $author->id(),
      'revision_uid' => $author->id(),
      'field_content' => [$paragraph],
    ]);
    $node->save();

    $this->setCurrentUser($editor);
    $path = '/layout-paragraphs-builder/formatter/node/' . $node->id() . '/field_content/full';
    $this->doRequest(Request::create($path));
    $data = [];
    foreach (['form_build_id', 'form_id', 'form_token', 'layout_paragraphs_storage_key'] as $name) {
      $inputs = $this->cssSelect('input[name="' . $name . '"]');
      $this->assertCount(1, $inputs);
      $data[$name] = (string) $inputs[0]->attributes()->value[0];
    }
    $response = $this->doRequest(Request::create($path, 'POST', $data + ['op' => 'Save']));
    $this->assertEquals(303, $response->getStatusCode());

    $this->container->get('entity_type.manager')->getStorage('node')->resetCache();
    $node = Node::load($node->id());
    $this->assertEquals($editor->id(), $node->getRevisionUserId());
    $this->assertEquals($author->id(), $node->getOwnerId());
  }

  /**
   * Handles a request in the test's HTTP kernel.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  protected function doRequest(Request $request) {
    $this->container->get('entity.memory_cache')->deleteAll();
    $response = $this->container->get('http_kernel')->handle($request);
    $this->setRawContent((string) $response->getContent());
    return $response;
  }

}
