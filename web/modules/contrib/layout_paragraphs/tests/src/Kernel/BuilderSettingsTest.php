<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_paragraphs\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Render\RenderContext;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_paragraphs\Form\LayoutParagraphsBuilderSettingsForm;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the builder settings.
 *
 * @group layout_paragraphs
 */
#[RunTestsInSeparateProcesses]
#[Group('layout_paragraphs')]
class BuilderSettingsTest extends KernelTestBase {

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

    $this->setUpCurrentUser([], [], TRUE);
  }

  /**
   * Tests that double-click to edit is enabled by default.
   */
  public function testDoubleClickToEditDefault() {
    $this->assertTrue($this->config('layout_paragraphs.builder_settings')->get('double_click_to_edit'));
    $this->assertTrue($this->builderSettings()['double_click_to_edit']);
  }

  /**
   * Tests turning double-click to edit off in the settings form.
   */
  public function testSettingsForm() {
    $form_state = (new FormState())->setValues(['double_click_to_edit' => NULL]);
    $this->container->get('form_builder')->submitForm(LayoutParagraphsBuilderSettingsForm::class, $form_state);

    $this->assertSame([], $form_state->getErrors());
    $this->assertFalse($this->config('layout_paragraphs.builder_settings')->get('double_click_to_edit'));
    $this->assertFalse($this->builderSettings()['double_click_to_edit']);
  }

  /**
   * Tests that the builder varies by the builder settings.
   */
  public function testBuilderCacheability() {
    $this->assertContains('config:layout_paragraphs.builder_settings', $this->buildBuilder()['#cache']['tags']);
  }

  /**
   * Tests that the post update enables double-click to edit.
   */
  public function testPostUpdate() {
    $this->config('layout_paragraphs.builder_settings')->delete();
    $this->container->get('module_handler')->loadInclude('layout_paragraphs', 'php', 'layout_paragraphs.post_update');

    layout_paragraphs_post_update_builder_settings();

    $this->assertTrue($this->config('layout_paragraphs.builder_settings')->get('double_click_to_edit'));
  }

  /**
   * Builds the builder element for a node with one component.
   *
   * @return array
   *   The pre-rendered builder element.
   */
  protected function buildBuilder(): array {
    $paragraph = Paragraph::create(['type' => 'lp_text']);
    $paragraph->save();
    $node = Node::create([
      'type' => 'lp_test',
      'title' => 'Test node',
      'field_lp' => [['entity' => $paragraph]],
    ]);
    $node->save();

    $layout = new LayoutParagraphsLayout($node->field_lp);
    $builder = $this->container->get('plugin.manager.element_info')->createInstance('layout_paragraphs_builder');
    return $this->container->get('renderer')->executeInRenderContext(new RenderContext(), fn () => $builder->preRender([
      '#layout_paragraphs_layout' => $layout,
      '#uuid' => NULL,
      '#is_translating' => NULL,
    ]));
  }

  /**
   * Returns the builder settings passed to drupalSettings.
   *
   * @return array
   *   The builder settings.
   */
  protected function builderSettings(): array {
    $element = $this->buildBuilder();
    return $element['#attached']['drupalSettings']['lpBuilder'][$element['#attributes']['data-lpb-id']];
  }

}
