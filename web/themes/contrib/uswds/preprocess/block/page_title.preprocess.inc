<?php

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\PageTitlePreprocessHooks;

/**
 * @file
 * Preprocess function for this hook.
 */
/**
 * Implements hook_preprocess_page_title().
 */
#[LegacyHook]
function uswds_preprocess_page_title(&$variables) {
  \Drupal::service(PageTitlePreprocessHooks::class)->preprocessPageTitle($variables);
}
