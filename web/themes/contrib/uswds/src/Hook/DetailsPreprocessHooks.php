<?php

namespace Drupal\uswds\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Element;
use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class DetailsPreprocessHooks
{
    /**
     * Implements hook_preprocess_details().
     */
    #[Hook('preprocess_details')]
    public function preprocessDetails(&$variables)
    {
        // Add the necessary class to the summary (which we'll render as a button).
        $variables['summary_attributes']->addClass('usa-accordion__button');
        // Check to see if this should be uncollapsible. This could be the case if
        // if has errors in it, or if it has been specifically flagged as such.
        $uncollapsible = !empty($variables['element']['#uswds_uncollapsible']);
        if (!$uncollapsible) {
            $inline_form_errors = \Drupal::moduleHandler()->moduleExists('inline_form_errors');
            if ($inline_form_errors) {
                $uncollapsible = _uswds_is_error_in_array($variables['element']);
            }
        }
        if ($uncollapsible) {
            $variables['element']['#open'] = TRUE;
            $variables['uncollapsible'] = TRUE;
        } else if (empty($variables['title'])) {
            $variables['title'] = '&nbsp;';
        }
        // Drupal defaults to open/expanded details, unless '#open' is FALSE, so we
        // have to match that, even though it would be nice to default to closed.
        $open = 'true';
        if (isset($variables['element']['#open']) && !$variables['element']['#open']) {
            $open = 'false';
        }
        $variables['summary_attributes']['aria-expanded'] = $open;
        if (empty($variables['attributes']['id'])) {
            $variables['attributes']['id'] = \Drupal\Component\Utility\Html::getUniqueId('uswds-accordion');
        }
    }
}
