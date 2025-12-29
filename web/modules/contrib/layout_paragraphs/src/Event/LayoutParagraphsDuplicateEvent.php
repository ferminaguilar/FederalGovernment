<?php

namespace Drupal\layout_paragraphs\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\layout_paragraphs\LayoutParagraphsComponent;

/**
 * Class definition for Layout Paragraphs Duplicate event.
 *
 * Developers can subscribe to this event to modify the duplicated component,
 * cancel the duplication operation, or perform additional actions after
 * a component has been duplicated but before it's saved to the layout.
 */
class LayoutParagraphsDuplicateEvent extends Event {

  const EVENT_NAME = 'layout_paragraphs_duplicate';

  /**
   * The layout object.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsLayout
   */
  protected $layout;

  /**
   * The UUID of the source component being duplicated.
   *
   * @var string
   */
  protected $sourceUuid;

  /**
   * The duplicated component.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsComponent
   */
  protected $duplicateComponent;

  /**
   * Whether the duplication operation should be prevented.
   *
   * @var bool
   */
  protected $preventDuplication = FALSE;

  /**
   * The message to display to the user when duplication is prevented.
   *
   * @var string
   */
  protected $message = 'This operation is not permitted.';

  /**
   * Class constructor.
   *
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout
   *   The layout object.
   * @param string $source_uuid
   *   The UUID of the source component being duplicated.
   * @param \Drupal\layout_paragraphs\LayoutParagraphsComponent $duplicate_component
   *   The duplicated component.
   */
  public function __construct(LayoutParagraphsLayout $layout, string $source_uuid, LayoutParagraphsComponent $duplicate_component) {
    $this->layout = $layout;
    $this->sourceUuid = $source_uuid;
    $this->duplicateComponent = $duplicate_component;
  }

  /**
   * Gets the layout object.
   *
   * @return \Drupal\layout_paragraphs\LayoutParagraphsLayout
   *   The layout object.
   */
  public function getLayout() {
    return $this->layout;
  }

  /**
   * Gets the source UUID.
   *
   * @return string
   *   The UUID of the source component being duplicated.
   */
  public function getSourceUuid() {
    return $this->sourceUuid;
  }

  /**
   * Gets the duplicated component.
   *
   * @return \Drupal\layout_paragraphs\LayoutParagraphsComponent
   *   The duplicated component.
   */
  public function getDuplicateComponent() {
    return $this->duplicateComponent;
  }

  /**
   * Prevents the duplication operation.
   *
   * @param string $message
   *   Optional message to display to the user.
   */
  public function preventDuplication(?string $message = NULL) {
    $this->preventDuplication = TRUE;
    if ($message !== NULL) {
      $this->message = $message;
    }
  }

  /**
   * Checks whether duplication should be prevented.
   *
   * @return bool
   *   TRUE if duplication should be prevented, FALSE otherwise.
   */
  public function isDuplicationPrevented() {
    return $this->preventDuplication;
  }

  /**
   * Gets the message to display to the user when duplication is prevented.
   *
   * @return string
   *   The message.
   */
  public function getMessage() {
    return $this->message;
  }

  /**
   * Sets the message to display to the user when duplication is prevented.
   *
   * @param string $message
   *   The message to set.
   */
  public function setMessage(string $message) {
    $this->message = $message;
  }

}
