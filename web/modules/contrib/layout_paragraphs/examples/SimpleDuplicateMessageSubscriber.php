<?php

namespace Drupal\your_module\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\layout_paragraphs\Event\LayoutParagraphsDuplicateEvent;

/**
 * Simple example showing custom messages for duplicate prevention.
 */
class SimpleDuplicateMessageSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      LayoutParagraphsDuplicateEvent::EVENT_NAME => 'onComponentDuplicate',
    ];
  }

  /**
   * Prevents duplication with custom messages.
   *
   * @param \Drupal\layout_paragraphs\Event\LayoutParagraphsDuplicateEvent $event
   *   The duplicate event.
   */
  public function onComponentDuplicate(LayoutParagraphsDuplicateEvent $event) {
    $source_component = $event->getLayout()->getComponentByUuid($event->getSourceUuid());

    if ($source_component) {
      $bundle = $source_component->getEntity()->bundle();

      // Example: Different messages for different component types.
      switch ($bundle) {
        case 'hero_banner':
          $event->preventDuplication('Hero banners should be unique on each page. Please create a new hero banner or modify the existing one.');
          break;

        case 'contact_form':
          $event->preventDuplication('Contact forms have unique configurations and cannot be duplicated. Please add a new contact form component instead.');
          break;

        case 'testimonial':
          // Allow duplication but modify the content.
          $duplicate_paragraph = $event->getDuplicateComponent()->getEntity();
          if ($duplicate_paragraph->hasField('field_title')) {
            $original_title = $duplicate_paragraph->get('field_title')->value;
            $duplicate_paragraph->set('field_title', $original_title . ' (Copy)');
          }
          break;
      }
    }
  }

}
