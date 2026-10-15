<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_paragraphs\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\SchemaCheckTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the config schema of the builder formatter settings.
 *
 * @group layout_paragraphs
 */
#[RunTestsInSeparateProcesses]
#[Group('layout_paragraphs')]
class BuilderFormatterConfigSchemaTest extends KernelTestBase {

  use SchemaCheckTestTrait;

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
  }

  /**
   * Tests that the default builder formatter settings match the schema.
   */
  public function testDefaultSettingsMatchSchema() {
    EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'lp_test',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('field_lp', ['type' => 'layout_paragraphs_builder'])
      ->save();

    $config_name = 'core.entity_view_display.node.lp_test.default';
    $this->assertConfigSchemaByName($config_name);
    $settings = $this->config($config_name)->get('content.field_lp.settings');
    $this->assertTrue($settings['conversion']);
  }

}
