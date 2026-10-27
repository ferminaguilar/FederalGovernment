<?php

namespace Drupal\uswds\Hook;

use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class InputPasswordPreprocessHooks
{
    /**
     * @file
     * Preprocess function for this hook.
     */
    /**
     * Implements hook_preprocess_input__password().
     */
    #[Hook('preprocess_input__password')]
    public function preprocessInputPassword(&$variables)
    {
        $variables['attributes']['class'][] = 'usa-input';
    }
}
