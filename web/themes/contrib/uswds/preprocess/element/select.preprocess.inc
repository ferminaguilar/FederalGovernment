<?php

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\SelectPreprocessHooks;

/**
 * @file
 * Preprocess function for this hook.
 */
/**
 * Implements hook_preprocess_select().
 */
#[LegacyHook]
function uswds_preprocess_select(&$variables) {
  \Drupal::service(SelectPreprocessHooks::class)->preprocessSelect($variables);
}
