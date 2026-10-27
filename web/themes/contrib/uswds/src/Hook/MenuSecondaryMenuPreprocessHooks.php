<?php

namespace Drupal\uswds\Hook;

use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class MenuSecondaryMenuPreprocessHooks
{
    /**
     * @file
     * Preprocess function for this hook.
     */
    /**
     * Implements hook_preprocess_menu__REGION().
     *
     * This made possible by our custom theme suggestion.
     *
     * @see uswds_theme_suggestions_menu_alter()
     */
    #[Hook('preprocess_menu__secondary_menu')]
    public function preprocessMenuSecondaryMenu(&$variables)
    {
    }
}
