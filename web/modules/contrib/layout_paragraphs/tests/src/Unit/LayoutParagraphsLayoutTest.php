<?php

namespace Drupal\Tests\layout_paragraphs\Unit;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests layout component lookups.
 *
 * @group layout_paragraphs
 * @coversDefaultClass \Drupal\layout_paragraphs\LayoutParagraphsLayout
 */
class LayoutParagraphsLayoutTest extends UnitTestCase {

  /**
   * Tests optional, missing, and existing component UUIDs.
   *
   * @covers ::getComponentByUuid
   */
  public function testGetComponentByUuid() {
    $existing_uuid = '5e42c730-8415-40c0-b7ec-355d5469d79c';
    $unknown_uuid = '1af5104f-9874-4362-a0d2-ce95b923c164';
    $paragraph = $this->createMock(ParagraphInterface::class);
    $paragraph->method('uuid')->willReturn($existing_uuid);

    $items = new \ArrayObject([(object) ['entity' => $paragraph]]);
    $field = $this->createField($items);
    $layout = new LayoutParagraphsLayout($field);

    $this->assertNull($layout->getComponentByUuid(NULL));
    $this->assertNull($layout->getComponentByUuid(''));
    $this->assertNull($layout->getComponentByUuid($unknown_uuid));
    $this->assertSame($paragraph, $layout->getComponentByUuid($existing_uuid)->getEntity());
  }

  /**
   * Tests cache hits and recovery after external field mutations.
   *
   * @covers ::getComponentByUuid
   * @covers ::__sleep
   */
  public function testLookupIndex() {
    $first = $this->createMock(ParagraphInterface::class);
    $first->method('uuid')->willReturn('first');
    $second = $this->createMock(ParagraphInterface::class);
    $second->method('uuid')->willReturn('second');
    $replacement = $this->createMock(ParagraphInterface::class);
    $replacement->method('uuid')->willReturn('second');
    $items = new \ArrayObject([
      (object) ['entity' => NULL],
      (object) ['entity' => $first],
      (object) ['entity' => $second],
    ]);
    $field = $this->createField($items);
    // Only the initial lookup and the stale lookups should scan the field.
    $field->expects($this->exactly(4))->method('getIterator')
      ->willReturnCallback(fn() => $items->getIterator());
    $layout = new LayoutParagraphsLayout($field);
    $this->assertSame($first, $layout->getComponentByUuid('first')->getEntity());
    $this->assertSame($second, $layout->getComponentByUuid('second')->getEntity());
    $this->assertNotContains('uuidIndex', $layout->__sleep());
    $this->assertSame($second, $layout->getComponentByUuid('second')->getEntity());

    // Reordering invalidates cached deltas.
    $items->exchangeArray([(object) ['entity' => $second], (object) ['entity' => $first]]);
    $this->assertSame($second, $layout->getComponentByUuid('second')->getEntity());
    // Replacement at a valid delta must return the current entity.
    $items[0] = (object) ['entity' => $replacement];
    $this->assertSame($replacement, $layout->getComponentByUuid('second')->getEntity());
    unset($items[0]);
    $this->assertNull($layout->getComponentByUuid('second'));
    $items[] = (object) ['entity' => $second];
    $this->assertSame($second, $layout->getComponentByUuid('second')->getEntity());
  }

  /**
   * Tests duplicate UUIDs and replacement of the reference field.
   *
   * @covers ::getComponentByUuid
   */
  public function testDuplicateUuidsAndFieldReplacement() {
    $first = $this->createMock(ParagraphInterface::class);
    $first->method('uuid')->willReturn('duplicate');
    $second = $this->createMock(ParagraphInterface::class);
    $second->method('uuid')->willReturn('duplicate');
    $items = new \ArrayObject([
      (object) ['entity' => $first],
      (object) ['entity' => $second],
    ]);
    $layout = new LayoutParagraphsLayout($this->createField($items));
    $this->assertSame($first, $layout->getComponentByUuid('duplicate')->getEntity());
    // The reference field is exposed by reference, so it can change externally.
    $field = &$layout->getParagraphsReferenceField();
    $field = $this->createField(new \ArrayObject([(object) ['entity' => $second]]));
    $this->assertSame($second, $layout->getComponentByUuid('duplicate')->getEntity());
  }

  /**
   * Tests nested scope lifetime and transient serialization properties.
   *
   * @covers ::beginReadScope
   * @covers ::endReadScope
   * @covers ::getEntities
   * @covers ::getComponents
   * @covers ::__sleep
   */
  public function testReadScopeLifetime() {
    $paragraph = $this->createMock(ParagraphInterface::class);
    $items = new \ArrayObject([(object) ['entity' => $paragraph]]);
    $field = $this->createField($items);
    $field->expects($this->exactly(3))->method('getIterator')
      ->willReturnCallback(fn() => $items->getIterator());
    $layout = new LayoutParagraphsLayout($field);
    $layout->beginReadScope();
    $components = $layout->getComponents();
    $layout->beginReadScope();
    $this->assertSame($components, $layout->getComponents());
    $this->assertSame([$paragraph], $layout->getEntities());
    $layout->endReadScope();
    $this->assertSame($components, $layout->getComponents());
    foreach (['uuidIndex', 'readCache', 'readScopeDepth'] as $property) {
      $this->assertNotContains($property, $layout->__sleep());
    }
    $this->assertSame($components, $layout->getComponents());
    $layout->endReadScope();
    $this->assertNotSame($components, $layout->getComponents());
    $layout->beginReadScope();
    $this->assertNotSame($components, $layout->getComponents());
    $layout->endReadScope();
  }

  /**
   * Tests that deletion clears lists populated during a recursive operation.
   *
   * @covers ::deleteComponent
   * @covers ::getRootComponents
   */
  public function testReadScopeDeletion() {
    $paragraph = $this->createMock(ParagraphInterface::class);
    $paragraph->method('uuid')->willReturn('component');
    $paragraph->method('getAllBehaviorSettings')->willReturn([
      'layout_paragraphs' => ['layout' => 'test_layout'],
    ]);
    $items = new \ArrayObject([(object) ['entity' => $paragraph]]);
    $field = $this->createField($items);
    $field->method('offsetUnset')->willReturnCallback(function ($delta) use ($items) {
      unset($items[$delta]);
    });
    $layout = new LayoutParagraphsLayout($field);
    $layout->beginReadScope();
    $this->assertCount(1, $layout->getRootComponents());
    $layout->deleteComponent('component', TRUE);
    $this->assertSame([], $layout->getComponents());
    $this->assertSame([], $layout->getRootComponents());
    $layout->endReadScope();
  }

  /**
   * Tests grouped children preserve field order and rebuild between scopes.
   *
   * @covers ::getLayoutSection
   * @covers ::getRootComponents
   */
  public function testReadScopeSections() {
    $section = $this->createMock(ParagraphInterface::class);
    $section->method('uuid')->willReturn('section');
    $section->method('getAllBehaviorSettings')->willReturn([
      'layout_paragraphs' => ['layout' => 'test_layout'],
    ]);
    $child = $this->createMock(ParagraphInterface::class);
    $child->method('getAllBehaviorSettings')->willReturn([
      'layout_paragraphs' => ['parent_uuid' => 'section', 'region' => 'content'],
    ]);
    $items = new \ArrayObject([(object) ['entity' => $section], (object) ['entity' => $child]]);
    $layout = new LayoutParagraphsLayout($this->createField($items));
    $layout->beginReadScope();
    $components = $layout->getComponents();
    $this->assertSame([1 => $components[1]], $layout->getLayoutSection($section)->getComponents());
    $this->assertSame([0 => $components[0]], $layout->getRootComponents());
    $this->assertSame([1 => $components[1]], $layout->getLayoutSection($section)->getComponents());
    $layout->endReadScope();
    $items->exchangeArray([(object) ['entity' => $section]]);
    $layout->beginReadScope();
    $this->assertSame([], $layout->getLayoutSection($section)->getComponents());
    $layout->endReadScope();
  }

  /**
   * Creates a field backed by mutable items.
   */
  protected function createField(\ArrayObject $items): EntityReferenceFieldItemListInterface {
    $field = $this->createMockForIntersectionOfInterfaces([
      EntityReferenceFieldItemListInterface::class,
      \IteratorAggregate::class,
    ]);
    $field->method('getIterator')->willReturnCallback(fn() => $items->getIterator());
    $field->method('offsetExists')->willReturnCallback(fn($delta) => isset($items[$delta]));
    $field->method('offsetGet')->willReturnCallback(fn($delta) => $items[$delta]);
    return $field;
  }

}
