<?php

namespace Drupal\layout_paragraphs;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Handles the translation of layout paragraphs.
 */
class LayoutParagraphsTranslationHandler implements LayoutParagraphsTranslationHandlerInterface {

  /**
   * The content translation manager, if the module is installed.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface|null
   */
  protected $contentTranslationManager;

  /**
   * Constructs a LayoutParagraphsTranslationHandler object.
   *
   * @param \Drupal\content_translation\ContentTranslationManagerInterface|null $content_translation_manager
   *   The content translation manager, or NULL if the content translation
   *   module is not installed.
   */
  public function __construct(?ContentTranslationManagerInterface $content_translation_manager = NULL) {
    $this->contentTranslationManager = $content_translation_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function isTranslating(ContentEntityInterface $host, ?string $langcode = NULL, bool $adding_translation = FALSE): bool {
    if (!$host->isTranslatable()) {
      return FALSE;
    }
    if (!$host->getEntityType()->hasKey('default_langcode')) {
      return FALSE;
    }
    $default_langcode_key = $host->getEntityType()->getKey('default_langcode');
    if (!$host->hasField($default_langcode_key)) {
      return FALSE;
    }

    if ($adding_translation) {
      // Adding a translation.
      return TRUE;
    }
    if (isset($langcode) && $host->hasTranslation($langcode) && $host->getTranslation($langcode)->get($default_langcode_key)->value == 0) {
      // Editing a translation.
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function initTranslations(LayoutParagraphsLayout $layout, bool $is_translating, string $langcode, ?string $source_langcode = NULL): void {
    $items = $layout->getParagraphsReferenceField();
    foreach ($items->referencedEntities() as $delta => $paragraph) {
      if ($paragraph instanceof ParagraphInterface) {
        if (!$is_translating) {
          // Set the langcode if we are not translating.
          $langcode_key = $paragraph->getEntityType()->getKey('langcode');
          if ($paragraph->get($langcode_key)->value != $langcode) {
            // If a translation in the given language already exists,
            // switch to that. If there is none yet, update the language.
            if ($paragraph->hasTranslation($langcode)) {
              $paragraph = $paragraph->getTranslation($langcode);
            }
            else {
              $paragraph->set($langcode_key, $langcode);
            }
          }
        }
        else {
          // Add translation if missing for the target language,
          // if the paragraph is translatable at all.
          if ($paragraph->isTranslatable() && !$paragraph->hasTranslation($langcode)) {
            // Get the selected translation of the paragraph entity.
            $entity_langcode = $paragraph->language()->getId();
            $paragraph_source_langcode = $source_langcode ?? $entity_langcode;
            // Make sure the source language version is used if available.
            // Fetching the translation without this check leads to an
            // exception in the valid case of a paragraph that has no
            // translation in the source language.
            if ($paragraph->hasTranslation($paragraph_source_langcode)) {
              $paragraph = $paragraph->getTranslation($paragraph_source_langcode);
            }
            // The paragraph entity has no content translation source field if
            // no paragraph entity field is translatable, even if the host is.
            if ($paragraph->hasField('content_translation_source')) {
              // Initialize the translation with source language values.
              $paragraph->addTranslation($langcode, $paragraph->toArray());
              $translation = $paragraph->getTranslation($langcode);
              // The content translation source field implies the content
              // translation module, so the manager is expected to be present.
              if ($this->contentTranslationManager) {
                $this->contentTranslationManager->getTranslationMetadata($translation)
                  ->setSource($paragraph->language()->getId());
              }
            }
          }
          // Switch translatable paragraphs to the translation,
          // leave untranslatable paragraphs as they are.
          if ($paragraph->isTranslatable() && $paragraph->hasField('content_translation_source')) {
            $paragraph = $paragraph->getTranslation($langcode);
          }
        }
        // Use setValue() with a structured array including target_id and
        // target_revision_id to prevent onChange('entity') from firing. Direct
        // assignment ($item->entity = $paragraph) triggers __set() which calls
        // onChange('entity'), potentially nulling out target_id and causing
        // existing paragraphs to be treated as new (duplicate UUID INSERT).
        $items[$delta]->setValue([
          'entity' => $paragraph,
          'target_id' => $paragraph->id(),
          'target_revision_id' => $paragraph->getRevisionId(),
        ], FALSE);
      }
    }
    $layout->setParagraphsReferenceField($items);
  }

}
