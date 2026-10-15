<?php

declare(strict_types=1);

namespace Drupal\layout_paragraphs\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirms closing the builder with unsaved changes.
 */
class CloseLayoutForm extends FormBase {

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
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_paragraphs.tempstore_repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_paragraphs_close_layout_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    ?LayoutParagraphsLayout $layout_paragraphs_layout = NULL,
  ) {
    $this->layoutParagraphsLayout = $layout_paragraphs_layout;
    $field_label = $this->layoutParagraphsLayout
      ->getParagraphsReferenceField()
      ->getFieldDefinition()
      ->getLabel();

    $form['#attributes']['class'][] = 'lpb-close-layout-form';

    $url = Url::fromRoute('layout_paragraphs.builder.close', [
      'layout_paragraphs_layout' => $this->layoutParagraphsLayout->id(),
    ]);
    $form['#action'] = $url->toString();

    $form['confirm'] = [
      '#markup' => $this->t('Leave without saving? The changes you made to @field_label will be lost.', [
        '@field_label' => $field_label,
      ]),
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Leave without saving'),
        '#button_type' => 'danger',
        '#ajax' => [
          'url' => $url,
          'options' => ['query' => [FormBuilderInterface::AJAX_FORM_REQUEST => TRUE]],
          'callback' => '::ajaxSubmit',
        ],
        '#attributes' => [
          'data-disable-refocus' => 'true',
        ],
      ],
      'cancel' => [
        '#type' => 'button',
        '#value' => $this->t('Continue editing'),
        '#limit_validation_errors' => [],
        '#attributes' => [
          'class' => ['dialog-cancel'],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->layoutParagraphsLayout->setThirdPartySetting('layout_paragraphs', 'discard', TRUE);
    $this->tempstore->set($this->layoutParagraphsLayout);
  }

  /**
   * Ajax callback: closes the dialog and closes the builder again.
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
    $selector = '[data-lpb-form-id="' . Html::getId($this->layoutParagraphsLayout->id()) . '"] .lpb-btn--cancel';
    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());
    $response->addCommand(new InvokeCommand($selector, 'trigger', ['mousedown']));
    return $response;
  }

}
