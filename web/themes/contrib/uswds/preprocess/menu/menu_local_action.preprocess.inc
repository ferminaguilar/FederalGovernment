<?php

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\MenuLocalActionPreprocessHooks;

/**
 * @file
 * Preprocess function for this hook.
 */
/**
 * Implements hook_preprocess_menu_local_action().
 */
#[LegacyHook]
function uswds_preprocess_menu_local_action(&$variables)
{
    \Drupal::service(MenuLocalActionPreprocessHooks::class)->preprocessMenuLocalAction($variables);
}
