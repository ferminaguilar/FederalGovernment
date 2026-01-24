<?php

namespace Drupal\ai_provider_azure\Client;

use GuzzleHttp\Client;

/**
 * Lightweight provider client for mimicking OpenAI\Client.
 */
class ChatClient {

  /**
   * The http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * The headers.
   *
   * @var array
   */
  protected array $headers;

  /**
   * The query string.
   *
   * @var array
   */
  protected array $queryString;

  /**
   * The base uri.
   *
   * @var string
   */
  protected string $baseUri;

  /**
   * The message key.
   *
   * @var string
   */
  protected string $messageKey = 'message';

  /**
   * The user role key.
   *
   * @var string
   */
  protected string $userRoleKey = 'role';

  /**
   * The content key.
   *
   * @var string
   */
  protected string $contentKey = 'content';

  /**
   * Create exceptions on none 2xx responses.
   *
   * @var bool
   */
  protected bool $statusExceptions = TRUE;

  /**
   * Create exceptions on connection errors.
   *
   * @var bool
   */
  protected bool $connectionExceptions = TRUE;

  /**
   * Constructor.
   *
   * @param \GuzzleHttp\Client $client
   *   The http client.
   * @param array $headers
   *   The headers to use.
   * @param array $query_string
   *   The query string to use.
   * @param string $base_uri
   *   The base uri to use.
   * @param bool $status_exceptions
   *   Create exceptions on none 2xx responses.
   * @param bool $connection_exceptions
   *   Create exceptions on connection errors.
   */
  public function __construct(Client $client, array $headers = [], array $query_string = [], string $base_uri = '', bool $status_exceptions = TRUE, bool $connection_exceptions = TRUE) {
    $this->client = $client;
    $this->headers = $headers;
    $this->queryString = $query_string;
    $this->baseUri = $base_uri;
    $this->statusExceptions = $status_exceptions;
    $this->connectionExceptions = $connection_exceptions;
  }

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
    $uri = rtrim($this->baseUri, '/') . '/chat/completions';
    $response = NULL;
    try {
      $response = $this->client->post($uri, [
        'headers' => $this->headers,
        'query' => $this->queryString,
        'json' => $parameters,
      ]);
    }
    catch (\Exception $e) {
      if ($this->connectionExceptions) {
        throw new \Exception('Connection error: ' . $e->getMessage());
      }
    }
    if ($this->statusExceptions && $response->getStatusCode() >= 300) {
      throw new \Exception('Status code: ' . $response->getStatusCode());
    }
    $data = json_decode($response->getBody()->getContents(), TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      // If error return pure string.
      return $response->getBody()->getContents();
    }
    // Otherwise we return the data as a client object.
    return new ChatResult($data);
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
