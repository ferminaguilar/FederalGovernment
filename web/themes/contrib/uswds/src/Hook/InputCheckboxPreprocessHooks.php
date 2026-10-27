<?php

namespace Drupal\uswds\Hook;

use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class InputCheckboxPreprocessHooks
{
    /**
     * @file
     * Preprocess function for this hook.
     */
    /**
     * Implements hook_preprocess_input__checkbox().
     */
    #[Hook('preprocess_input__checkbox')]
    public function preprocessInputCheckbox(&$variables)
    {
        $variables['attributes']['class'][] = 'usa-checkbox__input';
    }
}
