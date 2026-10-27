<?php

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\MenuPrimaryMenuPreprocessHooks;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\ThemeSettingsProvider;

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
function uswds_preprocess_menu__primary_menu(&$variables)
{
    \Drupal::service(MenuPrimaryMenuPreprocessHooks::class)->preprocessMenuPrimaryMenu($variables);
}
