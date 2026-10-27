<?php

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\InputRadioPreprocessHooks;

/**
 * @file
 * Preprocess function for this hook.
 */
/**
 * Implements hook_preprocess_input__radio().
 */
#[LegacyHook]
function uswds_preprocess_input__radio(&$variables) {
  \Drupal::service(InputRadioPreprocessHooks::class)->preprocessInputRadio($variables);
}
