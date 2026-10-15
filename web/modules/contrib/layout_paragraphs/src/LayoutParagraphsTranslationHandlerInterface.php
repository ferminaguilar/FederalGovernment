<?php

namespace Drupal\layout_paragraphs;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines an interface for the layout paragraphs translation handler.
 *
 * The translation handler decides whether a layout is edited as a translation
 * of its host entity and prepares the layout's paragraphs for the language
 * being edited.
 */
interface LayoutParagraphsTranslationHandlerInterface {

  /**
   * Determines whether the host entity is edited as a translation.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $host
   *   The host entity the layout is attached to.
   * @param string|null $langcode
   *   The language code the host entity is edited in, if known.
   * @param bool $adding_translation
   *   TRUE if a new translation of the host entity is being added.
   *
   * @return bool
   *   TRUE if the host entity is translatable and either a translation is
   *   being added or $langcode names an existing translation that is not the
   *   default translation, FALSE otherwise.
   *
   * @see \Drupal\paragraphs\Plugin\Field\FieldWidget\ParagraphsWidget::initIsTranslating()
   */
  public function isTranslating(ContentEntityInterface $host, ?string $langcode = NULL, bool $adding_translation = FALSE): bool;

  /**
   * Prepares the paragraphs of a layout for the given language.
   *
   * When not translating, each paragraph is switched to its translation in
   * $langcode if it has one, and relabeled to $langcode otherwise. When
   * translating, each translatable paragraph is switched to its translation in
   * $langcode, which is created from the source language values if it is
   * missing; untranslatable paragraphs are left as they are.
   *
   * The updated reference field is set on the layout. The layout is not saved
   * to the tempstore; that is up to the caller.
   *
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout
   *   The layout whose paragraphs are prepared.
   * @param bool $is_translating
   *   Whether the host entity is edited as a translation.
   * @param string $langcode
   *   The language code the host entity is edited in.
   * @param string|null $source_langcode
   *   The language code to create missing paragraph translations from, or
   *   NULL to use each paragraph's own language.
   *
   * @see \Drupal\layout_paragraphs\LayoutParagraphsTranslationHandlerInterface::isTranslating()
   */
  public function initTranslations(LayoutParagraphsLayout $layout, bool $is_translating, string $langcode, ?string $source_langcode = NULL): void;

}
