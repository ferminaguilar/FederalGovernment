<?php

namespace Drupal\layout_paragraphs\Plugin\paragraphs\Behavior;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\OptGroup;
use Drupal\Core\Render\Element;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Layout\LayoutInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\layout_paragraphs\LayoutParagraphsSection;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\layout_paragraphs\LayoutParagraphsRendererService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a way to define grid based layouts.
 *
 * @ParagraphsBehavior(
 *   id = "layout_paragraphs",
 *   label = @Translation("Layout Paragraphs"),
 *   description = @Translation("Integrates paragraphs with layout discovery and layout API."),
 *   weight = 0
 * )
 */
class LayoutParagraphsBehavior extends ParagraphsBehaviorBase {

  /**
   * The layout plugin manager service.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManagerInterface
   */
  protected $layoutPluginManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   *   The entity type manager service.
   */
  protected $entityTypeManager;

  /**
   * The layout paragraphs service.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsRendererService
   */
  protected $layoutParagraphsRendererService;

  /**
   * A reference to the paragraph instance.
   *
   * @var [type]
   */
  protected $paragraph;

  /**
   * The plugin form factory.
   *
   * @var \Drupal\Core\Plugin\PluginFormFactoryInterface
   */
  protected $pluginFormFactory;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * ParagraphsLayoutPlugin constructor.
   *
   * @param array $configuration
   *   The configuration array.
   * @param string $plugin_id
   *   This plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Entity field manager service.
   * @param \Drupal\Core\Layout\LayoutPluginManagerInterface $layout_plugin_manager
   *   The grid discovery service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The grid discovery service.
   * @param \Drupal\layout_paragraphs\LayoutParagraphsRendererService $layout_paragraphs_renderer_service
   *   The layout paragraphs service.
   * @param \Drupal\Core\Plugin\PluginFormFactoryInterface $plugin_form_factory
   *   The plugin form factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityFieldManagerInterface $entity_field_manager,
    LayoutPluginManagerInterface $layout_plugin_manager,
    EntityTypeManagerInterface $entity_type_manager,
    LayoutParagraphsRendererService $layout_paragraphs_renderer_service,
    PluginFormFactoryInterface $plugin_form_factory,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_field_manager);
    $this->layoutPluginManager = $layout_plugin_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->layoutParagraphsRendererService = $layout_paragraphs_renderer_service;
    $this->pluginFormFactory = $plugin_form_factory;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.core.layout'),
      $container->get('entity_type.manager'),
      $container->get('layout_paragraphs.renderer'),
      $container->get('plugin_form.factory'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(
    ParagraphInterface $paragraph,
    array &$form,
    FormStateInterface $form_state,
  ) {

    $layout_paragraphs_section = new LayoutParagraphsSection($paragraph);
    $layout_settings = $layout_paragraphs_section->getSetting('config');
    $available_layouts = $this->configuration['available_layouts'];
    $path = array_merge($form['#parents'], ['layout']);
    $input_layout_id = NestedArray::getValue($form_state->getUserInput(), $path);
    $layout_id = $input_layout_id ?? $layout_paragraphs_section->getLayoutId();
    $layout_id = Html::escape($layout_id);
    $default_value = !empty($layout_id) ? $layout_id : key($available_layouts);
    // @todo Throw an error if plugin instance cannot be loaded.
    $plugin_instance = $this->layoutPluginManager->createInstance($default_value, $layout_settings ?? []);
    $plugin_form = $this->getLayoutPluginForm($plugin_instance);
    $wrapper_id = Html::getId(implode('-', array_merge($form['#parents'], ['layout-options'])));
    $form['layout'] = [
      '#title' => $this->t('Choose a layout:'),
      '#type' => 'layout_select',
      '#options' => $available_layouts,
      '#default_value' => $default_value,
      '#ajax' => [
        'wrapper' => $wrapper_id,
        'callback' => [$this, 'ajaxUpdateOptions'],
        'progress' => [
          'type' => 'throbber',
        ],
      ],
      '#weight' => 0,
    ];
    if ($plugin_form) {
      $form['config'] = [
        '#type' => 'details',
        '#id' => $wrapper_id,
        '#title' => $this->t('Layout Options'),
        '#weight' => 10,
      ];
      $form['config'] += $plugin_form->buildConfigurationForm([], $form_state);
      $this->discardStaleConfigValues(
        $form['config'],
        array_merge($form['#parents'], ['config']),
        $plugin_instance->defaultConfiguration(),
        $form_state
      );
    }

    return $form;
  }

  /**
   * Drops config values the selected layout does not accept.
   *
   * When the layout changes, the submitted input and the #default_value still
   * hold the previous layout's settings. A value that is not one of the new
   * layout's #options fails validation, which stops the form from being
   * rebuilt with the "Move Orphaned Items" element.
   *
   * @param array $elements
   *   The layout configuration form.
   * @param array $parents
   *   The parents of $elements, used to address the user input.
   * @param array $defaults
   *   The target layout plugin's default configuration.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  protected function discardStaleConfigValues(array &$elements, array $parents, array $defaults, FormStateInterface $form_state) {
    $input = $form_state->getUserInput();
    $this->discardStaleConfigValuesRecursive($elements, $parents, $defaults, $input);
    $form_state->setUserInput($input);
  }

  /**
   * Recursive worker for static::discardStaleConfigValues().
   *
   * @param array $elements
   *   The form elements to walk.
   * @param array $parents
   *   The parents of $elements.
   * @param array $defaults
   *   The target layout plugin's default configuration.
   * @param array $input
   *   The user input, altered by reference.
   */
  protected function discardStaleConfigValuesRecursive(array &$elements, array $parents, array $defaults, array &$input) {
    foreach (Element::children($elements) as $key) {
      $element = &$elements[$key];
      $path = array_merge($parents, [$key]);
      if (isset($element['#options'])) {
        $options = OptGroup::flattenOptions($element['#options']);
        if (isset($element['#default_value']) && !$this->isAllowedOption($element['#default_value'], $options)) {
          $element['#default_value'] = array_key_exists($key, $defaults) && $this->isAllowedOption($defaults[$key], $options)
            ? $defaults[$key]
            : array_key_first($options);
        }
        $submitted = NestedArray::getValue($input, $path, $exists);
        if ($exists && !$this->isAllowedOption($submitted, $options)) {
          NestedArray::unsetValue($input, $path);
        }
      }
      $this->discardStaleConfigValuesRecursive($element, $path, $defaults, $input);
    }
  }

  /**
   * Checks a submitted or default value against a set of allowed options.
   *
   * @param mixed $value
   *   The value to check. Arrays - multi-value selects, checkboxes - are
   *   allowed only when every item is.
   * @param array $options
   *   The flattened #options array.
   *
   * @return bool
   *   TRUE if the value is allowed.
   */
  protected function isAllowedOption($value, array $options) {
    if (is_array($value)) {
      foreach ($value as $item) {
        if (!$this->isAllowedOption($item, $options)) {
          return FALSE;
        }
      }
      return TRUE;
    }
    return is_scalar($value) && isset($options[$value]);
  }

  /**
   * {@inheritdoc}
   */
  public function validateBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $plugin_instance = $this->layoutPluginManager->createInstance($form_state->getValue('layout'), $form_state->getValue('config') ?? []);
    if ($plugin_form = $this->getLayoutPluginForm($plugin_instance)) {
      $plugin_form->validateConfigurationForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $filtered_values = $this->filterBehaviorFormSubmitValues($paragraph, $form, $form_state);
    $plugin_instance = $this->layoutPluginManager->createInstance($form_state->getValue('layout'), $form_state->getValue('config') ?? []);
    if ($plugin_form = $this->getLayoutPluginForm($plugin_instance)) {
      // Add default #parents array to prevent form errors.
      // @see https://www.drupal.org/project/layout_paragraphs/issues/3291180
      $form['config'] += ['#parents' => []];
      $form += ['#parents' => []];
      $subform_state = SubformState::createForSubform($form['config'], $form, $form_state);
      $plugin_form->submitConfigurationForm($form['config'], $subform_state);
      $filtered_values['config'] = $plugin_form->getConfiguration();
    }
    // Merge existing behavior settings.
    $behavior_settings = $paragraph->getAllBehaviorSettings();
    $existing_settings = $behavior_settings[$this->getPluginId()] ?? [];
    $filtered_values = $filtered_values + $existing_settings;
    // Set the updated behavior settings.
    $paragraph->setBehaviorSettings($this->getPluginId(), $filtered_values);
  }

  /**
   * Ajax callback - returns the updated layout options form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The layout options form.
   */
  public function ajaxUpdateOptions(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = $triggering_element['#parents'];
    array_splice($parents, -1, 1, ['config']);
    $config_form = NestedArray::getValue($form, $parents);
    if (isset($config_form)) {
      return $config_form;
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'available_layouts' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $options = $this->layoutPluginManager->getLayoutOptions();
    $available_layouts = $this->configuration['available_layouts'];
    $form['available_layouts'] = [
      '#title' => $this->t('Available Layouts'),
      '#type' => 'select',
      '#multiple' => TRUE,
      '#options' => $options,
      '#default_value' => array_keys($available_layouts),
      '#size' => count($options) < 8 ? count($options) * 2 : 10,
      '#required' => FALSE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (empty($form_state->getValue('available_layouts'))) {
      $form_state->setErrorByName('available_layouts', $this->t('You must select at least one layout.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $available_layouts = array_filter($form_state->getValue('available_layouts'));
    foreach ($available_layouts as $layout_name) {
      $layout = $this->layoutPluginManager->getDefinition($layout_name);
      $this->configuration['available_layouts'][$layout_name] = $layout->getLabel();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(ParagraphInterface $paragraph) {
    $summary = [];
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, ParagraphInterface $paragraph, EntityViewDisplayInterface $display, $view_mode) {
    if (empty($build['regions']) && LayoutParagraphsSection::isLayoutComponent($paragraph)) {
      $build['regions'] = $this->layoutParagraphsRendererService->renderLayoutSection($build, $paragraph, $view_mode);
    }
  }

  /**
   * Retrieves the plugin form for a given layout.
   *
   * @param \Drupal\Core\Layout\LayoutInterface $layout
   *   The layout plugin.
   *
   * @return \Drupal\Core\Plugin\PluginFormInterface|null
   *   The plugin form for the layout.
   */
  protected function getLayoutPluginForm(LayoutInterface $layout) {
    if ($layout instanceof PluginWithFormsInterface) {
      try {
        return $this->pluginFormFactory->createInstance($layout, 'configure');
      }
      catch (\Exception $e) {
        $this->loggerFactory->get('layout_paragraphs')->error('Erl, Layout Configuration', [$e]);
      }
    }

    if ($layout instanceof PluginFormInterface) {
      return $layout;
    }

    return NULL;
  }

}
