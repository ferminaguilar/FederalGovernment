<?php

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\BlockSystemMenuBlockFooterMenuPreprocessHooks;

/**
 * @file
 * Preprocess function for this hook.
 */
/**
 * Implements hook_preprocess_block__DELTA__REGION().
 *
 * This made possible by our custom theme suggestion.
 *
 * @see uswds_theme_suggestions_block_alter()
 */
#[LegacyHook]
function uswds_preprocess_block__system_menu_block__footer_menu(&$variables) {
  \Drupal::service(BlockSystemMenuBlockFooterMenuPreprocessHooks::class)->preprocessBlockSystemMenuBlockFooterMenu($variables);
}
