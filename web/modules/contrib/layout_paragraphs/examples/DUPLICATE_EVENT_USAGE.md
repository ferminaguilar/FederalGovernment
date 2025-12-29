# Layout Paragraphs Duplicate Event

## Overview

The `LayoutParagraphsDuplicateEvent` is dispatched after a layout paragraphs component has been duplicated but before it's saved to the layout, allowing other modules to:

1. **Prevent duplication** of certain component types
2. **Modify the duplicated component** before it's saved
3. **Perform additional validation** or business logic
4. **Log or audit** duplication operations

## Event Details

- **Event Name**: `layout_paragraphs_duplicate`
- **Event Class**: `\Drupal\layout_paragraphs\Event\LayoutParagraphsDuplicateEvent`
- **Dispatch Location**: `\Drupal\layout_paragraphs\Controller\DuplicateController::duplicate()`

## Available Methods

### LayoutParagraphsDuplicateEvent Methods

- `getLayout()` - Returns the `LayoutParagraphsLayout` object
- `getSourceUuid()` - Returns the UUID of the component being duplicated
- `getDuplicateComponent()` - Returns the duplicated `LayoutParagraphsComponent` object
- `preventDuplication($message = NULL)` - Prevents the duplication operation with optional custom message
- `isDuplicationPrevented()` - Checks if duplication has been prevented
- `getMessage()` - Gets the message to display when duplication is prevented
- `setMessage($message)` - Sets a custom message to display when duplication is prevented

## Creating an Event Subscriber

### 1. Create the Event Subscriber Class

```php
<?php

namespace Drupal\your_module\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\layout_paragraphs\Event\LayoutParagraphsDuplicateEvent;

class YourDuplicateSubscriber implements EventSubscriberInterface {

  protected $messenger;

  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  public static function getSubscribedEvents(): array {
    return [
      LayoutParagraphsDuplicateEvent::EVENT_NAME => 'onComponentDuplicate',
    ];
  }

  public function onComponentDuplicate(LayoutParagraphsDuplicateEvent $event) {
    $layout = $event->getLayout();
    $source_uuid = $event->getSourceUuid();
    $duplicate_component = $event->getDuplicateComponent();
    $source_component = $layout->getComponentByUuid($source_uuid);

    if ($source_component) {
      $source_paragraph = $source_component->getEntity();
      $duplicate_paragraph = $duplicate_component->getEntity();

      // Example: Prevent duplication of certain types
      if ($source_paragraph->bundle() === 'restricted_type') {
        $event->preventDuplication('This component type cannot be duplicated due to security restrictions.');
        return;
      }

      // Example: Modify the duplicated component
      if ($duplicate_paragraph->hasField('field_title')) {
        $original_title = $duplicate_paragraph->get('field_title')->value;
        $duplicate_paragraph->set('field_title', $original_title . ' (Copy)');
      }
    }
  }
}
```

### 2. Register the Service

Add to your module's `services.yml` file:

```yaml
services:
  your_module.duplicate_subscriber:
    class: Drupal\your_module\EventSubscriber\YourDuplicateSubscriber
    arguments:
      - '@messenger'
    tags:
      - { name: 'event_subscriber' }
```

## Common Use Cases

### Preventing Duplication Based on Component Type

```php
public function onComponentDuplicate(LayoutParagraphsDuplicateEvent $event) {
  $source_component = $event->getLayout()->getComponentByUuid($event->getSourceUuid());

  if ($source_component && $source_component->getEntity()->bundle() === 'hero_banner') {
    $event->preventDuplication('Hero banners are unique and cannot be duplicated. Please create a new hero banner instead.');
  }
}
```

### Limiting Number of Components

```php
public function onComponentDuplicate(LayoutParagraphsDuplicateEvent $event) {
  $layout = $event->getLayout();
  $components = $layout->getComponents();

  if (count($components) >= 10) {
    $event->preventDuplication('This layout has reached the maximum of 10 components. Please remove a component before adding another.');
  }
}
```

### Custom Message Based on User Permissions

```php
public function onComponentDuplicate(LayoutParagraphsDuplicateEvent $event) {
  $current_user = \Drupal::currentUser();

  if (!$current_user->hasPermission('duplicate layout components')) {
    $event->preventDuplication('You do not have permission to duplicate components. Please contact an administrator to request access.');
  }
}
```

### Modifying the Duplicated Component

```php
public function onComponentDuplicate(LayoutParagraphsDuplicateEvent $event) {
  $duplicate_component = $event->getDuplicateComponent();
  $duplicate_paragraph = $duplicate_component->getEntity();

  // Add "(Copy)" to the title
  if ($duplicate_paragraph->hasField('field_title')) {
    $original_title = $duplicate_paragraph->get('field_title')->value;
    $duplicate_paragraph->set('field_title', $original_title . ' (Copy)');
  }

  // Clear certain fields in the duplicate
  if ($duplicate_paragraph->hasField('field_date_published')) {
    $duplicate_paragraph->set('field_date_published', NULL);
  }
}
```

### Limiting Number of Components

```php
public function onComponentDuplicate(LayoutParagraphsDuplicateEvent $event) {
  $layout = $event->getLayout();
  $components = $layout->getComponents();

  if (count($components) >= 10) {
    $event->preventDuplication();
    $this->messenger->addWarning('Maximum of 10 components allowed.');
  }
}
```

### Audit Logging

```php
public function onComponentDuplicate(LayoutParagraphsDuplicateEvent $event) {
  $source_component = $event->getLayout()->getComponentByUuid($event->getSourceUuid());
  $duplicate_component = $event->getDuplicateComponent();

  if ($source_component) {
    \Drupal::logger('my_module')->info('Component @type duplicated from @source_uuid to @duplicate_uuid by user @user', [
      '@type' => $source_component->getEntity()->bundle(),
      '@source_uuid' => $event->getSourceUuid(),
      '@duplicate_uuid' => $duplicate_component->getEntity()->uuid(),
      '@user' => \Drupal::currentUser()->getAccountName(),
    ]);
  }
}
```

### Role-Based Restrictions

```php
public function onComponentDuplicate(LayoutParagraphsDuplicateEvent $event) {
  $current_user = \Drupal::currentUser();

  if (!$current_user->hasPermission('duplicate layout components')) {
    $event->preventDuplication('You do not have permission to duplicate components. Please contact an administrator.');
  }
}
```

### Using setMessage() Method

```php
public function onComponentDuplicate(LayoutParagraphsDuplicateEvent $event) {
  $source_component = $event->getLayout()->getComponentByUuid($event->getSourceUuid());

  if ($source_component) {
    $bundle = $source_component->getEntity()->bundle();

    // Set a dynamic message based on component type
    if ($bundle === 'gallery') {
      $event->setMessage('Gallery components contain sensitive media and cannot be duplicated for security reasons.');
      $event->preventDuplication();
    }
    elseif ($bundle === 'form') {
      $event->setMessage('Form components have unique configurations and should not be duplicated. Please create a new form instead.');
      $event->preventDuplication();
    }
  }
}
```

## Best Practices

1. **Always check** if the component exists before accessing its properties
2. **Provide clear, user-friendly messages** when preventing duplication - avoid technical jargon
3. **Use appropriate log levels** when logging duplication events
4. **Keep event subscribers lightweight** to avoid performance issues
5. **Test thoroughly** as preventing duplication affects the user experience
6. **Be specific in prevention messages** - explain why duplication was prevented and what the user can do instead

## Dialog Display

When duplication is prevented, the message is automatically displayed to the user in a modal dialog with an "OK" button. The dialog uses the same styling as other Layout Paragraphs dialogs for consistency.

The default message is "This operation is not permitted." but should be customized to provide specific, actionable feedback to users.
