<?php

namespace Drupal\uswds\Hook;

use Drupal\Core\Render\Markup;
use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class VerticalTabsPreprocessHooks
{
    /**
     * Implements hook_preprocess_vertical_tabs().
     */
    #[Hook('preprocess_vertical_tabs')]
    public function preprocessVerticalTabs(&$variables)
    {
        $prefix = '<ul class="usa-accordion usa-accordion--bordered add-list-reset">';
        $suffix = '</ul><!-- USWDS fieldset end -->';
        $content = $variables['children'];
        $content = str_replace($prefix, '', $content);
        $content = str_replace($suffix, '', $content);
        $variables['children'] = \Drupal\Core\Render\Markup::create($content);
        $variables['attributes']['class'][] = 'usa-accordion usa-accordion--bordered';
    }
}
