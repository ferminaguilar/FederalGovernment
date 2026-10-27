<?php

namespace Drupal\uswds\Hook;

use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class SelectPreprocessHooks
{
    /**
     * @file
     * Preprocess function for this hook.
     */
    /**
     * Implements hook_preprocess_select().
     */
    #[Hook('preprocess_select')]
    public function preprocessSelect(&$variables)
    {
        // Add the usa-select class to select form elements.
        $variables['attributes']['class'][] = 'usa-select';
    }
}
