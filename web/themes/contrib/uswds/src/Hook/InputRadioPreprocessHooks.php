<?php

namespace Drupal\uswds\Hook;

use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class InputRadioPreprocessHooks
{
    /**
     * @file
     * Preprocess function for this hook.
     */
    /**
     * Implements hook_preprocess_input__radio().
     */
    #[Hook('preprocess_input__radio')]
    public function preprocessInputRadio(&$variables)
    {
        $variables['attributes']['class'][] = 'usa-radio__input';
    }
}
