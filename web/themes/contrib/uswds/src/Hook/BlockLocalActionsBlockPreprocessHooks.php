<?php

namespace Drupal\uswds\Hook;

use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class BlockLocalActionsBlockPreprocessHooks
{
    /**
     * @file
     * Preprocess function for this hook.
     */
    /**
     * Implements hook_preprocess_block__local_actions_block().
     */
    #[Hook('preprocess_block__local_actions_block')]
    public function preprocessBlockLocalActionsBlock(&$variables)
    {
        $variables['attributes']['class'][] = 'uswds-horizontal-list';
    }
}
