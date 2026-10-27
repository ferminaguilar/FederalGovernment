<?php

namespace Drupal\uswds\Hook;

use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class InputEmailPreprocessHooks
{
    /**
     * @file
     * Preprocess function for this hook.
     */
    /**
     * Implements hook_preprocess_input__email().
     */
    #[Hook('preprocess_input__email')]
    public function preprocessInputEmail(&$variables)
    {
        $variables['attributes']['class'][] = 'usa-input';
    }
}
