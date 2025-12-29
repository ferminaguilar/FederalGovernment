<?php

namespace Drupal\layout_paragraphs\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_paragraphs\Utility\Dialog;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutRefreshTrait;

/**
 * Defines a base class for modal confirmation forms with a single "OK" button.
 */
class ModalConfirmForm extends FormBase {

  use LayoutParagraphsLayoutRefreshTrait;

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'layout_paragraphs_modal_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    ?LayoutParagraphsLayout $layout_paragraphs_layout = NULL,
    ?string $message = NULL,
  ) {

    $this->setLayoutParagraphsLayout($layout_paragraphs_layout);

    $form['message'] = [
      '#markup' => $message,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['ok'] = [
      '#type' => 'submit',
      '#value' => $this->t('OK'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => '::ajaxSubmit',
      ],
    ];

    return $form;
  }

  /**
   * AJAX callback for the submit button.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(Dialog::closeDialogCommand($this->layoutParagraphsLayout));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // No action needed on submit.
  }

}
