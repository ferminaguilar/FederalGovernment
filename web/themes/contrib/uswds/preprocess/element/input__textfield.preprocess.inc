<?php

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\InputTextfieldPreprocessHooks;

/**
 * @file
 * Preprocess function for this hook.
 */
/**
 * Implements hook_preprocess_input__textfield().
 */
#[LegacyHook]
function uswds_preprocess_input__textfield(&$variables) {
  \Drupal::service(InputTextfieldPreprocessHooks::class)->preprocessInputTextfield($variables);
}
