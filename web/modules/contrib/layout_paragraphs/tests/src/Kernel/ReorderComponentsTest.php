<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_paragraphs\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests reordering the components of a layout.
 *
 * @group layout_paragraphs
 */
#[RunTestsInSeparateProcesses]
#[Group('layout_paragraphs')]
class ReorderComponentsTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installSchema('node', ['node_access']);

    NodeType::create(['type' => 'lp_test', 'name' => 'LP Test'])->save();

    ParagraphsType::create(['id' => 'lp_section', 'label' => 'Section'])->save();
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
  }

  /**
   * Tests that components missing from the new order are kept.
   *
   * The builder posts the components it rendered. A component in a region
   * its section's layout does not have is not rendered, so it is missing
   * from the order.
   */
  public function testComponentsMissingFromOrderAreKept() {
    $section = Paragraph::create(['type' => 'lp_section']);
    $section->setBehaviorSettings('layout_paragraphs', [
      'layout' => 'layout_twocol',
      'config' => [],
    ]);
    $section->save();

    $components = [];
    foreach (['first' => 'first', 'second' => 'first', 'stranded' => 'third'] as $key => $region) {
      $components[$key] = Paragraph::create(['type' => 'lp_text']);
      $components[$key]->setBehaviorSettings('layout_paragraphs', [
        'parent_uuid' => $section->uuid(),
        'region' => $region,
      ]);
      $components[$key]->save();
    }

    $node = Node::create([
      'type' => 'lp_test',
      'title' => 'Test node',
      'field_lp' => [
        ['entity' => $section],
        ['entity' => $components['first']],
        ['entity' => $components['second']],
        ['entity' => $components['stranded']],
      ],
    ]);
    $node->save();

    $layout = new LayoutParagraphsLayout($node->field_lp);
    $layout->reorderComponents([
      ['uuid' => $section->uuid(), 'parentUuid' => NULL, 'region' => NULL],
      ['uuid' => $components['second']->uuid(), 'parentUuid' => $section->uuid(), 'region' => 'first'],
      ['uuid' => $components['first']->uuid(), 'parentUuid' => $section->uuid(), 'region' => 'first'],
    ]);
    $node->save();

    $node = Node::load($node->id());
    $uuids = array_map(fn ($item) => $item->entity->uuid(), iterator_to_array($node->field_lp));
    $this->assertSame([
      $section->uuid(),
      $components['second']->uuid(),
      $components['first']->uuid(),
      $components['stranded']->uuid(),
    ], array_values($uuids));

    $stranded = (new LayoutParagraphsLayout($node->field_lp))
      ->getComponentByUuid($components['stranded']->uuid());
    $this->assertSame($section->uuid(), $stranded->getParentUuid());
    $this->assertSame('third', $stranded->getRegion());
  }

}
