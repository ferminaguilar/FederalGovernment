<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_paragraphs\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\SortableTestTrait;

/**
 * LayoutBuilderSortTrait, provides callback for simulated layout change.
 */
trait LayoutParagraphsSortTrait {

  use SortableTestTrait;

  /**
   * {@inheritdoc}
   */
  protected function sortableUpdate($item, $from, $to = NULL) {
    // If container does not change, $from and $to are equal.
    $to = $to ?: $from;

    $script = <<<JS
(function (to) {
  var toElement = document.querySelector(to);
  var layoutParagraphsElement = toElement.closest('[data-lpb-id]');
  Drupal.layoutParagraphsUpdateOrder(layoutParagraphsElement);
})('{$to}')

JS;

    $options = [
      'script' => $script,
      'args'   => [],
    ];

    $this->getSession()->getDriver()->getWebDriverSession()->execute($options);
  }

}
