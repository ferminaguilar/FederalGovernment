<?php

namespace Drupal\ai_provider_azure\Client;

/**
 * The chat result object.
 */
class ChatResult {

  /**
   * The actual response object.
   *
   * @var mixed
   *   The object.
   */
  protected $responseObject;

  /**
   * The constructor.
   *
   * @param mixed $responseObject
   *   The response object.
   */
  public function __construct($responseObject) {
    $this->responseObject = $responseObject;
  }

  /**
   * Magic method to get the key from the response object.
   *
   * @param string $name
   *   The key.
   *
   * @return mixed
   *   The value.
   */
  public function __get($name) {
    // Fail gracefully.
    if (!isset($this->responseObject->{$name})) {
      return NULL;
    }
    return $this->responseObject->{$name};
  }

  /**
   * Get as an array.
   *
   * @return array
   *   The array.
   */
  public function toArray(): array {
    return (array) $this->responseObject;
  }

}
