<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_paragraphs\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Render\RenderContext;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_paragraphs\Form\EditComponentForm;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests switching a section to a layout with fewer regions.
 *
 * @group layout_paragraphs
 */
#[RunTestsInSeparateProcesses]
#[Group('layout_paragraphs')]
class LayoutSwitchOrphansTest extends KernelTestBase {

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
   * The section paragraph.
   *
   * @var \Drupal\paragraphs\ParagraphInterface
   */
  protected $section;

  /**
   * The components of the section, keyed by region.
   *
   * @var \Drupal\paragraphs\ParagraphInterface[]
   */
  protected $components = [];

  /**
   * The layout of the node.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsLayout
   */
  protected $layout;

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
    $this->setUpCurrentUser([], [], TRUE);

    NodeType::create(['type' => 'lp_test', 'name' => 'LP Test'])->save();
    ParagraphsType::create([
      'id' => 'lp_section',
      'label' => 'Section',
      'behavior_plugins' => [
        'layout_paragraphs' => [
          'enabled' => TRUE,
          'available_layouts' => [
            'layout_twocol_section' => 'Two column',
            'layout_threecol_section' => 'Three column',
          ],
        ],
      ],
    ])->save();
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

    $this->section = Paragraph::create(['type' => 'lp_section']);
    $this->section->setBehaviorSettings('layout_paragraphs', [
      'layout' => 'layout_threecol_section',
      'config' => ['column_widths' => '33-34-33'],
    ]);
    $this->section->save();
    $items = [['entity' => $this->section]];
    foreach (['first', 'second', 'third'] as $region) {
      $this->components[$region] = Paragraph::create(['type' => 'lp_text']);
      $this->components[$region]->setBehaviorSettings('layout_paragraphs', [
        'parent_uuid' => $this->section->uuid(),
        'region' => $region,
      ]);
      $this->components[$region]->save();
      $items[] = ['entity' => $this->components[$region]];
    }
    $node = Node::create(['type' => 'lp_test', 'title' => 'Test', 'field_lp' => $items]);
    $node->save();
    $this->layout = new LayoutParagraphsLayout($node->field_lp, ['form_display_mode' => 'default']);
  }

  /**
   * Tests that the previous layout's config values are dropped.
   */
  public function testStaleConfigValuesAreDropped() {
    $form_state = new FormState();
    $form_state->setUserInput([
      'layout_paragraphs' => [
        'layout' => 'layout_twocol_section',
        'config' => ['column_widths' => '33-34-33'],
      ],
    ]);
    $form = ['#parents' => ['layout_paragraphs']];
    $this->section->getParagraphType()
      ->getBehaviorPlugin('layout_paragraphs')
      ->buildBehaviorForm($this->section, $form, $form_state);

    $this->assertSame('50-50', $form['config']['column_widths']['#default_value']);
    $this->assertArrayNotHasKey('column_widths', $form_state->getUserInput()['layout_paragraphs']['config']);
  }

  /**
   * Tests the orphaned items element when the form has no values.
   */
  public function testOrphanedItemsElementFromInput() {
    $form_object = $this->createEditForm();
    $form_state = new FormState();
    $form_state->setUserInput([
      'layout_paragraphs' => ['layout' => 'layout_twocol_section'],
    ]);
    $form = ['layout_paragraphs' => ['#type' => 'container']];
    $form_object->orphanedItemsElement($form, $form_state);

    $this->assertArrayHasKey('third', $form['layout_paragraphs']['move_items']);
    $this->assertArrayNotHasKey('first', $form['layout_paragraphs']['move_items']);
  }

  /**
   * Tests that saving a section moves components out of missing regions.
   */
  public function testStrandedComponentsAreMoved() {
    $this->section->setBehaviorSettings('layout_paragraphs', [
      'layout' => 'layout_twocol_section',
      'config' => ['column_widths' => '50-50'],
    ]);
    $this->layout->setComponent($this->section);
    $this->container->get('layout_paragraphs.tempstore_repository')->set($this->layout);

    $form_state = (new FormState())
      ->addBuildInfo('args', [$this->layout, $this->section->uuid()])
      ->setValues([
        'layout_paragraphs' => [
          'layout' => 'layout_twocol_section',
          'config' => ['column_widths' => '50-50'],
        ],
      ]);
    $this->container->get('renderer')->executeInRenderContext(new RenderContext(), function () use ($form_state) {
      $this->container->get('form_builder')->submitForm($this->createEditForm(), $form_state);
    });
    $this->assertEmpty($form_state->getErrors());

    $layout = $this->container->get('layout_paragraphs.tempstore_repository')->get($this->layout);
    $this->assertSame('first', $layout->getComponentByUuid($this->components['first']->uuid())->getRegion());
    $this->assertSame('second', $layout->getComponentByUuid($this->components['second']->uuid())->getRegion());
    $this->assertSame('first', $layout->getComponentByUuid($this->components['third']->uuid())->getRegion());
  }

  /**
   * Creates the edit form for the section.
   *
   * @return \Drupal\layout_paragraphs\Form\EditComponentForm
   *   The edit component form.
   */
  protected function createEditForm() {
    $form_object = $this->container->get('class_resolver')->getInstanceFromDefinition(EditComponentForm::class);
    $form_object->setLayoutParagraphsLayout($this->layout);
    $form_object->setParagraph($this->section);
    return $form_object;
  }

}
