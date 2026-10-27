<?php

namespace Drupal\uswds\Hook;

use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class MenuLocalActionPreprocessHooks
{
    /**
     * @file
     * Preprocess function for this hook.
     */
    /**
     * Implements hook_preprocess_menu_local_action().
     */
    #[Hook('preprocess_menu_local_action')]
    public function preprocessMenuLocalAction(&$variables)
    {
        $variables['link']['#options']['attributes']['class'][] = 'usa-button';
        $variables['link']['#options']['attributes']['class'][] = 'usa-button--outline';
    }
}
