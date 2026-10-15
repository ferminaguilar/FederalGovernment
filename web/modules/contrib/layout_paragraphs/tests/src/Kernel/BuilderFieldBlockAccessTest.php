<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_paragraphs\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_paragraphs\Plugin\Block\LayoutParagraphsFieldBlock;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Layout Builder field block for the builder formatter.
 *
 * @group layout_paragraphs
 */
#[RunTestsInSeparateProcesses]
#[Group('layout_paragraphs')]
class BuilderFieldBlockAccessTest extends KernelTestBase {

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
    'layout_builder',
    'layout_paragraphs',
  ];

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
    ParagraphsType::create(['id' => 'lp_text', 'label' => 'Text'])->save();
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

    LayoutBuilderEntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'lp_test',
      'mode' => 'default',
      'status' => TRUE,
    ])->enableLayoutBuilder()->save();

    // Uid 1 bypasses access checks, so take it before the test users.
    $this->createUser();
  }

  /**
   * Tests that the field block class is replaced for the builder's fields.
   */
  public function testFieldBlockClass() {
    $definition = $this->container->get('plugin.manager.block')->getDefinition('field_block:node:lp_test:field_lp');
    $this->assertSame(LayoutParagraphsFieldBlock::class, $definition['class']);
    $definition = $this->container->get('plugin.manager.block')->getDefinition('field_block:node:lp_test:title');
    $this->assertNotSame(LayoutParagraphsFieldBlock::class, $definition['class']);
  }

  /**
   * Tests access to the field block when the field is empty.
   */
  public function testEmptyFieldAccess() {
    $editor = $this->createUser(['access content', 'edit any lp_test content']);
    $viewer = $this->createUser(['access content']);
    $node = Node::create(['type' => 'lp_test', 'title' => 'Empty']);
    $node->save();

    $block = $this->createFieldBlock('layout_paragraphs_builder', $node);
    $this->assertTrue($block->access($editor));
    $this->assertFalse($block->access($viewer));

    $block = $this->createFieldBlock('entity_reference_revisions_entity_view', $node);
    $this->assertFalse($block->access($editor));
  }

  /**
   * Tests access to the field block when the field has a value.
   */
  public function testFilledFieldAccess() {
    $viewer = $this->createUser(['access content']);
    $paragraph = Paragraph::create(['type' => 'lp_text']);
    $paragraph->save();
    $node = Node::create([
      'type' => 'lp_test',
      'title' => 'Filled',
      'field_lp' => [['entity' => $paragraph]],
    ]);
    $node->save();

    $block = $this->createFieldBlock('layout_paragraphs_builder', $node);
    $this->assertTrue($block->access($viewer));
  }

  /**
   * Creates the field block for field_lp with a formatter.
   *
   * @param string $formatter
   *   The formatter plugin ID.
   * @param \Drupal\node\Entity\Node $node
   *   The node the block renders.
   *
   * @return \Drupal\Core\Block\BlockPluginInterface
   *   The field block.
   */
  protected function createFieldBlock(string $formatter, Node $node) {
    $block = $this->container->get('plugin.manager.block')->createInstance('field_block:node:lp_test:field_lp', [
      'formatter' => ['type' => $formatter],
    ]);
    $block->setContextValue('entity', $node);
    return $block;
  }

}
