<?php

/**
 * @file
 * Preprocess function for this hook.
 */
use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\DetailsPreprocessHooks;
use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Element;

/**
 * Implements hook_preprocess_details().
 */
#[LegacyHook]
function uswds_preprocess_details(&$variables)
{
    \Drupal::service(DetailsPreprocessHooks::class)->preprocessDetails($variables);
}

/**
 * Helper method to find errors in a render array.
 */
function _uswds_is_error_in_array($element) {
  if (!empty($element['#errors'])) {
    return TRUE;
  }
  foreach (Element::children($element) as $key) {
    if (_uswds_is_error_in_array($element[$key])) {
      return TRUE;
    }
  }
  return FALSE;
}
