<?php

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\PagePreprocessHooks;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\ThemeSettingsProvider;

/**
 * @file
 * Preprocess function for this hook.
 */
/**
 * Implements hook_preprocess_page().
 */
#[LegacyHook]
function uswds_preprocess_page(&$variables)
{
    \Drupal::service(PagePreprocessHooks::class)->preprocessPage($variables);
}
