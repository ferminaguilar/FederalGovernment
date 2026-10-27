<?php

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\TextareaPreprocessHooks;

/**
 * @file
 * Preprocess function for this hook.
 */
/**
 * Implements hook_preprocess_textarea().
 */
#[LegacyHook]
function uswds_preprocess_textarea(&$variables) {
  \Drupal::service(TextareaPreprocessHooks::class)->preprocessTextarea($variables);
}
