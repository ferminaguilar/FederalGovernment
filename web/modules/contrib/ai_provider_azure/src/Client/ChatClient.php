<?php

namespace Drupal\ai_provider_azure\Client;

/**
 * Lightweight provider client for mimicking OpenAI\Client.
 */
class ChatClient extends AbstractLightweightClient {

  /**
   * The message key, only needed for streaming.
   *
   * @var string
   */
  protected string $messageKey = 'message';

  /**
   * The user role key, only needed for streaming.
   *
   * @var string
   */
  protected string $userRoleKey = 'role';

  /**
   * The content key, only needed for streaming.
   *
   * @var string
   */
  protected string $contentKey = 'content';

  /**
   * Set the message key, only needed for streaming.
   *
   * @param string $message_key
   *   The message key.
   */
  public function setMessageKey(string $message_key) {
    $this->messageKey = $message_key;
  }

  /**
   * Set the user role key, only needed for streaming.
   *
   * @param string $user_role_key
   *   The user role key.
   */
  public function setUserRoleKey(string $user_role_key) {
    $this->userRoleKey = $user_role_key;
  }

  /**
   * Set the content key, only needed for streaming.
   *
   * @param string $content_key
   *   The content key.
   */
  public function setContentKey(string $content_key) {
    $this->contentKey = $content_key;
  }

  /**
   * Make a normal create request.
   *
   * @param array $parameters
   *   The parameters to use.
   *
   * @return mixed
   *   The response or a string on failure to json_decode.
   */
  public function create(array $parameters) {
    $response = $this->sendRequest($this->requestUri('chat/completions'), ['json' => $parameters]);
    return $this->decodeJsonResponse($response);
  }

  /**
   * Make a mockup stream call.
   *
   * @param array $parameters
   *   The parameters to use.
   *
   * @return mixed
   *   The response or a string on failure to json_decode.
   */
  public function createStreamed(array $parameters) {
    $result = $this->create($parameters);
    if (!($result instanceof ChatResult)) {
      // If failure, return the string.
      return $result;
    }
    $results = [];

    foreach ($result->toArray()['choices'] as $values) {
      $value = $values[$this->messageKey];
      $results[] = new ChatResultStreamed($value[$this->userRoleKey], $value[$this->contentKey]);
    }
    // Return an iterator.
    return new ChatGenerator($results);
  }

}
