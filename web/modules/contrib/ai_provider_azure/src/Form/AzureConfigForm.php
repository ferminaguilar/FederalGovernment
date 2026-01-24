<?php

namespace Drupal\ai_provider_azure\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Service\AiProviderFormHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Azure Provider API access.
 */
class AzureConfigForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The AI Provider service.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProviderManager;

  /**
   * The form helper.
   *
   * @var \Drupal\ai\Service\AiProviderFormHelper
   */
  protected $formHelper;

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai_provider_azure.settings';

  /**
   * Constructs a new Azure Provider Config object.
   */
  final public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AiProviderPluginManager $ai_provider_manager,
    AiProviderFormHelper $form_helper,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->aiProviderManager = $ai_provider_manager;
    $this->formHelper = $form_helper;
  }

  /**
   * {@inheritdoc}
   */
  final public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('ai.provider'),
      $container->get('ai.form_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'azure_ai_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $provider = $this->aiProviderManager->createInstance('azure');
    $form['models'] = $this->formHelper->getModelsTable($form, $form_state, $provider);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Do nothing for now.
  }

}
