<?php

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\InputEmailPreprocessHooks;

/**
 * @file
 * Preprocess function for this hook.
 */
/**
 * Implements hook_preprocess_input__email().
 */
#[LegacyHook]
function uswds_preprocess_input__email(&$variables) {
  \Drupal::service(InputEmailPreprocessHooks::class)->preprocessInputEmail($variables);
}
