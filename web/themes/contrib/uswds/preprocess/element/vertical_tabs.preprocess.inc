<?php

/**
 * @file
 * Preprocess function for this hook.
 */
use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\VerticalTabsPreprocessHooks;
use Drupal\Core\Render\Markup;

/**
 * Implements hook_preprocess_vertical_tabs().
 */
#[LegacyHook]
function uswds_preprocess_vertical_tabs(&$variables)
{
    \Drupal::service(VerticalTabsPreprocessHooks::class)->preprocessVerticalTabs($variables);
}
