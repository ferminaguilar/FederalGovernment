<?php

namespace Drupal\uswds\Hook;

use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class InputSearchPreprocessHooks
{
    /**
     * @file
     * Preprocess function for this hook.
     */
    /**
     * Implements hook_preprocess_input__search().
     */
    #[Hook('preprocess_input__search')]
    public function preprocessInputSearch(&$variables)
    {
        $variables['attributes']['class'][] = 'usa-input';
    }
}
