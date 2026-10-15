<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_paragraphs\Kernel;

use Drupal\Component\Serialization\Json;
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
 * Tests the dialog options of the component controls.
 *
 * @group layout_paragraphs
 */
#[RunTestsInSeparateProcesses]
#[Group('layout_paragraphs')]
class DeleteDialogSettingsTest extends KernelTestBase {

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
  }

  /**
   * Tests that the delete dialog uses the modal settings.
   */
  public function testDeleteDialogUsesModalSettings() {
    $this->config('layout_paragraphs.modal_settings')
      ->set('width', '480px')
      ->set('height', '320px')
      ->set('autoresize', FALSE)
      ->save();

    $paragraph = Paragraph::create(['type' => 'lp_text']);
    $paragraph->save();
    $node = Node::create([
      'type' => 'lp_test',
      'title' => 'Test node',
      'field_lp' => [['entity' => $paragraph]],
    ]);
    $node->save();

    $variables = [
      'layout_paragraphs_layout' => new LayoutParagraphsLayout($node->field_lp),
      'uuid' => $paragraph->uuid(),
    ];
    $theme_hooks = layout_paragraphs_theme();
    $variables += $theme_hooks['layout_paragraphs_builder_controls']['variables'];
    layout_paragraphs_preprocess_layout_paragraphs_builder_controls($variables);

    $edit_options = Json::decode($variables['controls']['edit_link']['#attributes']['data-dialog-options']);
    $delete_options = Json::decode($variables['controls']['delete_link']['#attributes']['data-dialog-options']);

    $this->assertSame('480px', $delete_options['width']);
    $this->assertSame('320px', $delete_options['height']);
    $this->assertFalse($delete_options['autoResize']);
    $this->assertSame($edit_options['target'], $delete_options['target']);
    $this->assertSame($edit_options['dialogClass'], $delete_options['dialogClass']);
    $this->assertTrue($delete_options['drupalAutoButtons']);
  }

}
