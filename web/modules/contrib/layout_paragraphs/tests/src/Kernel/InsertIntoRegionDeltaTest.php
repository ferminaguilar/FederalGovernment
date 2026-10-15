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
 * Tests that inserting a component into a region sets a correct delta.
 *
 * @group layout_paragraphs
 */
#[RunTestsInSeparateProcesses]
#[Group('layout_paragraphs')]
class InsertIntoRegionDeltaTest extends KernelTestBase {

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
   * Tests inserting a component into a nested layout's empty region.
   *
   * The reported bug (#3589578): a paragraph added into a region of a
   * layout that is itself nested inside another layout was always spliced
   * at delta 0 of the entire field, rather than being positioned near its
   * parent component.
   */
  public function testInsertIntoNestedRegionSetsCorrectDelta() {
    // A sibling paragraph that should remain first (delta 0) throughout.
    $leading_text = Paragraph::create(['type' => 'lp_text']);
    $leading_text->save();

    // An outer section...
    $outer_section = Paragraph::create(['type' => 'lp_section']);
    $outer_section->setBehaviorSettings('layout_paragraphs', [
      'layout' => 'layout_onecol',
      'config' => [],
    ]);
    $outer_section->save();

    // ...containing an inner (nested) section.
    $inner_section = Paragraph::create(['type' => 'lp_section']);
    $inner_section->setBehaviorSettings('layout_paragraphs', [
      'layout' => 'layout_onecol',
      'config' => [],
      'parent_uuid' => $outer_section->uuid(),
      'region' => 'content',
    ]);
    $inner_section->save();

    $node = Node::create([
      'type' => 'lp_test',
      'title' => 'Test node',
      'field_lp' => [
        ['entity' => $leading_text],
        ['entity' => $outer_section],
        ['entity' => $inner_section],
      ],
    ]);
    $node->save();

    $layout = new LayoutParagraphsLayout($node->field_lp);

    $new_text = Paragraph::create(['type' => 'lp_text']);
    $new_text->save();

    // Insert the new paragraph into the inner section's (empty) region.
    $layout->insertIntoRegion($inner_section->uuid(), 'content', $new_text);
    $node->save();

    $node = Node::load($node->id());
    $deltas = [];
    foreach ($node->field_lp as $delta => $item) {
      $deltas[$item->entity->uuid()] = $delta;
    }

    $this->assertSame(0, $deltas[$leading_text->uuid()], 'Unrelated leading paragraph remains at delta 0.');
    $this->assertGreaterThan(
      $deltas[$inner_section->uuid()],
      $deltas[$new_text->uuid()],
      'New paragraph inserted into a nested region is positioned after its parent section, not spliced to delta 0.'
    );
  }

}
