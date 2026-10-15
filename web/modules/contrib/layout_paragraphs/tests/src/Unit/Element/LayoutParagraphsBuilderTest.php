<?php

namespace Drupal\Tests\layout_paragraphs\Unit\Element;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Layout\LayoutDefault;
use Drupal\Core\Layout\LayoutInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Plugin\PreviewAwarePluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\layout_paragraphs\Element\LayoutParagraphsBuilder;
use Drupal\layout_paragraphs\LayoutParagraphsComponent;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository;
use Drupal\layout_paragraphs\LayoutParagraphsSection;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsConversionManager;
use Drupal\Tests\UnitTestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests preview mode injection for layout plugins in the builder.
 *
 * @group layout_paragraphs
 * @coversDefaultClass \Drupal\layout_paragraphs\Element\LayoutParagraphsBuilder
 */
class LayoutParagraphsBuilderTest extends UnitTestCase {

  /**
   * Tests preview mode is set on preview-aware layouts.
   *
   * @covers ::preRender
   */
  public function testPreviewModeIsSetForPreviewAwareLayout() {
    $layout_plugin = $this->createMock(LayoutDefault::class);
    $this->assertInstanceOf(PreviewAwarePluginInterface::class, $layout_plugin);
    $layout_plugin->expects($this->once())
      ->method('setInPreview')
      ->with(TRUE);
    $layout_plugin->expects($this->once())
      ->method('build')
      ->with(['content' => []])
      ->willReturn(['content' => []]);

    $builder = $this->createBuilder($layout_plugin);
    $result = $builder->preRender($this->createElement());

    $this->assertSame(
      [
        'build' => [
          'regions' => [
            'content' => [],
            '#weight' => 1000,
          ],
        ],
      ],
      $result
    );
  }

  /**
   * Tests regular layouts do not receive preview mode injection.
   *
   * @covers ::preRender
   */
  public function testPreviewModeIsNotSetForRegularLayout() {
    $layout_plugin = $this->createMock(LayoutInterface::class);
    $this->assertNotInstanceOf(PreviewAwarePluginInterface::class, $layout_plugin);
    $layout_plugin->expects($this->once())
      ->method('build')
      ->with(['content' => []])
      ->willReturn(['content' => []]);

    $builder = $this->createBuilder($layout_plugin);
    $result = $builder->preRender($this->createElement());

    $this->assertSame(
      [
        'build' => [
          'regions' => [
            'content' => [],
            '#weight' => 1000,
          ],
        ],
      ],
      $result
    );
  }

  /**
   * Creates a builder with its plugin and component construction stubbed.
   */
  private function createBuilder(LayoutInterface $layout_plugin): LayoutParagraphsBuilder {
    $paragraph = $this->createMock(ParagraphInterface::class);
    $paragraph->method('uuid')->willReturn('section-uuid');

    $component = $this->createMock(LayoutParagraphsComponent::class);
    $component->method('getEntity')->willReturn($paragraph);
    $component->method('isLayout')->willReturn(TRUE);

    $section = $this->createMock(LayoutParagraphsSection::class);
    $section->method('getComponentsForRegion')->with('content')->willReturn([]);
    $section->method('getLayoutId')->willReturn('test_layout');
    $section->method('getLayoutConfiguration')->willReturn([]);

    $layout = $this->createMock(LayoutParagraphsLayout::class);
    $layout->method('getComponents')->willReturn([$component]);
    $layout->method('getSetting')
      ->willReturnCallback(static function ($name, $default = NULL) {
        return $name === 'preview_view_mode' ? 'default' : $default;
      });
    $layout->method('getLayoutSection')->with($paragraph)->willReturn($section);

    $tempstore = $this->createMock(LayoutParagraphsLayoutTempstoreRepository::class);
    $tempstore->method('get')->willReturn($layout);

    $layout_plugin_manager = $this->createMock(LayoutPluginManagerInterface::class);
    $layout_plugin_manager->expects($this->once())
      ->method('createInstance')
      ->with('test_layout', ['layout_paragraphs_section' => $section])
      ->willReturn($layout_plugin);

    $builder = $this->getMockBuilder(LayoutParagraphsBuilder::class)
      ->setConstructorArgs([
        [],
        'layout_paragraphs_builder',
        [],
        $tempstore,
        $this->createMock(EntityTypeManagerInterface::class),
        $layout_plugin_manager,
        $this->createMock(RendererInterface::class),
        $this->createMock(EntityTypeBundleInfoInterface::class),
        $this->createMock(ParagraphsConversionManager::class),
        $this->createMock(EventDispatcherInterface::class),
        $this->createMock(ConfigFactoryInterface::class),
      ])
      ->onlyMethods(['buildComponent'])
      ->getMock();
    $builder->method('buildComponent')
      ->willReturn(['regions' => ['content' => []]]);

    return $builder;
  }

  /**
   * Creates the render element for a single layout component.
   */
  private function createElement(): array {
    return [
      '#layout_paragraphs_layout' => $this->createMock(LayoutParagraphsLayout::class),
      '#uuid' => 'section-uuid',
      '#is_translating' => FALSE,
    ];
  }

}
