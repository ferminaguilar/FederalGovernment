<?php

/**
 * @file
 * Alterations for this form.
 */
use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\UserLoginFormFormHooks;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_FORM_ID_alter().
 */
#[LegacyHook]
function uswds_form_user_login_form_alter(&$form, FormStateInterface $form_state, $form_id)
{
    \Drupal::service(UserLoginFormFormHooks::class)->formUserLoginFormAlter($form, $form_state, $form_id);
}
