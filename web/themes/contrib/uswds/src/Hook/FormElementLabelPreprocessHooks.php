<?php

namespace Drupal\uswds\Hook;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class FormElementLabelPreprocessHooks
{
    /**
     * @file
     * Preprocess function for this hook.
     */
    /**
     * Implements hook_preprocess_form_element_label().
     */
    #[Hook('preprocess_form_element_label')]
    public function preprocessFormElementLabel(&$variables)
    {
        $variables['attributes']['class'][] = 'usa-label';
        if (!empty($variables['required'])) {
            $variables['attributes']['class'][] = 'usa-input--required';
        }
        $form_optional_label = \Drupal\Component\Utility\DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(\Drupal\Core\Extension\ThemeSettingsProvider::class)->getSetting('uswds_form_optional_label'), fn() => theme_get_setting('uswds_form_optional_label'));
        $variables['form_optional_label'] = $form_optional_label === NULL ? TRUE : $form_optional_label;
    }
}
