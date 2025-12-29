<?php

namespace Drupal\your_module\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\layout_paragraphs\Event\LayoutParagraphsDuplicateEvent;

/**
 * Example event subscriber for Layout Paragraphs duplicate events.
 *
 * This is an example of how other modules can subscribe to the
 * LayoutParagraphsDuplicateEvent to modify the duplication behavior.
 */
class LayoutParagraphsDuplicateExampleSubscriber implements EventSubscriberInterface {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      LayoutParagraphsDuplicateEvent::EVENT_NAME => 'onComponentDuplicate',
    ];
  }

  /**
   * Responds to component duplication events.
   *
   * @param \Drupal\layout_paragraphs\Event\LayoutParagraphsDuplicateEvent $event
   *   The duplicate event.
   */
  public function onComponentDuplicate(LayoutParagraphsDuplicateEvent $event) {
    $layout = $event->getLayout();
    $source_uuid = $event->getSourceUuid();
    $duplicate_component = $event->getDuplicateComponent();

    // Example: Get the source component.
    $source_component = $layout->getComponentByUuid($source_uuid);

    if ($source_component) {
      $source_paragraph = $source_component->getEntity();
      $duplicate_paragraph = $duplicate_component->getEntity();

      // Example 1: Prevent duplication of certain paragraph types.
      if ($source_paragraph->bundle() === 'restricted_paragraph_type') {
        $event->preventDuplication('This component type cannot be duplicated due to content policy restrictions.');
        return;
      }

      // Example 2: Limit the number of duplicates.
      $existing_components = $layout->getComponents();
      $same_type_count = 0;
      foreach ($existing_components as $component) {
        if ($component->getEntity()->bundle() === $source_paragraph->bundle()) {
          $same_type_count++;
        }
      }

      if ($same_type_count >= 3) {
        $event->preventDuplication('Maximum of 3 components of this type allowed. Please remove an existing component before duplicating.');
        return;
      }

      // Example 3: Modify the duplicated component before it's saved.
      if ($duplicate_paragraph->hasField('field_title')) {
        $original_title = $duplicate_paragraph->get('field_title')->value;
        $duplicate_paragraph->set('field_title', $original_title . ' (Copy)');
      }
    }
  }

}
