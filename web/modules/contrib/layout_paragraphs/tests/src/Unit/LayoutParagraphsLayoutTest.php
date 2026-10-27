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

    $field = $this->createMock(EntityReferenceFieldItemListInterface::class);
    $layout = $this->getMockBuilder(LayoutParagraphsLayout::class)
      ->setConstructorArgs([$field])
      ->onlyMethods(['getEntities'])
      ->getMock();
    $layout->method('getEntities')->willReturn([$paragraph]);

    $this->assertNull($layout->getComponentByUuid(NULL));
    $this->assertNull($layout->getComponentByUuid(''));
    $this->assertNull($layout->getComponentByUuid($unknown_uuid));
    $this->assertSame($paragraph, $layout->getComponentByUuid($existing_uuid)->getEntity());
  }

}
