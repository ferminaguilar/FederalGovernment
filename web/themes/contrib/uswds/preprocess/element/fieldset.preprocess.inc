<?php

/**
 * @file
 * Preprocess function for fieldsets.
 */

/**
 * Implements hook_preprocess_hook().
 */
function uswds_preprocess_fieldset(&$variables) {
  $variables['attributes']['class'][] = 'usa-fieldset';
  /** @var \Drupal\Core\Template\Attribute $legend_attributes */
  $legend_attributes = $variables['legend']['attributes'];
  $legend_attributes->addClass('usa-legend');
  // Add required class.
  if (!empty($variables['required'])) {
    $variables['attributes']['class'][] = 'usa-input-required';
  }
}
