<?php

namespace Drupal\uswds\Hook;

use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class InputTextfieldPreprocessHooks
{
    /**
     * @file
     * Preprocess function for this hook.
     */
    /**
     * Implements hook_preprocess_input__textfield().
     */
    #[Hook('preprocess_input__textfield')]
    public function preprocessInputTextfield(&$variables)
    {
        $variables['attributes']['class'][] = 'usa-input';
    }
}
