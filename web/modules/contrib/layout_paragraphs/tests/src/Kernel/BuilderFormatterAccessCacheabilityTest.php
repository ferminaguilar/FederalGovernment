<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_paragraphs\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the cacheability of the builder formatter's access check.
 *
 * @group layout_paragraphs
 */
#[RunTestsInSeparateProcesses]
#[Group('layout_paragraphs')]
class BuilderFormatterAccessCacheabilityTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'entity_reference_revisions',
    'paragraphs',
    'file',
    'node',
    'layout_discovery',
    'layout_paragraphs',
  ];

  /**
   * The node rendered with the builder formatter.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['layout_paragraphs']);

    NodeType::create(['type' => 'lp_test', 'name' => 'LP Test'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_lp',
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'cardinality' => -1,
      'settings' => ['target_type' => 'paragraph'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_lp',
      'entity_type' => 'node',
      'bundle' => 'lp_test',
    ])->save();

    // Uid 1 bypasses access checks, so take it before the test users.
    $this->createUser();
    $author = $this->createUser(['access content', 'edit own lp_test content']);
    $this->node = Node::create([
      'type' => 'lp_test',
      'title' => 'Test node',
      'uid' => $author->id(),
    ]);
    $this->node->save();
    $this->setCurrentUser($author);
  }

  /**
   * Tests that the builder carries the cacheability of its access check.
   */
  public function testAllowedBuildVariesByAccess() {
    $build = $this->node->field_lp->view(['type' => 'layout_paragraphs_builder']);

    $this->assertContains('lpb-formatter', $build['#attributes']['class']);
    $this->assertContains('user', $build['#cache']['contexts']);
    $this->assertContains('node:' . $this->node->id(), $build['#cache']['tags']);
  }

  /**
   * Tests that the build without the builder varies by access too.
   */
  public function testDeniedBuildVariesByAccess() {
    $this->setCurrentUser($this->createUser(['access content', 'edit own lp_test content']));
    $build = $this->node->field_lp->view(['type' => 'layout_paragraphs_builder']);

    $this->assertArrayNotHasKey('#attributes', $build);
    $this->assertContains('user', $build['#cache']['contexts']);
    $this->assertContains('node:' . $this->node->id(), $build['#cache']['tags']);
  }

}
