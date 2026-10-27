<?php

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\FormElementLabelPreprocessHooks;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\ThemeSettingsProvider;

/**
 * @file
 * Preprocess function for this hook.
 */
/**
 * Implements hook_preprocess_form_element_label().
 */
#[LegacyHook]
function uswds_preprocess_form_element_label(&$variables)
{
    \Drupal::service(FormElementLabelPreprocessHooks::class)->preprocessFormElementLabel($variables);
}
