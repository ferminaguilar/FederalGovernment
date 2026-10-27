<?php

/**
 * @file
 * Alterations for this form.
 */
use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\NodeFormHooks;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_node_form_alter().
 */
#[LegacyHook]
function uswds_form_node_form_alter(&$form, FormStateInterface $form_state, $form_id)
{
    \Drupal::service(NodeFormHooks::class)->formNodeFormAlter($form, $form_state, $form_id);
}
