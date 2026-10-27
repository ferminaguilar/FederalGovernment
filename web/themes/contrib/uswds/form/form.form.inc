<?php

/**
 * @file
 * Alterations for ALL forms.
 */
use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\FormFormHooks;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\webform\WebformSubmissionForm;

/**
 * Implements hook_form_alter().
 */
#[LegacyHook]
function uswds_form_alter(&$form, FormStateInterface $form_state, $form_id) {
  \Drupal::service(FormFormHooks::class)->formAlter($form, $form_state, $form_id);
}
