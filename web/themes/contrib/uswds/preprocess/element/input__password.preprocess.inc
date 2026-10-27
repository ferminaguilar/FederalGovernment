<?php

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\InputPasswordPreprocessHooks;

/**
 * @file
 * Preprocess function for this hook.
 */
/**
 * Implements hook_preprocess_input__password().
 */
#[LegacyHook]
function uswds_preprocess_input__password(&$variables) {
  \Drupal::service(InputPasswordPreprocessHooks::class)->preprocessInputPassword($variables);
}
