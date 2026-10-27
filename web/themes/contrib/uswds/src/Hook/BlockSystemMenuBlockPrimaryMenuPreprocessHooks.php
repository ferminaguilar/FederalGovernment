<?php

namespace Drupal\uswds\Hook;

use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class BlockSystemMenuBlockPrimaryMenuPreprocessHooks
{
    /**
     * @file
     * Preprocess function for this hook.
     */
    /**
     * Implements hook_preprocess_block__DELTA__REGION().
     *
     * This made possible by our custom theme suggestion.
     *
     * @see uswds_theme_suggestions_block_alter()
     */
    #[Hook('preprocess_block__system_menu_block__primary_menu')]
    public function preprocessBlockSystemMenuBlockPrimaryMenu(&$variables)
    {
        _uswds_mark_menu_items($variables, 'primary_menu');
    }
}
