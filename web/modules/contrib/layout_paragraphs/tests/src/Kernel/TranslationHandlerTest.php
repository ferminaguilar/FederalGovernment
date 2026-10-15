<?php

namespace Drupal\Tests\layout_paragraphs\Kernel;

use Drupal\Core\Language\LanguageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\layout_paragraphs\LayoutParagraphsTranslationHandler;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;

/**
 * Tests the layout paragraphs translation handler service.
 *
 * @group layout_paragraphs
 */
class TranslationHandlerTest extends KernelTestBase {

  use ParagraphsTestBaseTrait;

  /**
   * The modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'text',
    'node',
    'file',
    'field',
    'layout_discovery',
    'entity_reference_revisions',
    'paragraphs',
    'content_translation',
    'language',
    'layout_paragraphs',
  ];

  /**
   * The translation handler under test.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsTranslationHandlerInterface
   */
  protected $translationHandler;

  /**
   * The content translation manager.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface
   */
  protected $contentTranslationManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installConfig(['language']);

    ConfigurableLanguage::create(['id' => 'de'])->save();
    ConfigurableLanguage::create(['id' => 'fr'])->save();

    $this->addParagraphsType('text');
    $this->addFieldtoParagraphType('text', 'field_text', 'string');
    $this->addParagraphedContentType('page', 'field_content', 'layout_paragraphs');

    ContentLanguageSettings::loadByEntityTypeBundle('node', 'page')
      ->setDefaultLangcode('en')
      ->setLanguageAlterable(TRUE)
      ->save();

    $this->contentTranslationManager = $this->container->get('content_translation.manager');
    $this->contentTranslationManager->setEnabled('node', 'page', TRUE);

    $this->translationHandler = $this->container->get('layout_paragraphs.translation_handler');
  }

  /**
   * Tests that a host without translation support is never translating.
   */
  public function testHostWithoutTranslationSupportIsNotTranslating(): void {
    $this->addParagraphedContentType('article', 'field_content', 'layout_paragraphs');
    $node = $this->createNode('article', [$this->createTextParagraph('EN text')]);

    $this->assertFalse($node->isTranslatable());
    $this->assertFalse($this->translationHandler->isTranslating($node, 'en'));
    $this->assertFalse($this->translationHandler->isTranslating($node, 'en', TRUE));
  }

  /**
   * Tests that the default translation of the host is not translating.
   */
  public function testDefaultTranslationIsNotTranslating(): void {
    $this->enableSymmetricTranslation();
    $node = $this->createNode('page', [$this->createTextParagraph('EN text')]);
    $node->addTranslation('de', ['title' => 'Node title DE'])->save();

    $this->assertFalse($this->translationHandler->isTranslating($node));
    $this->assertFalse($this->translationHandler->isTranslating($node, 'en'));
    $this->assertFalse($this->translationHandler->isTranslating($node, 'fr'));
  }

  /**
   * Tests that paragraphs follow the language of the default translation.
   *
   * A paragraph in another language is switched to its translation in the
   * host language if it has one, and is relabeled otherwise. The paragraphs
   * set on the layout's reference field carry a reference to the layout.
   */
  public function testParagraphsFollowDefaultTranslationLanguage(): void {
    $this->enableSymmetricTranslation();
    $relabeled = $this->createTextParagraph('DE only text', 'de');
    $translated = $this->createTextParagraph('EN text');
    $translated->addTranslation('de', ['field_text' => 'DE text'])->save();
    $node = $this->createNode('page', [$relabeled, $translated->getTranslation('de')]);

    $layout = new LayoutParagraphsLayout($node->get('field_content'));
    $this->translationHandler->initTranslations($layout, FALSE, 'en');

    $items = $layout->getParagraphsReferenceField();
    $this->assertSame($relabeled->id(), $items[0]->entity->id());
    $this->assertSame('en', $items[0]->entity->language()->getId());
    $this->assertSame('DE only text', $items[0]->entity->get('field_text')->value);
    $this->assertSame($translated->id(), $items[1]->entity->id());
    $this->assertSame('en', $items[1]->entity->language()->getId());
    $this->assertSame('EN text', $items[1]->entity->get('field_text')->value);
    $this->assertSame($layout, $items[1]->entity->_layoutParagraphsLayout);
  }

  /**
   * Tests that editing a host translation switches paragraphs to it.
   *
   * An existing host translation other than the default one is translating,
   * and paragraphs with a translation in its language are switched to that
   * translation.
   */
  public function testExistingHostTranslationSwitchesParagraphsToTranslation(): void {
    $this->enableSymmetricTranslation();
    $paragraph = $this->createTextParagraph('EN text');
    $paragraph->addTranslation('de', ['field_text' => 'DE text'])->save();
    $node = $this->createNode('page', [$paragraph]);
    $node->addTranslation('de', ['title' => 'Node title DE'])->save();
    $translation = $node->getTranslation('de');

    $this->assertTrue($this->translationHandler->isTranslating($translation, 'de'));

    $layout = new LayoutParagraphsLayout($translation->get('field_content'));
    $this->translationHandler->initTranslations($layout, TRUE, 'de');

    $entity = $layout->getParagraphsReferenceField()[0]->entity;
    $this->assertSame($paragraph->id(), $entity->id());
    $this->assertSame('de', $entity->language()->getId());
    $this->assertSame('DE text', $entity->get('field_text')->value);
  }

  /**
   * Tests that a missing paragraph translation is added from its language.
   *
   * Without a source language, the translation takes the values of the
   * paragraph's own language.
   */
  public function testMissingParagraphTranslationIsAddedFromParagraphLanguage(): void {
    $this->enableSymmetricTranslation();
    $paragraph = $this->createTextParagraph('EN text');
    $node = $this->createNode('page', [$paragraph]);
    $node->addTranslation('de', ['title' => 'Node title DE'])->save();
    $translation = $node->getTranslation('de');

    $layout = new LayoutParagraphsLayout($translation->get('field_content'));
    $this->translationHandler->initTranslations($layout, TRUE, 'de');

    $entity = $layout->getParagraphsReferenceField()[0]->entity;
    $this->assertTrue($entity->hasTranslation('de'));
    $this->assertSame('de', $entity->language()->getId());
    $this->assertSame('EN text', $entity->get('field_text')->value);
    $this->assertSame('en', $this->contentTranslationManager->getTranslationMetadata($entity)->getSource());
  }

  /**
   * Tests that a missing paragraph translation is added from the source.
   *
   * With a source language the paragraph has a translation in, the new
   * translation takes the values of that source translation.
   */
  public function testMissingParagraphTranslationIsAddedFromSourceLanguage(): void {
    $this->enableSymmetricTranslation();
    $paragraph = $this->createTextParagraph('EN text');
    $paragraph->addTranslation('fr', ['field_text' => 'FR text'])->save();
    $node = $this->createNode('page', [$paragraph]);
    $node->addTranslation('de', ['title' => 'Node title DE'])->save();
    $translation = $node->getTranslation('de');

    $layout = new LayoutParagraphsLayout($translation->get('field_content'));
    $this->translationHandler->initTranslations($layout, TRUE, 'de', 'fr');

    $entity = $layout->getParagraphsReferenceField()[0]->entity;
    $this->assertSame('de', $entity->language()->getId());
    $this->assertSame('FR text', $entity->get('field_text')->value);
    $this->assertSame('fr', $this->contentTranslationManager->getTranslationMetadata($entity)->getSource());
  }

  /**
   * Tests that each paragraph without a source language uses its own language.
   *
   * Without a source language, every missing paragraph translation takes the
   * values of that paragraph's own language, even where a paragraph also has
   * a translation in the language of a paragraph before it.
   */
  public function testMissingParagraphTranslationsAreAddedFromEachParagraphLanguage(): void {
    $this->enableSymmetricTranslation();
    $english = $this->createTextParagraph('EN text');
    $french = $this->createTextParagraph('FR text', 'fr');
    $french->addTranslation('en', ['field_text' => 'EN text of FR paragraph'])->save();
    $node = $this->createNode('page', [$english, $french]);
    $node->addTranslation('de', ['title' => 'Node title DE'])->save();
    $translation = $node->getTranslation('de');

    $layout = new LayoutParagraphsLayout($translation->get('field_content'));
    $this->translationHandler->initTranslations($layout, TRUE, 'de');

    $items = $layout->getParagraphsReferenceField();
    $this->assertSame('de', $items[0]->entity->language()->getId());
    $this->assertSame('EN text', $items[0]->entity->get('field_text')->value);
    $this->assertSame('en', $this->contentTranslationManager->getTranslationMetadata($items[0]->entity)->getSource());
    $this->assertSame($french->id(), $items[1]->entity->id());
    $this->assertSame('de', $items[1]->entity->language()->getId());
    $this->assertSame('FR text', $items[1]->entity->get('field_text')->value);
    $this->assertSame('fr', $this->contentTranslationManager->getTranslationMetadata($items[1]->entity)->getSource());
  }

  /**
   * Tests that a paragraph translation is added without a manager.
   *
   * A handler built without a content translation manager still adds the
   * missing paragraph translation with the source language values and puts
   * it on the layout, and it writes no translation metadata source: the
   * source field of the new translation stays at its default instead of
   * recording the source language.
   */
  public function testMissingParagraphTranslationIsAddedWithoutContentTranslationManager(): void {
    $this->enableSymmetricTranslation();
    $paragraph = $this->createTextParagraph('EN text');
    $node = $this->createNode('page', [$paragraph]);
    $node->addTranslation('de', ['title' => 'Node title DE'])->save();
    $translation = $node->getTranslation('de');
    // The source paragraph was never translated through the content
    // translation module, so its source field holds the field default.
    $this->assertSame(LanguageInterface::LANGCODE_NOT_SPECIFIED, $paragraph->get('content_translation_source')->value);

    $handler = new LayoutParagraphsTranslationHandler(NULL);
    $layout = new LayoutParagraphsLayout($translation->get('field_content'));
    $handler->initTranslations($layout, TRUE, 'de');

    $entity = $layout->getParagraphsReferenceField()[0]->entity;
    $this->assertSame($paragraph->id(), $entity->id());
    $this->assertTrue($entity->hasTranslation('de'));
    $this->assertSame('de', $entity->language()->getId());
    $this->assertSame('EN text', $entity->get('field_text')->value);
    $this->assertSame(LanguageInterface::LANGCODE_NOT_SPECIFIED, $entity->get('content_translation_source')->value);
  }

  /**
   * Tests that adding a host translation is translating.
   */
  public function testAddingHostTranslationIsTranslating(): void {
    $node = $this->createNode('page', [$this->createTextParagraph('EN text')]);

    $this->assertFalse($node->hasTranslation('de'));
    $this->assertTrue($this->translationHandler->isTranslating($node, 'de', TRUE));
    $this->assertTrue($this->translationHandler->isTranslating($node, NULL, TRUE));
  }

  /**
   * Tests that asymmetric translations leave the paragraphs as they are.
   *
   * With a translatable reference field and untranslatable paragraphs, the
   * host translation is translating, and its paragraphs are neither
   * translated nor relabeled, whatever their language.
   */
  public function testAsymmetricTranslationLeavesParagraphsUnchanged(): void {
    $this->enableAsymmetricTranslation();
    $paragraph = $this->createTextParagraph('EN text');
    $node = $this->createNode('page', [$paragraph]);
    // Creating the host translation replaces its paragraphs with German copies.
    $translation = $node->addTranslation('de', [
      'title' => 'Node title DE',
      'field_content' => [$paragraph],
    ]);
    $copy = $translation->get('field_content')->entity;
    $this->assertNotSame($paragraph, $copy);
    $this->assertSame('de', $copy->language()->getId());
    // A paragraph in another language referenced by the host translation.
    $translation->get('field_content')->appendItem($paragraph);

    $this->assertTrue($this->translationHandler->isTranslating($translation, 'de', TRUE));
    $this->assertTrue($this->translationHandler->isTranslating($translation, 'de'));

    $layout = new LayoutParagraphsLayout($translation->get('field_content'));
    $this->translationHandler->initTranslations($layout, TRUE, 'de');

    $items = $layout->getParagraphsReferenceField();
    $this->assertSame($copy, $items[0]->entity);
    $this->assertSame(['de'], array_keys($items[0]->entity->getTranslationLanguages()));
    $this->assertSame($paragraph, $items[1]->entity);
    $this->assertSame(['en'], array_keys($items[1]->entity->getTranslationLanguages()));
    $this->assertSame('en', $items[1]->entity->language()->getId());
    $this->assertSame('EN text', $items[1]->entity->get('field_text')->value);
  }

  /**
   * Configures content translation for symmetric paragraph translations.
   *
   * The paragraphs and their text field are translatable, the reference field
   * is not.
   */
  protected function enableSymmetricTranslation(): void {
    $this->setReferenceFieldTranslatable(FALSE);
    $this->contentTranslationManager->setEnabled('paragraph', 'text', TRUE);
    $this->container->get('entity_field.manager')
      ->getFieldDefinitions('paragraph', 'text')['field_text']
      ->setTranslatable(TRUE)
      ->save();
  }

  /**
   * Configures content translation for asymmetric paragraph translations.
   *
   * The reference field is translatable; the paragraphs and their text field
   * are not.
   */
  protected function enableAsymmetricTranslation(): void {
    $this->setReferenceFieldTranslatable(TRUE);
    $this->container->get('entity_field.manager')
      ->getFieldDefinitions('paragraph', 'text')['field_text']
      ->setTranslatable(FALSE)
      ->save();
  }

  /**
   * Sets the translatability of the page reference field.
   *
   * @param bool $translatable
   *   Whether the reference field is translatable.
   */
  protected function setReferenceFieldTranslatable(bool $translatable): void {
    $entity_type_manager = $this->container->get('entity_type.manager');
    $field_storage = $entity_type_manager->getStorage('field_storage_config')->load('node.field_content');
    $field_storage->set('translatable', $translatable);
    $field_storage->save();
    $field = $entity_type_manager->getStorage('field_config')->load('node.page.field_content');
    $field->set('translatable', $translatable);
    $field->save();
  }

  /**
   * Creates and saves a text paragraph.
   *
   * @param string $text
   *   The value of the text field.
   * @param string $langcode
   *   The language of the paragraph.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   The saved paragraph.
   */
  protected function createTextParagraph(string $text, string $langcode = 'en'): ParagraphInterface {
    $paragraph = $this->container->get('entity_type.manager')
      ->getStorage('paragraph')
      ->create([
        'type' => 'text',
        'langcode' => $langcode,
        'field_text' => $text,
      ]);
    $paragraph->save();
    return $paragraph;
  }

  /**
   * Creates and saves an English node referencing the given paragraphs.
   *
   * @param string $bundle
   *   The node type.
   * @param \Drupal\paragraphs\ParagraphInterface[] $paragraphs
   *   The paragraphs to reference.
   *
   * @return \Drupal\node\NodeInterface
   *   The saved node.
   */
  protected function createNode(string $bundle, array $paragraphs): NodeInterface {
    $node = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->create([
        'type' => $bundle,
        'langcode' => 'en',
        'title' => 'Node title',
        'field_content' => $paragraphs,
      ]);
    $node->save();
    return $node;
  }

}
