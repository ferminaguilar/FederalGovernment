<?php

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\InputSearchPreprocessHooks;

/**
 * @file
 * Preprocess function for this hook.
 */
/**
 * Implements hook_preprocess_input__search().
 */
#[LegacyHook]
function uswds_preprocess_input__search(&$variables) {
  \Drupal::service(InputSearchPreprocessHooks::class)->preprocessInputSearch($variables);
}
