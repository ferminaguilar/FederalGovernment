<?php

namespace Drupal\uswds\Hook;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class SearchHooks
{
    /**
     * Implements hook_form_FORM_ID_alter().
     */
    #[Hook('form_search_block_form_alter')]
    public function formSearchBlockFormAlter(&$form, \Drupal\Core\Form\FormStateInterface $form_state, $form_id)
    {
        $bypass = _uswds_process_search();
        if (!$bypass) {
            $search_setting = \Drupal\Component\Utility\DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(\Drupal\Core\Extension\ThemeSettingsProvider::class)->getSetting('uswds_search_style'), fn() => theme_get_setting('uswds_search_style')) ?? 'small';
            // No need to control the size.
            unset($form['keys']['#size']);
            $form['keys']['#label_attributes']['class'][] = 'usa-sr-only';
            // Add a unique ID for the search input.
            $form['keys']['#attributes']['id'] = "edit-search-keys";
            // Add add SR label and aria-label.
            $form['actions']['submit']['#prefix'] = '<span class="usa-sr-only">Search</span>';
            $form['actions']['submit']['#attributes']['aria-label'] = 'Submit Search';
            // Unique ID for actions.
            $form['actions']['#attributes']['id'] = "search-actions";
            // Unique ID for search form submit.
            $form['actions']['submit']['#id'] = 'search-form--submit';
            if ($search_setting === 'small') {
                $form['actions']['submit']['#value'] = '';
            }
        }
    }
}
