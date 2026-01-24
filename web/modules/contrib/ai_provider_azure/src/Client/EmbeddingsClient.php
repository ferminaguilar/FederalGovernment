<?php

namespace Drupal\ai_provider_azure\Client;

use GuzzleHttp\Client;

/**
 * Lightweight provider client for mimicking OpenAI\Client.
 */
class EmbeddingsClient {

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
   * The input.
   *
   * @var string
   */
  protected string $input;

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
   * Set the input.
   *
   * @param string $input
   *   The message key.
   */
  public function setInput(string $input) {
    $this->input = $input;
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
    $uri = rtrim($this->baseUri, '/') . '/embeddings';
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
    return new EmbeddingsResult($data);
  }

}
