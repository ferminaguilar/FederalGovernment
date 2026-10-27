<?php

namespace Drupal\uswds\Hook;

use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class BlockSystemMenuBlockSidebarFirstPreprocessHooks
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
    #[Hook('preprocess_block__system_menu_block__sidebar_first')]
    public function preprocessBlockSystemMenuBlockSidebarFirst(&$variables)
    {
        _uswds_mark_menu_items($variables, 'sidebar_first');
    }
}
