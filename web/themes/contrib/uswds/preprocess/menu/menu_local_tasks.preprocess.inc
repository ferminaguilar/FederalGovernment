<?php

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\MenuLocalTasksPreprocessHooks;

/**
 * @file
 * Preprocess function for this hook.
 */
/**
 * Implements hook_preprocess_menu_local_tasks().
 */
#[LegacyHook]
function uswds_preprocess_menu_local_tasks(&$variables)
{
    \Drupal::service(MenuLocalTasksPreprocessHooks::class)->preprocessMenuLocalTasks($variables);
}
