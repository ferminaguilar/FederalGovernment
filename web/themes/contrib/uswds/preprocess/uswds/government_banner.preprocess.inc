<?php

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\GovernmentBannerPreprocessHooks;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\ThemeSettingsProvider;

/**
 * @file
 * Preprocess function for this hook.
 */
/**
 * Implements hook_preprocess_government_banner().
 */
#[LegacyHook]
function uswds_preprocess_government_banner(&$variables)
{
    \Drupal::service(GovernmentBannerPreprocessHooks::class)->preprocessGovernmentBanner($variables);
}
