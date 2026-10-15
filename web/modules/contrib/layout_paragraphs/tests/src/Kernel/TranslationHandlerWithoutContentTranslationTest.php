<?php

namespace Drupal\Tests\layout_paragraphs\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\layout_paragraphs\LayoutParagraphsTranslationHandlerInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;

/**
 * Tests the translation handler without the content translation module.
 *
 * @group layout_paragraphs
 */
class TranslationHandlerWithoutContentTranslationTest extends KernelTestBase {

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

    $this->addParagraphsType('text');
    $this->addFieldtoParagraphType('text', 'field_text', 'string');
    $this->addParagraphedContentType('page', 'field_content', 'layout_paragraphs');

    $this->translationHandler = $this->container->get('layout_paragraphs.translation_handler');
  }

  /**
   * Tests that the service is built with no content translation manager.
   */
  public function testServiceIsBuiltWithoutContentTranslationManager(): void {
    $this->assertFalse($this->container->has('content_translation.manager'));
    $this->assertInstanceOf(LayoutParagraphsTranslationHandlerInterface::class, $this->translationHandler);

    // The guard around the translation metadata source cannot be reached
    // through the service's behavior here: without the content translation
    // module a paragraph has no content_translation_source field, so nothing
    // the service does depends on the manager, and the missing manager is
    // only visible on the object.
    $property = new \ReflectionProperty($this->translationHandler, 'contentTranslationManager');
    $this->assertNull($property->getValue($this->translationHandler));
  }

  /**
   * Tests that a host of an untranslatable bundle is not translating.
   */
  public function testHostOfUntranslatableBundleIsNotTranslating(): void {
    $node = $this->createNode('page', [$this->createTextParagraph('EN text')]);

    $this->assertTrue($this->container->get('language_manager')->isMultilingual());
    $this->assertFalse($node->isTranslatable());
    $this->assertFalse($this->translationHandler->isTranslating($node));
    $this->assertFalse($this->translationHandler->isTranslating($node, 'en'));
    $this->assertFalse($this->translationHandler->isTranslating($node, 'de'));
    $this->assertFalse($this->translationHandler->isTranslating($node, 'de', TRUE));
    $this->assertFalse($this->translationHandler->isTranslating($node, NULL, TRUE));
  }

  /**
   * Tests that a paragraph in another language is relabeled.
   *
   * Outside translation, a paragraph without a translation in the given
   * language takes that language, keeping its field values.
   */
  public function testParagraphWithoutTranslationIsRelabeled(): void {
    $paragraph = $this->createTextParagraph('EN text');
    $node = $this->createNode('page', [$paragraph]);

    $layout = new LayoutParagraphsLayout($node->get('field_content'));
    $this->translationHandler->initTranslations($layout, FALSE, 'de');

    $entity = $layout->getParagraphsReferenceField()[0]->entity;
    $this->assertSame($paragraph->id(), $entity->id());
    $this->assertSame('de', $entity->language()->getId());
    $this->assertSame('EN text', $entity->get('field_text')->value);
    $this->assertSame(['de'], array_keys($entity->getTranslationLanguages()));
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
