<?php

namespace Drupal\ai_provider_azure\Client;

/**
 * Mock iterator for testing.
 */
class ChatGenerator implements \IteratorAggregate {

  /**
   * The array to iterate over.
   *
   * @var array
   */
  private $array;

  /**
   * Constructor.
   *
   * @param array $array
   *   The array to iterate over.
   */
  public function __construct(array $array) {
    $this->array = $array;
  }

  /**
   * Iterator aggregate.
   *
   * @return \Traversable
   *   The iterator.
   */
  public function getIterator(): \Traversable {
    return new \ArrayIterator($this->array);
  }

}
