<?php

namespace Drupal\uswds\Hook;

use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class ViewsViewTablePreprocessHooks
{
    /**
     * @file
     * Preprocess function for this hook.
     */
    /**
     * Implements hook_preprocess_views_view_table().
     */
    #[Hook('preprocess_views_view_table')]
    public function preprocessViewsViewTable(&$variables)
    {
        $variables['attributes']['class'][] = 'usa-table';
    }
}
