<?php

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\FormElementPreprocessHooks;

/**
 * @file
 * Preprocess function for this hook.
 */
/**
 * Implements hook_preprocess_form_element().
 */
#[LegacyHook]
function uswds_preprocess_form_element(&$variables)
{
    \Drupal::service(FormElementPreprocessHooks::class)->preprocessFormElement($variables);
}
