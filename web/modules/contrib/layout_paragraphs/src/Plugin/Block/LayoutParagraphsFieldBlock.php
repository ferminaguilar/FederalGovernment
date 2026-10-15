<?php

namespace Drupal\layout_paragraphs\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\layout_builder\Plugin\Block\FieldBlock;

/**
 * Field block that renders an empty field with the builder formatter.
 *
 * Layout Builder denies access to a field block whose field is empty, so the
 * Layout Paragraphs Builder formatter never renders its empty state and no
 * first component can be added from the front end.
 *
 * @see layout_paragraphs_block_alter()
 * @see https://www.drupal.org/project/drupal/issues/3067982
 *
 * @phpstan-ignore classExtendsInternalClass.classExtendsInternalClass
 */
class LayoutParagraphsFieldBlock extends FieldBlock {

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    if (($this->getConfiguration()['formatter']['type'] ?? NULL) !== 'layout_paragraphs_builder') {
      return parent::blockAccess($account);
    }

    $entity = $this->getEntity();
    $access = $entity->access('view', $account, TRUE);
    if (!$access->isAllowed()) {
      return $access;
    }
    if (!$entity instanceof FieldableEntityInterface || !$entity->hasField($this->fieldName)) {
      return $access->andIf(AccessResult::forbidden());
    }

    $field = $entity->get($this->fieldName);
    $access = $access->andIf($field->access('view', $account, TRUE));
    if (!$access->isAllowed() || !$field->isEmpty() || $this->entityFieldHasDefaultValue()) {
      return $access;
    }

    // An empty field only renders the builder, so require builder access.
    return $access
      ->andIf($field->access('edit', $account, TRUE))
      ->andIf($entity->access($entity->isNew() ? 'create' : 'update', $account, TRUE));
  }

}
