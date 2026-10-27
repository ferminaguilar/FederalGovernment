<?php

namespace Drupal\uswds\Hook;

use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class InputNumberPreprocessHooks
{
    /**
     * @file
     * Preprocess function for this hook.
     */
    /**
     * Implements hook_preprocess_input__number().
     */
    #[Hook('preprocess_input__number')]
    public function preprocessInputNumber(&$variables)
    {
        $variables['attributes']['class'][] = 'usa-input';
    }
}
