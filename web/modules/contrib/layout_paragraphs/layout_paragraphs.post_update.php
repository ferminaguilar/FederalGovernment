<?php

/**
 * @file
 * Post update functions for Layout Paragraphs.
 */

/**
 * Rebuild the container for the translation handler service.
 */
function layout_paragraphs_post_update_translation_handler() {
}

/**
 * Add the builder settings with double-click to edit enabled.
 */
function layout_paragraphs_post_update_builder_settings() {
  \Drupal::configFactory()->getEditable('layout_paragraphs.builder_settings')
    ->set('double_click_to_edit', TRUE)
    ->save(TRUE);
}
