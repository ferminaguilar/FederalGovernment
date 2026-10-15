<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_paragraphs\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityFormMode;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_paragraphs\Form\EditComponentForm;
use Drupal\layout_paragraphs\Form\InsertComponentForm;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the entity form methods of the component forms.
 *
 * @group layout_paragraphs
 */
#[RunTestsInSeparateProcesses]
#[Group('layout_paragraphs')]
class ComponentFormEntityApiTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'entity_reference_revisions',
    'paragraphs',
    'file',
    'node',
    'layout_discovery',
    'layout_paragraphs',
  ];

  /**
   * The layout the component forms are built for.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsLayout
   */
  protected $layout;

  /**
   * The paragraph in the layout.
   *
   * @var \Drupal\paragraphs\ParagraphInterface
   */
  protected $paragraph;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['filter', 'layout_paragraphs']);

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

    FieldStorageConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'paragraph',
      'type' => 'text_long',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'paragraph',
      'bundle' => 'lp_text',
    ])->save();

    EntityFormMode::create([
      'id' => 'paragraph.alternative',
      'targetEntityType' => 'paragraph',
      'label' => 'Alternative',
    ])->save();
    EntityFormDisplay::create([
      'targetEntityType' => 'paragraph',
      'bundle' => 'lp_text',
      'mode' => 'alternative',
      'status' => TRUE,
    ])->setComponent('field_text', ['type' => 'text_textarea'])->save();

    $this->paragraph = Paragraph::create(['type' => 'lp_text']);
    $this->paragraph->save();
    $node = Node::create([
      'type' => 'lp_test',
      'title' => 'Test node',
      'field_lp' => [['entity' => $this->paragraph]],
    ]);
    $node->save();

    $this->layout = new LayoutParagraphsLayout($node->field_lp, [
      'form_display_mode' => 'alternative',
    ]);
  }

  /**
   * Tests the entity form methods of the edit component form.
   */
  public function testEditComponentForm() {
    $form_state = $this->buildComponentForm(EditComponentForm::class, [
      $this->layout,
      $this->paragraph->uuid(),
    ]);

    $form_object = $form_state->getFormObject();
    $this->assertSame($this->paragraph->uuid(), $form_object->getEntity()->uuid());
    $this->assertSame('paragraph.lp_text.alternative', $form_object->getFormDisplay($form_state)->id());
  }

  /**
   * Tests the entity form methods of the insert component form.
   */
  public function testInsertComponentForm() {
    $form_state = $this->buildComponentForm(InsertComponentForm::class, [
      $this->layout,
      ParagraphsType::load('lp_text'),
    ]);

    $form_object = $form_state->getFormObject();
    $this->assertTrue($form_object->getEntity()->isNew());
    $this->assertSame('lp_text', $form_object->getEntity()->bundle());
    $this->assertSame('paragraph.lp_text.alternative', $form_object->getFormDisplay($form_state)->id());
  }

  /**
   * Builds a component form.
   *
   * @param string $class
   *   The component form class.
   * @param array $args
   *   The arguments passed to the form's buildForm() method.
   *
   * @return \Drupal\Core\Form\FormStateInterface
   *   The form state of the built form.
   */
  protected function buildComponentForm(string $class, array $args) {
    $form_object = $this->container->get('class_resolver')->getInstanceFromDefinition($class);
    $form_state = (new FormState())->addBuildInfo('args', $args);
    $this->container->get('form_builder')->buildForm($form_object, $form_state);
    return $form_state;
  }

}
