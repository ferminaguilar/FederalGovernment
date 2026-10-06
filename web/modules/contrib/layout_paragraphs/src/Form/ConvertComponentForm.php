<?php

namespace Drupal\layout_paragraphs\Form;

use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Form\WorkspaceSafeFormInterface;
use Drupal\layout_paragraphs\Event\LayoutParagraphsAllowedTypesEvent;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutRefreshTrait;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository;
use Drupal\layout_paragraphs\Utility\Dialog;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsConversionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a form for converting a Layout Paragraphs component.
 *
 * Presents applicable Paragraphs Conversion plugins for a given paragraph
 * component, allows the editor to configure the conversion, and replaces the
 * component with the converted paragraph(s) in the tempstore layout.
 */
class ConvertComponentForm extends FormBase implements WorkspaceSafeFormInterface {

  use AjaxFormHelperTrait;
  use LayoutParagraphsLayoutRefreshTrait;

  /**
   * The layout paragraphs layout tempstore repository.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository
   */
  protected $tempstore;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The paragraphs conversion manager service.
   *
   * @var \Drupal\paragraphs\ParagraphsConversionManager
   */
  protected $conversionManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The uuid of the component to convert.
   *
   * @var string
   */
  protected $componentUuid;

  /**
   * The paragraph entity being converted.
   *
   * @var \Drupal\paragraphs\ParagraphInterface
   */
  protected $paragraph;

  /**
   * Constructs a ConvertComponentForm object.
   *
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository $tempstore
   *   The layout paragraphs tempstore repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\paragraphs\ParagraphsConversionManager $conversion_manager
   *   The paragraphs conversion manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(
    LayoutParagraphsLayoutTempstoreRepository $tempstore,
    EntityTypeManagerInterface $entity_type_manager,
    ParagraphsConversionManager $conversion_manager,
    ModuleHandlerInterface $module_handler,
    EventDispatcherInterface $event_dispatcher,
  ) {
    $this->tempstore = $tempstore;
    $this->entityTypeManager = $entity_type_manager;
    $this->conversionManager = $conversion_manager;
    $this->moduleHandler = $module_handler;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_paragraphs.tempstore_repository'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.paragraphs.conversion'),
      $container->get('module_handler'),
      $container->get('event_dispatcher'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_paragraphs_convert_component_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    ?LayoutParagraphsLayout $layout_paragraphs_layout = NULL,
    ?string $component_uuid = NULL,
  ) {
    $this->setLayoutParagraphsLayout($layout_paragraphs_layout);
    $this->componentUuid = $component_uuid;

    $component = $this->layoutParagraphsLayout->getComponentByUuid($this->componentUuid);
    $this->paragraph = $component->getEntity();

    $allowed_types = $this->getAllowedTypes();
    $definitions = $this->conversionManager->getApplicableDefinitions($this->paragraph, $allowed_types);

    $options = [];
    foreach ($definitions as $plugin_id => $definition) {
      $options[$plugin_id] = $definition['label'];
    }

    $type_label = $this->paragraph->getParagraphType()->label();
    $form['#title'] = $this->t('Convert @type', ['@type' => $type_label]);

    // Determine the currently selected plugin, falling back to the first
    // option.
    $selected_plugin_id = $form_state->getValue('conversion_plugin');
    if (!$selected_plugin_id || !isset($options[$selected_plugin_id])) {
      $selected_plugin_id = array_key_first($options);
    }

    $form['conversion_plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Convert to'),
      '#options' => $options,
      '#default_value' => $selected_plugin_id,
      '#ajax' => [
        'callback' => '::updatePluginForm',
        'wrapper' => 'conversion-plugin-form-wrapper',
      ],
      // Do not trigger validation when only switching the plugin selector.
      '#limit_validation_errors' => [],
    ];

    $form['plugin_form_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'conversion-plugin-form-wrapper'],
      '#tree' => TRUE,
    ];

    if ($selected_plugin_id) {
      $plugin = $this->conversionManager->createInstance($selected_plugin_id);
      $plugin_form = [];
      $sub_form_state = SubformState::createForSubform($plugin_form, $form, $form_state);
      $plugin->buildConversionForm($this->paragraph, $plugin_form, $sub_form_state);
      $form['plugin_form_wrapper'][$selected_plugin_id] = $plugin_form;
    }

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Convert'),
        '#button_type' => 'primary',
        '#ajax' => [
          'callback' => '::ajaxSubmit',
        ],
        '#attributes' => [
          'class' => ['lpb-btn--save'],
        ],
      ],
      'cancel' => [
        '#type' => 'button',
        '#value' => $this->t('Cancel'),
        '#ajax' => [
          'callback' => '::cancel',
        ],
        '#attributes' => [
          'class' => [
            'dialog-cancel',
            'lpb-btn--cancel',
          ],
        ],
        '#limit_validation_errors' => [],
      ],
    ];

    return $form;
  }

  /**
   * AJAX callback to update the plugin-specific sub-form on plugin selection.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   The plugin form wrapper container.
   */
  public function updatePluginForm(array &$form, FormStateInterface $form_state) {
    return $form['plugin_form_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $selected_plugin_id = $form_state->getValue('conversion_plugin');
    if ($selected_plugin_id && isset($form['plugin_form_wrapper'][$selected_plugin_id])) {
      $plugin = $this->conversionManager->createInstance($selected_plugin_id);
      $plugin_form = &$form['plugin_form_wrapper'][$selected_plugin_id];
      $sub_form_state = SubformState::createForSubform($plugin_form, $form, $form_state);
      $plugin->validateConversionForm($this->paragraph, $plugin_form, $sub_form_state);
    }
  }

  /**
   * {@inheritdoc}
   *
   * Executes the conversion and updates the tempstore layout.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selected_plugin_id = $form_state->getValue('conversion_plugin');
    $plugin = $this->conversionManager->createInstance($selected_plugin_id);
    $settings = $form_state->getValue(['plugin_form_wrapper', $selected_plugin_id], []);

    // Ensure the default language is processed first so converted paragraphs
    // are created before non-default translations are added to them.
    $original_paragraph = $this->paragraph->getUntranslated();
    $languages = array_merge(
      [$original_paragraph->language()->getId()],
      array_keys($original_paragraph->getTranslationLanguages(FALSE))
    );

    $converted_paragraphs = [];

    foreach ($languages as $langcode) {
      $translation = $original_paragraph->getTranslation($langcode);
      $paragraphs_values = $plugin->convert($settings, $translation, $converted_paragraphs);

      if (empty($paragraphs_values)) {
        continue;
      }

      foreach ($paragraphs_values as $key => $paragraph_values) {
        if ($translation->isDefaultTranslation()) {
          // Default language: create paragraph entities from returned values.
          if (is_array($paragraph_values)) {
            $converted_paragraphs[$key] = Paragraph::create($paragraph_values);
            $this->conversionManager->applyDefaultValues($original_paragraph, $converted_paragraphs[$key]);
          }
          else {
            // The plugin returned a ParagraphInterface directly.
            $converted_paragraphs[$key] = $paragraph_values;
          }
        }
        elseif (isset($converted_paragraphs[$key])) {
          // Non-default translation: add as a translation to the existing
          // converted paragraph at the same delta.
          $values = $paragraph_values instanceof ParagraphInterface
            ? $paragraph_values->toArray()
            : $paragraph_values;
          $this->conversionManager->addTranslation($converted_paragraphs[$key], $langcode, $values);
        }
      }
    }

    if (empty($converted_paragraphs)) {
      return;
    }

    // Allow modules to alter each converted paragraph.
    foreach ($converted_paragraphs as $converted_paragraph) {
      $this->moduleHandler->alter('paragraphs_conversion', $original_paragraph, $converted_paragraph);
    }

    // Insert the first converted paragraph in the original's position by
    // inserting before the original, then remove the original. Additional
    // paragraphs (1-to-many) are inserted sequentially after the first.
    $first = array_shift($converted_paragraphs);
    $this->layoutParagraphsLayout->insertBeforeComponent($this->componentUuid, $first);
    $previous_uuid = $first->uuid();
    foreach ($converted_paragraphs as $subsequent) {
      $this->layoutParagraphsLayout->insertAfterComponent($previous_uuid, $subsequent);
      $previous_uuid = $subsequent->uuid();
    }
    $this->layoutParagraphsLayout->deleteComponent($this->componentUuid);

    $this->tempstore->set($this->layoutParagraphsLayout);
  }

  /**
   * {@inheritdoc}
   *
   * Closes the dialog and refreshes the layout after a successful conversion.
   */
  public function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(Dialog::closeDialogCommand($this->layoutParagraphsLayout));
    return $this->refreshLayout($response);
  }

  /**
   * AJAX callback to close the form dialog without converting.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response.
   */
  public function cancel(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(Dialog::closeDialogCommand($this->layoutParagraphsLayout));
    return $response;
  }

  /**
   * Returns the allowed paragraph bundle types for this field.
   *
   * Reads the selection handler settings from the paragraphs reference field,
   * handles negated bundle restrictions, and dispatches the
   * LayoutParagraphsAllowedTypesEvent so that modules like
   * layout_paragraphs_restrictions can further filter the types.
   *
   * @return array
   *   An associative array of allowed bundle IDs keyed by bundle machine name.
   */
  protected function getAllowedTypes(): array {
    $field = $this->layoutParagraphsLayout->getParagraphsReferenceField();
    $settings = $field->getSettings()['handler_settings'] ?? [];
    /** @var \Drupal\paragraphs\Entity\ParagraphsType[] $all_types */
    $all_types = $this->entityTypeManager
      ->getStorage('paragraphs_type')
      ->loadMultiple();
    if (!empty($settings['negate']) && $settings['negate'] == '1') {
      $allowed_types = array_diff_key($all_types, $settings['target_bundles'] ?? []);
    }
    elseif (!empty($settings['target_bundles'])) {
      $allowed_types = array_intersect_key($all_types, $settings['target_bundles']);
    }
    else {
      $allowed_types = $all_types;
    }

    // Build the types array with is_section metadata, matching the format
    // expected by LayoutParagraphsAllowedTypesSubscriber.
    $types = [];
    foreach ($allowed_types as $bundle_id => $paragraphs_type) {
      $plugins = $paragraphs_type->getEnabledBehaviorPlugins();
      $types[$bundle_id] = ['is_section' => isset($plugins['layout_paragraphs'])];
    }

    $component = $this->layoutParagraphsLayout->getComponentByUuid($this->componentUuid);
    $context = [
      'parent_uuid' => $component->getParentUuid(),
      'region' => $component->getRegion(),
      'sibling_uuid' => NULL,
      'action' => 'convert',
    ];
    $event = new LayoutParagraphsAllowedTypesEvent($types, $this->layoutParagraphsLayout, $context);
    $this->eventDispatcher->dispatch($event, LayoutParagraphsAllowedTypesEvent::EVENT_NAME);
    return $event->getTypes();
  }

}
