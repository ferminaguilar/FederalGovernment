<?php

namespace Drupal\uswds\Hook;

use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class TextareaPreprocessHooks
{
    /**
     * @file
     * Preprocess function for this hook.
     */
    /**
     * Implements hook_preprocess_textarea().
     */
    #[Hook('preprocess_textarea')]
    public function preprocessTextarea(&$variables)
    {
        $variables['attributes']['class'][] = 'usa-textarea';
    }
}
