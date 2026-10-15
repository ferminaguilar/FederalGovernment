<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_paragraphs\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests block plugin discovery when Layout Builder is not installed.
 *
 * @group layout_paragraphs
 */
#[RunTestsInSeparateProcesses]
#[Group('layout_paragraphs')]
class BlockDiscoveryWithoutLayoutBuilderTest extends KernelTestBase {

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
   * Tests that the block definitions can be built.
   */
  public function testBlockDefinitions() {
    $definitions = $this->container->get('plugin.manager.block')->getDefinitions();
    $this->assertArrayHasKey('system_main_block', $definitions);
    $this->assertEmpty(array_filter(array_keys($definitions), fn ($id) => str_starts_with($id, 'field_block:')));
  }

}
