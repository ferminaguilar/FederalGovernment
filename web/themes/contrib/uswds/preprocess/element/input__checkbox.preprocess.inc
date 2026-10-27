<?php

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\InputCheckboxPreprocessHooks;

/**
 * @file
 * Preprocess function for this hook.
 */
/**
 * Implements hook_preprocess_input__checkbox().
 */
#[LegacyHook]
function uswds_preprocess_input__checkbox(&$variables) {
  \Drupal::service(InputCheckboxPreprocessHooks::class)->preprocessInputCheckbox($variables);
}
