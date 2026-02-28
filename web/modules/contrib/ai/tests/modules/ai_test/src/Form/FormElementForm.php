<?php

namespace Drupal\ai_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form to showcase the AI form elements.
 */
class FormElementForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_test_form_element_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['chat_history'] = [
      '#type' => 'chat_history',
      '#title' => $this->t('Chat History'),
      '#description' => $this->t('A form element for managing chat history.'),
      '#default_value' => [
        [
          'role' => 'user',
          'content' => 'Hello, how can you help me?',
        ],
      ],
    ];

    $form['hr_1'] = [
      '#type' => 'markup',
      '#markup' => '<hr>',
    ];

    $form['ai_tools_library'] = [
      '#type' => 'ai_tools_library',
      '#title' => $this->t('AI Tools Library'),
      '#description' => $this->t('A form element for selecting AI tools.'),
      '#default_value' => '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // No submit handling needed for this showcase form.
  }

}
