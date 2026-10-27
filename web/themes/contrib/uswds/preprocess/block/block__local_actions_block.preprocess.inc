<?php

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\BlockLocalActionsBlockPreprocessHooks;

/**
 * @file
 * Preprocess function for this hook.
 */
/**
 * Implements hook_preprocess_block__local_actions_block().
 */
#[LegacyHook]
function uswds_preprocess_block__local_actions_block(&$variables) {
  \Drupal::service(BlockLocalActionsBlockPreprocessHooks::class)->preprocessBlockLocalActionsBlock($variables);
}
