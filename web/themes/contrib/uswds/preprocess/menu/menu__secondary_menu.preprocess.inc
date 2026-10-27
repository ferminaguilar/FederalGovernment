<?php

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\MenuSecondaryMenuPreprocessHooks;

/**
 * @file
 * Preprocess function for this hook.
 */
/**
 * Implements hook_preprocess_menu__REGION().
 *
 * This made possible by our custom theme suggestion.
 *
 * @see uswds_theme_suggestions_menu_alter()
 */
#[LegacyHook]
function uswds_preprocess_menu__secondary_menu(&$variables)
{
    \Drupal::service(MenuSecondaryMenuPreprocessHooks::class)->preprocessMenuSecondaryMenu($variables);
}
