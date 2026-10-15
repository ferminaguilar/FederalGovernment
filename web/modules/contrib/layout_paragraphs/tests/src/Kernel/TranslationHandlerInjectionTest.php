<?php

namespace Drupal\Tests\layout_paragraphs\Kernel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_paragraphs\Form\LayoutParagraphsBuilderForm;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository;
use Drupal\layout_paragraphs\LayoutParagraphsTranslationHandlerInterface;
use Drupal\layout_paragraphs\Plugin\Field\FieldWidget\LayoutParagraphsWidget;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;

/**
 * Tests how the widget and the builder form get the translation handler.
 *
 * The translation handler is the last constructor parameter of both classes
 * and it is optional, so code written before it existed keeps working: a
 * direct instantiation and a subclass that passes the older argument list
 * both get the handler from the container, without an error, a warning, or a
 * deprecation, and so do the objects the container factories return.
 *
 * @group layout_paragraphs
 */
class TranslationHandlerInjectionTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installConfig(['language', 'layout_paragraphs']);

    $this->addParagraphsType('text');
    $this->addFieldtoParagraphType('text', 'field_text', 'string');
    $this->addParagraphedContentType('page', 'field_content', 'layout_paragraphs');
  }

  /**
   * Tests that a directly built widget gets the handler from the container.
   */
  public function testDirectlyConstructedWidgetGetsHandlerFromContainer(): void {
    $arguments = $this->getWidgetConstructorArguments();
    $widget = $this->constructAndAssertNothingRaised(fn() => new LayoutParagraphsWidget(...$arguments));

    $this->assertSame($this->container->get('layout_paragraphs.translation_handler'), $this->readTranslationHandler($widget));
  }

  /**
   * Tests that a widget subclass gets the handler from the container.
   */
  public function testWidgetSubclassGetsHandlerFromContainer(): void {
    $arguments = $this->getWidgetConstructorArguments();
    $widget = $this->constructAndAssertNothingRaised(fn() => new class(...$arguments) extends LayoutParagraphsWidget {

      // The constructor repeats the parent signature without the translation
      // handler argument, which is what this test covers.
      // phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod

      /**
       * {@inheritdoc}
       */
      public function __construct(
        $plugin_id,
        $plugin_definition,
        FieldDefinitionInterface $field_definition,
        array $settings,
        array $third_party_settings,
        LayoutParagraphsLayoutTempstoreRepository $tempstore,
        EntityTypeManagerInterface $entity_type_manager,
        LayoutPluginManagerInterface $layout_plugin_manager,
        FormBuilderInterface $form_builder,
        EntityDisplayRepositoryInterface $entity_display_repository,
        ConfigFactoryInterface $config_factory,
        EntityRepositoryInterface $entity_repository,
        $content_translation_manager,
      ) {
        parent::__construct(
          $plugin_id,
          $plugin_definition,
          $field_definition,
          $settings,
          $third_party_settings,
          $tempstore,
          $entity_type_manager,
          $layout_plugin_manager,
          $form_builder,
          $entity_display_repository,
          $config_factory,
          $entity_repository,
          $content_translation_manager
        );
      }

      // phpcs:enable Generic.CodeAnalysis.UselessOverridingMethod

      /**
       * Returns the translation handler the constructor set.
       *
       * @return \Drupal\layout_paragraphs\LayoutParagraphsTranslationHandlerInterface
       *   The translation handler.
       */
      public function getTranslationHandler(): LayoutParagraphsTranslationHandlerInterface {
        return $this->translationHandler;
      }

    });

    $this->assertSame($this->container->get('layout_paragraphs.translation_handler'), $widget->getTranslationHandler());
  }

  /**
   * Tests that a widget built by its factory holds the container's handler.
   */
  public function testWidgetFromContainerFactoryHasHandlerFromContainer(): void {
    $configuration = [
      'field_definition' => $this->getReferenceFieldDefinition(),
      'settings' => [],
      'third_party_settings' => [],
    ];
    $plugin_definition = $this->getWidgetPluginDefinition();
    $widget = $this->constructAndAssertNothingRaised(fn() => LayoutParagraphsWidget::create($this->container, $configuration, 'layout_paragraphs', $plugin_definition));

    $this->assertSame($this->container->get('layout_paragraphs.translation_handler'), $this->readTranslationHandler($widget));
  }

  /**
   * Tests that a directly built form gets the handler from the container.
   */
  public function testDirectlyConstructedBuilderFormGetsHandlerFromContainer(): void {
    $arguments = $this->getBuilderFormConstructorArguments();
    $form = $this->constructAndAssertNothingRaised(fn() => new LayoutParagraphsBuilderForm(...$arguments));

    $this->assertSame($this->container->get('layout_paragraphs.translation_handler'), $this->readTranslationHandler($form));
  }

  /**
   * Tests that a builder form subclass gets the handler from the container.
   */
  public function testBuilderFormSubclassGetsHandlerFromContainer(): void {
    $arguments = $this->getBuilderFormConstructorArguments();
    $form = $this->constructAndAssertNothingRaised(fn() => new class(...$arguments) extends LayoutParagraphsBuilderForm {

      // The constructor repeats the parent signature without the translation
      // handler argument, which is what this test covers.
      // phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod

      /**
       * {@inheritdoc}
       */
      public function __construct(
        LayoutParagraphsLayoutTempstoreRepository $tempstore,
        EntityTypeManagerInterface $entity_type_manager,
      ) {
        parent::__construct($tempstore, $entity_type_manager);
      }

      // phpcs:enable Generic.CodeAnalysis.UselessOverridingMethod

      /**
       * Returns the translation handler the constructor set.
       *
       * @return \Drupal\layout_paragraphs\LayoutParagraphsTranslationHandlerInterface
       *   The translation handler.
       */
      public function getTranslationHandler(): LayoutParagraphsTranslationHandlerInterface {
        return $this->translationHandler;
      }

    });

    $this->assertSame($this->container->get('layout_paragraphs.translation_handler'), $form->getTranslationHandler());
  }

  /**
   * Tests that a form built by its factory holds the container's handler.
   */
  public function testBuilderFormFromContainerFactoryHasHandlerFromContainer(): void {
    $form = $this->constructAndAssertNothingRaised(fn() => LayoutParagraphsBuilderForm::create($this->container));

    $this->assertSame($this->container->get('layout_paragraphs.translation_handler'), $this->readTranslationHandler($form));
  }

  /**
   * Returns the widget arguments of the list before the handler was appended.
   *
   * @return array
   *   The constructor arguments, in order, without a translation handler.
   */
  protected function getWidgetConstructorArguments(): array {
    return [
      'layout_paragraphs',
      $this->getWidgetPluginDefinition(),
      $this->getReferenceFieldDefinition(),
      [],
      [],
      $this->container->get('layout_paragraphs.tempstore_repository'),
      $this->container->get('entity_type.manager'),
      $this->container->get('plugin.manager.core.layout'),
      $this->container->get('form_builder'),
      $this->container->get('entity_display.repository'),
      $this->container->get('config.factory'),
      $this->container->get('entity.repository'),
      $this->container->get('content_translation.manager'),
    ];
  }

  /**
   * Returns the form arguments of the list before the handler was appended.
   *
   * @return array
   *   The constructor arguments, in order, without a translation handler.
   */
  protected function getBuilderFormConstructorArguments(): array {
    return [
      $this->container->get('layout_paragraphs.tempstore_repository'),
      $this->container->get('entity_type.manager'),
    ];
  }

  /**
   * Returns the definition of the layout paragraphs widget plugin.
   *
   * @return array
   *   The plugin definition.
   */
  protected function getWidgetPluginDefinition(): array {
    return $this->container->get('plugin.manager.field.widget')
      ->getDefinition('layout_paragraphs');
  }

  /**
   * Returns the definition of the paragraphs reference field.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface
   *   The field definition the widget is configured with.
   */
  protected function getReferenceFieldDefinition(): FieldDefinitionInterface {
    return $this->container->get('entity_field.manager')
      ->getFieldDefinitions('node', 'page')['field_content'];
  }

  /**
   * Constructs an object and asserts that the construction raised nothing.
   *
   * @param callable $constructor
   *   The callable that constructs the object under test.
   *
   * @return object
   *   The constructed object.
   */
  protected function constructAndAssertNothingRaised(callable $constructor): object {
    $raised = [];
    set_error_handler(function (int $level, string $message) use (&$raised): bool {
      $raised[] = $level . ': ' . $message;
      return TRUE;
    });
    try {
      $object = $constructor();
    }
    finally {
      restore_error_handler();
    }
    $this->assertSame([], $raised);
    return $object;
  }

  /**
   * Returns the translation handler an object holds.
   *
   * @param object $object
   *   The widget or the form to read the handler from.
   *
   * @return mixed
   *   The value of the translation handler property.
   */
  protected function readTranslationHandler(object $object): mixed {
    return (new \ReflectionProperty($object, 'translationHandler'))->getValue($object);
  }

}
