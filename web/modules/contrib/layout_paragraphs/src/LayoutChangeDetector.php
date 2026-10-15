<?php

declare(strict_types=1);

namespace Drupal\layout_paragraphs;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\entity_reference_revisions\EntityNeedsSaveInterface;

/**
 * Detects whether a layout in the tempstore differs from the stored one.
 */
class LayoutChangeDetector {

  /**
   * Constructs a LayoutChangeDetector.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns whether the layout differs from the stored host entity.
   *
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout
   *   The layout to inspect.
   *
   * @return bool
   *   TRUE if saving the layout would change something.
   */
  public function hasChanges(LayoutParagraphsLayout $layout): bool {
    $ids = [];
    foreach ($layout->getEntities() as $paragraph) {
      if ($paragraph->isNew()) {
        return TRUE;
      }
      if ($paragraph instanceof EntityNeedsSaveInterface && $paragraph->needsSave()) {
        return TRUE;
      }
      $ids[] = (string) $paragraph->id();
    }

    $stored = $this->loadStoredHost($layout);
    if (!$stored) {
      return TRUE;
    }

    $stored_ids = array_map('strval', array_column($stored->get($layout->getFieldName())->getValue(), 'target_id'));

    return $stored_ids !== $ids;
  }

  /**
   * Loads the stored host entity in the language the layout was prepared for.
   *
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout
   *   The layout whose host entity to load.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The stored host entity, or NULL if it no longer exists.
   */
  public function loadStoredHost(LayoutParagraphsLayout $layout): ?ContentEntityInterface {
    $host = $layout->getEntity();
    $storage = $this->entityTypeManager->getStorage($host->getEntityTypeId());
    $stored = $host instanceof RevisionableInterface && $storage instanceof RevisionableStorageInterface
      ? $storage->loadRevisionUnchanged($host->getLoadedRevisionId())
      : $storage->loadUnchanged($host->id());
    if (!$stored instanceof ContentEntityInterface) {
      return NULL;
    }

    $langcode = $layout->getThirdPartySetting('layout_paragraphs', 'langcode') ?? $host->language()->getId();
    return $stored instanceof TranslatableInterface && $stored->hasTranslation($langcode)
      ? $stored->getTranslation($langcode)
      : $stored;
  }

}
