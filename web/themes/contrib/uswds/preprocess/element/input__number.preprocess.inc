<?php

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\InputNumberPreprocessHooks;

/**
 * @file
 * Preprocess function for this hook.
 */
/**
 * Implements hook_preprocess_input__number().
 */
#[LegacyHook]
function uswds_preprocess_input__number(&$variables) {
  \Drupal::service(InputNumberPreprocessHooks::class)->preprocessInputNumber($variables);
}
