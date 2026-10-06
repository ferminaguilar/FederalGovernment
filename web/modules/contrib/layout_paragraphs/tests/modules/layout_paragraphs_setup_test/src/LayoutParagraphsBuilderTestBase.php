<?php

declare(strict_types=1);

namespace Drupal\layout_paragraphs_setup_test;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;

/**
 * New base class for Layout Paragraphs Builder tests using setup test module.
 *
 * This class provides the same functionality as the original BuilderTestBase
 * but uses the layout_paragraphs_setup_test module for configuration setup.
 *
 * @group layout_paragraphs
 */
abstract class LayoutParagraphsBuilderTestBase extends WebDriverTestBase {

  use ParagraphsTestBaseTrait;
  use LayoutParagraphsTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_paragraphs_setup_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->layoutParagraphsSetUp();
  }

  /**
   * {@inheritdoc}
   *
   * Added method with fixed return comment for IDE type hinting.
   *
   * @return \Drupal\FunctionalJavascriptTests\JSWebAssert
   *   A new JS web assert object.
   */
  public function assertSession($name = '') {
    $js_web_assert = parent::assertSession($name);
    return $js_web_assert;
  }

}
