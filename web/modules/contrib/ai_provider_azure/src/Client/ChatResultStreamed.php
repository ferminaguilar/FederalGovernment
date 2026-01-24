<?php

namespace Drupal\ai_provider_azure\Client;

/**
 * The chat result streamed object.
 */
class ChatResultStreamed {

  /**
   * The role of the chunk.
   *
   * @var string
   *   The role.
   */
  protected $role;

  /**
   * The content of the chunk.
   *
   * @var string
   *   The content.
   */
  protected $content;

  /**
   * The constructor.
   *
   * @param string $role
   *   The role.
   * @param string $content
   *   The content.
   */
  public function __construct(string $role, string $content) {
    $this->role = $role;
    $this->content = $content;
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
    // No metadata for now.
    if ($name == 'meta') {
      return NULL;
    }

    // Mimic choices.
    if ($name == 'choices') {
      return [
        (object) [
          'delta' => (object) [
            'role' => $this->role,
            'content' => $this->content,
          ],
        ],
      ];
    }
  }

  /**
   * Get as an array.
   *
   * @return array
   *   The array.
   */
  public function toArray(): array {
    // @phpstan-ignore-next-line
    return (array) $this->responseObject;
  }

}
