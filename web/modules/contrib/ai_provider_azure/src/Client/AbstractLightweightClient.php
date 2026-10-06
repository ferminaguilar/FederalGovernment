<?php

namespace Drupal\ai_provider_azure\Client;

use Drupal\ai\Exception\AiBadRequestException;
use Drupal\ai\Exception\AiResponseErrorException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * Shared boilerplate for lightweight clients mimicking OpenAI\Client.
 *
 * Handles the request plumbing (headers, query string, exception toggles,
 * literal-vs-tail-stripped URL building, connection/status error handling)
 * that is identical across every resource client; subclasses only
 * implement their own resource-specific request/response shape.
 */
abstract class AbstractLightweightClient {

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
   * Whether the endpoint is used exactly as configured.
   *
   * When TRUE, requestUri() ignores any given tail and returns the base
   * uri verbatim, because the endpoint did not match the shape that
   * tail-stripping-and-re-adding assumes (see
   * AzureProvider::createVariablesFromEndpoint()).
   *
   * @var bool
   */
  protected bool $literal = FALSE;

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
   * @param bool $literal
   *   TRUE to always request the base uri verbatim, ignoring any tail
   *   passed to requestUri().
   */
  public function __construct(Client $client, array $headers = [], array $query_string = [], string $base_uri = '', bool $status_exceptions = TRUE, bool $connection_exceptions = TRUE, bool $literal = FALSE) {
    $this->client = $client;
    $this->headers = $headers;
    $this->queryString = $query_string;
    $this->baseUri = $base_uri;
    $this->statusExceptions = $status_exceptions;
    $this->connectionExceptions = $connection_exceptions;
    $this->literal = $literal;
  }

  /**
   * Builds the request URL for a resource, honoring literal mode.
   *
   * @param string $tail
   *   The REST path tail to append (e.g. 'embeddings'), ignored when
   *   literal mode is active.
   *
   * @return string
   *   The full request URL.
   */
  protected function requestUri(string $tail = ''): string {
    if ($this->literal || $tail === '') {
      return $this->baseUri;
    }
    return rtrim($this->baseUri, '/') . '/' . $tail;
  }

  /**
   * Sends a POST request and returns the raw response.
   *
   * @param string $uri
   *   The request URL.
   * @param array $options
   *   Guzzle request options (e.g. 'json', 'multipart' or 'stream'),
   *   merged with the configured headers and query string.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response.
   *
   * @throws \Exception
   *   On a connection error (if connection exceptions are enabled) or a
   *   non-2xx status code (if status exceptions are enabled).
   */
  protected function sendRequest(string $uri, array $options): ResponseInterface {
    $response = NULL;
    try {
      $response = $this->client->post($uri, $options + [
        'headers' => $this->headers,
        'query' => $this->queryString,
      ]);
    }
    catch (\Exception $e) {
      if ($e instanceof RequestException && $e->hasResponse()) {
        // The API answered with an error status, so this is not a connection
        // problem. Carry on with the response to report the API message.
        $response = $e->getResponse();
      }
      elseif ($this->connectionExceptions) {
        throw new \Exception('Connection error: ' . $e->getMessage(), 0, $e);
      }
    }
    if ($response === NULL) {
      throw new AiResponseErrorException('No response was received from the API.');
    }
    if ($this->statusExceptions && $response->getStatusCode() >= 300) {
      $status = $response->getStatusCode();
      $message = $this->extractErrorMessage((string) $response->getBody(), $status);
      // A rejected request is the caller's to fix, so give it a typed error.
      if (in_array($status, [400, 422], TRUE)) {
        throw new AiBadRequestException($message, $status);
      }
      throw new \Exception('Status code: ' . $status . '. ' . $message, $status);
    }
    return $response;
  }

  /**
   * Pulls the error message out of an API error response body.
   *
   * @param string $body
   *   The raw response body.
   * @param int $status
   *   The HTTP status code, used when the body has no message.
   *
   * @return string
   *   The API error message, the raw body, or the status code as a fallback.
   */
  protected function extractErrorMessage(string $body, int $status): string {
    $data = json_decode($body, TRUE);
    $message = is_array($data) ? ($data['error']['message'] ?? $data['message'] ?? NULL) : NULL;
    if (is_string($message) && $message !== '') {
      return $message;
    }
    return $body !== '' ? $body : 'Status code: ' . $status;
  }

  /**
   * Decodes a JSON response body, wrapping it in a result object.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The response.
   * @param string $resultClass
   *   The result class to wrap the decoded data in.
   *
   * @return mixed
   *   An instance of $resultClass, or the raw body string on failure to
   *   json_decode.
   */
  protected function decodeJsonResponse(ResponseInterface $response, string $resultClass = ChatResult::class) {
    $data = json_decode($response->getBody()->getContents(), TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      // If error return pure string.
      return $response->getBody()->getContents();
    }
    return new $resultClass($data);
  }

}
