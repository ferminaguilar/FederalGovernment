<?php

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\uswds\Hook\ParagraphPreprocessHooks;

/**
 * @file
 * Preprocess function for blocks.
 */
/**
 * Implements hook_preprocess_paragraph().
 */
#[LegacyHook]
function uswds_preprocess_paragraph(&$variables)
{
    \Drupal::service(ParagraphPreprocessHooks::class)->preprocessParagraph($variables);
}
