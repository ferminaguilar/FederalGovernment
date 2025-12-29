<?php

namespace Drupal\layout_paragraphs\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Defines a form for modifying Layout Paragraphs global settings.
 */
class LayoutParagraphsSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager service property.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * SettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Core entity type manager service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->typedConfigManager = $typedConfigManager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_paragraphs_settings';
  }

  /**
   * Returns all available paragraph types.
   *
   * @return array
   *   An array of paragraph type entities keyed by their IDs.
   */
  public function getParagraphTypes() {
    return $this->entityTypeManager
      ->getStorage('paragraphs_type')
      ->loadMultiple();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'layout_paragraphs.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $lp_config = $this->configFactory()->getEditable('layout_paragraphs.settings');
    $lp_config_schema = $this->typedConfigManager->getDefinition('layout_paragraphs.settings') + ['mapping' => []];
    $lp_config_schema = $lp_config_schema['mapping'];
    $lp_paragraph_types = $this->getParagraphTypes();

    $form['show_paragraph_labels'] = [
      '#type' => 'checkbox',
      '#title' => $lp_config_schema['show_paragraph_labels']['label'],
      '#description' => $lp_config_schema['show_paragraph_labels']['description'],
      '#default_value' => $lp_config->get('show_paragraph_labels'),
    ];

    $form['show_layout_labels'] = [
      '#type' => 'checkbox',
      '#title' => $lp_config_schema['show_layout_labels']['label'],
      '#description' => $lp_config_schema['show_layout_labels']['description'],
      '#default_value' => $lp_config->get('show_layout_labels'),
    ];

    $form['show_layout_plugin_labels'] = [
      '#type' => 'checkbox',
      '#title' => $lp_config_schema['show_layout_plugin_labels']['label'],
      '#description' => $lp_config_schema['show_layout_plugin_labels']['description'],
      '#default_value' => $lp_config->get('show_layout_plugin_labels'),
    ];

    $form['paragraph_behaviors_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Paragraph Behaviors Fieldset Label'),
      '#default_value' => $lp_config->get('paragraph_behaviors_label') ?? $this->t('Behaviors'),
    ];

    $form['paragraph_behaviors_position'] = [
      '#type' => 'radios',
      '#title' => $this->t('Paragraph Behaviors Fieldset Position'),
      '#options' => [
        '-99' => $this->t('Top of paragraph edit form'),
        '99' => $this->t('Bottom of paragraph edit form'),
      ],
      '#default_value' => $lp_config->get('paragraph_behaviors_position') ?? '-99',
    ];

    $form['empty_message'] = [
      '#type' => 'textfield',
      '#title' => $lp_config_schema['empty_message']['label'],
      '#description' => $lp_config_schema['empty_message']['description'],
      '#default_value' => $lp_config->get('empty_message') ?? 'No components to add.',
    ];

    $form['button_labels'] = [
      '#type' => 'details',
      '#title' => $this->t('Tooltip Labels for the Add Component Button (+)'),
      '#tree' => TRUE,
      '#open' => TRUE,
    ];

    $form['button_labels']['enable_tooltips'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Tooltips for the Add Component Button (+)'),
      '#default_value' => $lp_config->get('button_labels')['enable_tooltips'] ?? FALSE,
    ];

    $form['button_labels']['default'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default'),
      '#default_value' => $lp_config->get('button_labels')['default'] ?? 'Add Component',
      '#description' => $this->t('Default label for the Add Component button (+) when no specific label is set for a paragraph type.'),
    ];

    $form['button_labels']['root'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Root Level'),
      '#default_value' => $lp_config->get('button_labels')['root'] ?? 'Add Section',
      '#description' => $this->t('Label for the Add Component button (+) at the root level of a layout paragraph builder.'),
    ];

    foreach ($lp_paragraph_types as $type) {
      $behavior_plugins = $type->getEnabledBehaviorPlugins();
      $type_id = $type->id();

      if (isset($behavior_plugins['layout_paragraphs'])) {
        $form['button_labels'][$type_id] = [
          '#type' => 'textfield',
          '#title' => $type->label(),
          '#default_value' => $lp_config->get('button_labels')[$type_id] ?? '',
          '#description' => $this->t('Label for the Add Component buttons (+) inside the @type paragraph type.', ['@type' => $type->label()]),
        ];
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $lp_config = $this->configFactory()->getEditable('layout_paragraphs.settings');
    $lp_config->set('show_paragraph_labels', $form_state->getValue('show_paragraph_labels'));
    $lp_config->set('show_layout_labels', $form_state->getValue('show_layout_labels'));
    $lp_config->set('show_layout_plugin_labels', $form_state->getValue('show_layout_plugin_labels'));
    $lp_config->set('paragraph_behaviors_label', $form_state->getValue('paragraph_behaviors_label'));
    $lp_config->set('paragraph_behaviors_position', $form_state->getValue('paragraph_behaviors_position'));
    $lp_config->set('empty_message', $form_state->getValue('empty_message'));
    $lp_config->set('button_labels', $form_state->getValue('button_labels'));
    $lp_config->save();

    // Confirmation on form submission.
    $this->messenger()->addMessage($this->t('The Layout Paragraphs settings have been saved.'));
  }

}
