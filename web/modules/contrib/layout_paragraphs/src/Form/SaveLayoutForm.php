<?php

declare(strict_types=1);

namespace Drupal\layout_paragraphs\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\layout_paragraphs\LayoutChangeDetector;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Asks for the revision options before the builder saves the host entity.
 */
class SaveLayoutForm extends FormBase {

  /**
   * The layout paragraphs layout object.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsLayout
   */
  protected LayoutParagraphsLayout $layoutParagraphsLayout;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected LayoutParagraphsLayoutTempstoreRepository $tempstore,
    protected LayoutChangeDetector $changeDetector,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_paragraphs.tempstore_repository'),
      $container->get('layout_paragraphs.change_detector')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_paragraphs_save_layout_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    ?LayoutParagraphsLayout $layout_paragraphs_layout = NULL,
    bool $new_revision = FALSE,
  ) {
    $this->layoutParagraphsLayout = $layout_paragraphs_layout;
    $entity = $this->layoutParagraphsLayout->getEntity();
    $entity_type = $entity->getEntityType();

    $form['#attributes']['class'][] = 'lpb-save-layout-form';

    $url = Url::fromRoute('layout_paragraphs.builder.save', [
      'layout_paragraphs_layout' => $this->layoutParagraphsLayout->id(),
    ]);
    $form['#action'] = $url->toString();

    $has_changes = $this->changeDetector->hasChanges($this->layoutParagraphsLayout);
    $show_revision_ui = $entity instanceof RevisionableInterface
      && $entity_type->isRevisionable()
      && $entity_type->showRevisionUi();

    if (!$has_changes) {
      $form['message'] = [
        '#markup' => $this->t('There are no changes to save.'),
      ];
    }
    elseif (!$show_revision_ui) {
      $form['message'] = [
        '#markup' => $this->t('Save your changes to %label?', ['%label' => $entity->label()]),
      ];
    }
    else {
      $form['revision'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Create new revision'),
        '#default_value' => $new_revision,
        '#access' => !$entity->isNew(),
      ];
      $log_message_field = $entity_type->getRevisionMetadataKey('revision_log_message');
      if ($log_message_field
        && $entity instanceof FieldableEntityInterface
        && $entity->hasField($log_message_field)
        && $entity->get($log_message_field)->access('edit')) {
        $log_message_definition = $entity->getFieldDefinition($log_message_field);
        $form['revision_log'] = [
          '#type' => 'textarea',
          '#title' => $log_message_definition->getLabel(),
          '#description' => $log_message_definition->getDescription(),
          '#rows' => 4,
          '#states' => [
            'visible' => [
              ':input[name="revision"]' => ['checked' => TRUE],
            ],
          ],
        ];
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
      'cancel' => [
        '#type' => 'button',
        '#value' => $has_changes ? $this->t('Cancel') : $this->t('Close'),
        '#limit_validation_errors' => [],
        '#weight' => 10,
        '#attributes' => [
          'class' => ['dialog-cancel'],
        ],
      ],
    ];

    if ($has_changes) {
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#button_type' => 'primary',
        '#weight' => 0,
        '#ajax' => [
          'url' => $url,
          'options' => ['query' => [FormBuilderInterface::AJAX_FORM_REQUEST => TRUE]],
          'callback' => '::ajaxSubmit',
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->layoutParagraphsLayout->setThirdPartySetting('layout_paragraphs', 'revision', [
      'create' => (bool) $form_state->getValue('revision'),
      'log' => (string) $form_state->getValue('revision_log', ''),
    ]);
    $this->tempstore->set($this->layoutParagraphsLayout);
  }

  /**
   * Ajax callback: closes the dialog and saves the builder again.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    $selector = '[data-lpb-form-id="' . Html::getId($this->layoutParagraphsLayout->id()) . '"] .lpb-form__actions [data-drupal-selector="edit-submit"]';
    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());
    $response->addCommand(new InvokeCommand($selector, 'trigger', ['mousedown']));
    return $response;
  }

}
