<?php

namespace Drupal\ai_provider_azure\Client;

use GuzzleHttp\Client;

/**
 * Lightweight provider client for mimicking OpenAI\Client.
 */
class LightweightProviderClient {

  /**
   * The http client.
   */
  protected Client $client;

  /**
   * The uri to the endpoint.
   *
   * @var string
   */
  protected string $baseUri;

  /**
   * The query parameters.
   *
   * @var array
   */
  protected array $queryString = [];

  /**
   * The headers.
   *
   * @var array
   */
  protected array $headers = [];

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
   * Constructs a new LightweightProviderClient object.
   */
  public function __construct() {
    $this->client = new Client();
  }

  /**
   * Sets another client.
   *
   * @param \GuzzleHttp\Client $client
   *   Http client.
   */
  public function withHttpClient(Client $client) {
    $this->client = $client;
  }

  /**
   * Sets the base uri.
   *
   * @param string $uri
   *   The base uri.
   */
  public function setBaseUri(string $uri) {
    $this->baseUri = $uri;
  }

  /**
   * Gets the base uri.
   *
   * @return string
   *   The base uri.
   */
  public function getBaseUri() {
    return $this->baseUri;
  }

  /**
   * With base uri (mimic).
   *
   * @param string $uri
   *   The base uri.
   */
  public function withBaseUri(string $uri) {
    $this->baseUri = $uri;
  }

  /**
   * Sets the query string.
   *
   * @param array $query
   *   The query string.
   */
  public function setQueryString(array $query) {
    $this->queryString = $query;
  }

  /**
   * Gets the query string.
   *
   * @return array
   *   The query string.
   */
  public function getQueryString() {
    return $this->queryString;
  }

  /**
   * Sets the http headers.
   *
   * @param array $headers
   *   The headers.
   */
  public function setHeaders(array $headers) {
    $this->headers = $headers;
  }

  /**
   * Gets the headers.
   *
   * @return array
   *   The headers.
   */
  public function getHeaders() {
    return $this->headers;
  }

  /**
   * Sets one query string (mimic).
   *
   * @param string $key
   *   The key.
   * @param string $value
   *   The value.
   */
  public function withQueryParam(string $key, string $value) {
    $this->queryString[$key] = $value;
  }

  /**
   * Set one header (mimic).
   *
   * @param string $key
   *   The key.
   * @param string $value
   *   The value.
   */
  public function withHttpHeader(string $key, string $value) {
    $this->headers[$key] = $value;
  }

  /**
   * Sets status exceptions.
   *
   * @param bool $statusExceptions
   *   If status exceptions should be thrown.
   */
  public function withStatusExceptions(bool $statusExceptions) {
    $this->statusExceptions = $statusExceptions;
  }

  /**
   * Sets connection exceptions.
   *
   * @param bool $connectionExceptions
   *   If connection exceptions should be thrown.
   */
  public function withConnectionExceptions(bool $connectionExceptions) {
    $this->connectionExceptions = $connectionExceptions;
  }

  /**
   * Mimics make, by returning itself.
   *
   * @return \Drupal\ai_provider_azure\Client\LightweightProviderClient
   *   The client.
   */
  public function make() {
    return $this;
  }

  /**
   * Returns a chat client.
   *
   * @return \Drupal\ai_provider_azure\Client\ChatClient
   *   The chat client.
   */
  public function chat() {
    return new ChatClient($this->client, $this->headers, $this->queryString, $this->baseUri, $this->statusExceptions, $this->connectionExceptions);
  }

  /**
   * Returns a embeddings client.
   *
   * @return \Drupal\ai_provider_azure\Client\EmbeddingsClient
   *   The embeddings client.
   */
  public function embeddings() {
    return new EmbeddingsClient($this->client, $this->headers, $this->queryString, $this->baseUri, $this->statusExceptions, $this->connectionExceptions);
  }

}
