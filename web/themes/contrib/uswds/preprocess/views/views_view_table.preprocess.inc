<?php

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\ViewsViewTablePreprocessHooks;

/**
 * @file
 * Preprocess function for this hook.
 */
/**
 * Implements hook_preprocess_views_view_table().
 */
#[LegacyHook]
function uswds_preprocess_views_view_table(&$variables) {
  \Drupal::service(ViewsViewTablePreprocessHooks::class)->preprocessViewsViewTable($variables);
}
