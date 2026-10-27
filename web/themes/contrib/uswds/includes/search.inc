<?php

/**
 * @file
 * Utility code related to search block.
 */
use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\SearchHooks;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Form\FormStateInterface;

/**
 * Helper function to see if we should alter the search.
 *
 * @return bool
 */
function _uswds_process_search() {
  $search_bypass = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('uswds_search_bypass'), fn() => theme_get_setting('uswds_search_bypass'));

  return (!empty($search_bypass));
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
#[LegacyHook]
function uswds_form_search_block_form_alter(&$form, FormStateInterface $form_state, $form_id)
{
    \Drupal::service(SearchHooks::class)->formSearchBlockFormAlter($form, $form_state, $form_id);
}

function uswds_preprocess_form(&$vars) {
  $bypass = _uswds_process_search();
  if (!$bypass && $vars['element']['#form_id'] == 'search_block_form') {
    $search_setting = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('uswds_search_style'), fn() => theme_get_setting('uswds_search_style')) ?? 'small';
    if ($search_setting !== 'default') {
      $vars['attributes']['class'][] = 'usa-search--' . $search_setting;
    }
    $vars['attributes']['class'][] = 'usa-search';
    $vars['attributes']['role'][] = 'search';
  }
}
