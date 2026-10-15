<?php

declare(strict_types=1);

namespace Drupal\layout_paragraphs\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form for modifying Layout Paragraphs builder settings.
 */
class LayoutParagraphsBuilderSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_paragraphs_builder_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'layout_paragraphs.builder_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['double_click_to_edit'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Double-click to edit'),
      '#description' => $this->t('Open the edit form of a component when it is double-clicked in the builder.'),
      '#default_value' => $this->config('layout_paragraphs.builder_settings')->get('double_click_to_edit'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('layout_paragraphs.builder_settings')
      ->set('double_click_to_edit', (bool) $form_state->getValue('double_click_to_edit'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
