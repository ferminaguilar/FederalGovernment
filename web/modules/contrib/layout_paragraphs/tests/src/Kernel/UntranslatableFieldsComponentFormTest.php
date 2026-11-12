<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_paragraphs\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\RenderContext;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\layout_paragraphs\Form\EditComponentForm;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests untranslatable fields in the component form while translating.
 *
 * @group layout_paragraphs
 */
#[RunTestsInSeparateProcesses]
#[Group('layout_paragraphs')]
class UntranslatableFieldsComponentFormTest extends KernelTestBase {

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
    'text',
    'filter',
    'language',
    'content_translation',
    'entity_reference_revisions',
    'paragraphs',
    'file',
    'node',
    'layout_discovery',
    'layout_paragraphs',
    'paragraphs_test',
  ];

  /**
   * The paragraph in the layout.
   *
   * @var \Drupal\paragraphs\ParagraphInterface
   */
  protected $paragraph;

  /**
   * The node holding the paragraph.
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
    $this->installConfig(['filter', 'language', 'layout_paragraphs']);
    $this->setUpCurrentUser([], [], TRUE);
    ConfigurableLanguage::createFromLangcode('de')->save();

    NodeType::create(['type' => 'lp_test', 'name' => 'LP Test'])->save();
    ParagraphsType::create([
      'id' => 'lp_text',
      'label' => 'Text',
      'behavior_plugins' => [
        'layout_paragraphs' => [
          'enabled' => TRUE,
          'available_layouts' => ['layout_twocol' => 'Two column'],
        ],
        'test_bold_text' => ['enabled' => TRUE],
      ],
    ])->save();
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
      'translatable' => FALSE,
    ])->save();
    foreach (['field_translatable' => TRUE, 'field_untranslatable' => FALSE] as $field_name => $translatable) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'paragraph',
        'type' => 'string',
      ])->save();
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'paragraph',
        'bundle' => 'lp_text',
        'label' => $field_name,
        'translatable' => $translatable,
      ])->save();
    }
    EntityFormDisplay::create([
      'targetEntityType' => 'paragraph',
      'bundle' => 'lp_text',
      'mode' => 'default',
      'status' => TRUE,
    ])
      ->setComponent('field_translatable', ['type' => 'string_textfield'])
      ->setComponent('field_untranslatable', ['type' => 'string_textfield'])
      ->save();

    $this->paragraph = Paragraph::create([
      'type' => 'lp_text',
      'field_translatable' => 'EN translatable',
      'field_untranslatable' => 'Shared',
    ]);
    $this->paragraph->addTranslation('de', ['field_translatable' => 'DE translatable']);
    $this->paragraph->setBehaviorSettings('layout_paragraphs', ['layout' => 'layout_twocol']);
    $this->paragraph->setBehaviorSettings('test_bold_text', ['bold_text' => 1]);
    $this->paragraph->save();
    $this->node = Node::create([
      'type' => 'lp_test',
      'title' => 'Test',
      'field_lp' => [['entity' => $this->paragraph]],
    ]);
    $this->node->save();
  }

  /**
   * Tests that untranslatable fields are hidden when configured so.
   */
  public function testUntranslatableFieldsHidden() {
    $this->setUntranslatableFieldsHide(TRUE);
    $form = $this->buildEditForm($this->paragraph->getTranslation('de'));

    $this->assertFalse($form['field_untranslatable']['#access']);
    $this->assertNotFalse($form['field_translatable']['#access'] ?? TRUE);
    $this->assertArrayNotHasKey('behavior_plugins', $form);
  }

  /**
   * Tests that untranslatable fields are labeled when not hidden.
   */
  public function testUntranslatableFieldsLabeled() {
    $this->setUntranslatableFieldsHide(FALSE);
    $form = $this->buildEditForm($this->paragraph->getTranslation('de'));

    $this->assertNotFalse($form['field_untranslatable']['#access'] ?? TRUE);
    $this->assertStringContainsString('all languages', (string) $form['field_untranslatable']['widget'][0]['value']['#title']);
    $this->assertStringNotContainsString('all languages', (string) $form['field_translatable']['widget'][0]['value']['#title']);
    $this->assertArrayHasKey('behavior_plugins', $form);
  }

  /**
   * Tests that the original language keeps every field as it is.
   */
  public function testOriginalLanguageUnchanged() {
    $this->setUntranslatableFieldsHide(TRUE);
    $form = $this->buildEditForm($this->paragraph);

    $this->assertNotFalse($form['field_untranslatable']['#access'] ?? TRUE);
    $this->assertStringNotContainsString('all languages', (string) $form['field_untranslatable']['widget'][0]['value']['#title']);
    $this->assertArrayHasKey('layout', $form['layout_paragraphs']);
    $this->assertArrayHasKey('behavior_plugins', $form);
  }

  /**
   * Tests that a locked layout keeps the shared behavior settings.
   */
  public function testBehaviorSettingsKeptWhileLayoutIsLocked(): void {
    $this->setUntranslatableFieldsHide(FALSE);
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'lp_test')
      ->setThirdPartySetting('content_translation', 'bundle_settings', ['untranslatable_fields_hide' => '1'])
      ->save();
    $form_state = new FormState();
    $form = $this->buildEditForm($this->paragraph->getTranslation('de'), TRUE, $form_state);

    $this->assertArrayNotHasKey('layout', $form['layout_paragraphs']);
    $this->assertArrayNotHasKey('behavior_plugins', $form);
    $paragraph = $form_state->getFormObject()->buildParagraphComponent($form, $form_state);
    $this->assertSame('layout_twocol', $paragraph->getBehaviorSetting('layout_paragraphs', 'layout'));
    $this->assertSame(1, $paragraph->getBehaviorSetting('test_bold_text', 'bold_text'));
  }

  /**
   * Enables translation of the paragraph type.
   *
   * @param bool $hide
   *   Whether untranslatable fields are hidden while translating.
   */
  protected function setUntranslatableFieldsHide(bool $hide) {
    ContentLanguageSettings::loadByEntityTypeBundle('paragraph', 'lp_text')
      ->setLanguageAlterable(TRUE)
      ->setThirdPartySetting('content_translation', 'enabled', TRUE)
      ->setThirdPartySetting('content_translation', 'bundle_settings', ['untranslatable_fields_hide' => $hide ? '1' : '0'])
      ->save();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
  }

  /**
   * Builds the edit form for the paragraph in a given translation.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph translation the form edits.
   * @param bool $translating
   *   Whether the layout is being translated.
   * @param \Drupal\Core\Form\FormState $form_state
   *   The form state.
   *
   * @return array
   *   The built form.
   */
  protected function buildEditForm(ParagraphInterface $paragraph, bool $translating = FALSE, FormState $form_state = new FormState()) {
    $items = $this->node->get('field_lp');
    $items[0]->entity = $paragraph;
    $layout = new LayoutParagraphsLayout($items, ['form_display_mode' => 'default']);
    $layout->setTranslating($translating);
    $form_object = $this->container->get('class_resolver')->getInstanceFromDefinition(EditComponentForm::class);
    $form_state->addBuildInfo('args', [$layout, $paragraph->uuid()]);
    return $this->container->get('renderer')->executeInRenderContext(new RenderContext(), function () use ($form_object, $form_state) {
      return $this->container->get('form_builder')->buildForm($form_object, $form_state);
    });
  }

}
