<?php

namespace Drupal\ai_provider_azure\Plugin\AiProvider;

use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\OpenAiBasedProviderClientBase;
use Drupal\ai\Dto\TokenUsageDto;
use Drupal\ai\Enum\AiProviderCapability;
use Drupal\ai\Exception\AiBadRequestException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\Exception\AiSetupFailureException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;
use Drupal\ai\OperationType\Chat\Tools\ToolsInputInterface;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsOutput;
use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextInput;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextOutput;
use Drupal\ai\OperationType\TextToImage\TextToImageInput;
use Drupal\ai\OperationType\TextToImage\TextToImageOutput;
use Drupal\ai\OperationType\TextToSpeech\TextToSpeechInput;
use Drupal\ai\OperationType\TextToSpeech\TextToSpeechOutput;
use Drupal\ai\Traits\OperationType\ChatTrait;
use Drupal\ai_provider_azure\AzureChatMessageIterator;
use Drupal\ai_provider_azure\AzureResponsesMessageIterator;
use Drupal\ai_provider_azure\Client\LightweightProviderClient;
use Drupal\Component\Serialization\Json;
use Drupal\Core\File\FileExists;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use OpenAI\Exceptions\ErrorException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'azure' provider.
 */
#[AiProvider(
  id: 'azure',
  // The label is a product name, so the context tells translators not to
  // translate it. The bare word "Azure" was being translated as the color.
  label: new TranslatableMarkup('Microsoft Azure AI', [], ['context' => 'Product name']),
)]
class AzureProvider extends OpenAiBasedProviderClientBase {

  use ChatTrait;
  use StringTranslationTrait;

  /**
   * We want to add models to the provider dynamically.
   *
   * @var bool
   */
  protected bool $hasPredefinedModels = FALSE;

  /**
   * Optional token tree (only used for header token replacement).
   *
   * @var \Drupal\token\TreeBuilder|null
   */
  protected $tokenTree;

  /**
   * Token service.
   *
   * @var \Drupal\Core\Utility\Token|null
   */
  protected $token;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface|null
   */
  protected $currentUser;

  /**
   * Parsed query params from the endpoint.
   *
   * @var array
   */
  protected array $endpointQueryParams = [];

  /**
   * Model header override (api-key / authorization / custom).
   *
   * @var string
   */
  protected string $apiKeyHeader = 'api-key';

  /**
   * The chat API style detected from the configured endpoint.
   *
   * Either 'chat_completions' or 'responses'.
   *
   * @var string
   */
  protected string $apiStyle = 'chat_completions';

  /**
   * Mapping of messages key to special consumers.
   *
   * @var array
   */
  protected array $messageConsumers = [
    '2023-06-01-preview-extensions-chat-completion' => [
      'messages_key' => 'messages',
      'flat_messages' => FALSE,
      'is_multiple' => TRUE,
      'return_role' => 'assistant',
    ],
    '2025-01-01-preview-extensions-chat-completion' => [
      'messages_key' => 'message',
      'flat_messages' => TRUE,
      'is_multiple' => FALSE,
      'return_role' => 'assistant',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    if ($container->has('token.tree_builder')) {
      $instance->tokenTree = $container->get('token.tree_builder');
    }
    if ($container->has('token')) {
      $instance->token = $container->get('token');
    }
    if ($container->has('current_user')) {
      $instance->currentUser = $container->get('current_user');
    }
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    if ($operation_type) {
      return in_array($operation_type, $this->getSupportedOperationTypes());
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return [
      'chat',
      'embeddings',
      'text_to_image',
      'speech_to_text',
      'text_to_speech',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedCapabilities(): array {
    return [
      AiProviderCapability::StreamChatOutput,
    ];
  }

  /**
   * Model settings pass-through.
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    return $generalConfig;
  }

  /**
   * {@inheritDoc}
   */
  public function canOverrideConfiguration(): bool {
    return TRUE;
  }

  /**
   * Load (or reload) Azure client for an operation + model.
   *
   * Keeps $this->client as OpenAI\Client (parent contract) unless the
   * endpoint doesn't fit that client's assumptions (see
   * createVariablesFromEndpoint()) or a custom consumer demands it, in
   * which case a lightweight client is used instead.
   */
  protected function prepareClient(string $operationType, array $model, string $apiKeyOverride = ''): void {
    $apiKey = $apiKeyOverride ?: $this->loadAzureApiKey($model['api_key']);
    $this->setAuthentication($apiKey);

    // Parse endpoint and query params.
    if (empty($model['endpoint'])) {
      throw new AiBadRequestException('Missing endpoint in model definition.');
    }
    $vars = $this->createVariablesFromEndpoint($operationType, $model['endpoint']);
    // Base endpoint (domain portion) stored in parent $endpoint property.
    $this->endpoint = $vars['endpoint'];
    $this->endpointQueryParams = $vars['query'];
    $this->apiStyle = $vars['api_style'];

    // Resolve header name.
    $this->apiKeyHeader = $model['connect_header'] ?? 'api-key';
    if (($model['connect_header'] ?? '') === 'other' && !empty($model['custom_key_header'])) {
      $this->apiKeyHeader = $model['custom_key_header'];
    }

    // Endpoints that don't fit the fixed REST-path shape the openai-php
    // SDK assumes are used exactly as configured (see
    // createVariablesFromEndpoint()), which the SDK's client cannot do
    // since it always posts to "<base-uri>/<resource>". So those are
    // always talked to through the lightweight client instead. The same
    // applies when a custom consumer is configured, regardless of shape.
    if ($vars['literal'] || (($operationType === 'chat' || $operationType === 'embeddings') && !empty($model['custom_consumer']) && isset($this->messageConsumers[$model['custom_consumer']]))) {
      $client = new LightweightProviderClient();
      $client->withLiteralEndpoint($vars['literal']);
    }
    else {
      $client = \OpenAI::factory();
    }

    $client->withBaseUri($this->endpoint);
    $client->withHttpClient($this->httpClient);
    if (!empty($this->endpointQueryParams)) {
      foreach ($this->endpointQueryParams as $key => $value) {
        $client->withQueryParam($key, $value);
      }
    }
    $client->withHttpHeader($this->apiKeyHeader, $this->apiKey);

    // Extra headers (token replacement if token module present).
    if (!empty($model['extra_headers'])) {
      foreach (explode("\n", $model['extra_headers']) as $line) {
        $line = trim($line);
        if ($line === '') {
          continue;
        }
        if ($this->tokenTree && $this->token) {
          $line = $this->token->replace($line, ['user' => $this->currentUser]);
        }
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) {
          $client->withHttpHeader(trim($parts[0]), trim($parts[1]));
        }
      }
    }

    $this->client = $client->make();
  }

  /**
   * {@inheritdoc}
   */
  public function chat(array|string|ChatInput $input, string $model_id, array $tags = []): ChatOutput {
    $info = $this->getModelInfo('chat', $model_id);
    if (empty($info['endpoint'])) {
      throw new AiBadRequestException('The model does not exist.');
    }
    $this->prepareClient('chat', $info);

    if ($this->apiStyle === 'responses') {
      return $this->chatViaResponsesApi($input, $model_id, $info);
    }

    // Normalize the input if needed.
    $chat_input = $input;
    if ($input instanceof ChatInput) {
      $chat_input = [];
      // Add a system role if wanted.
      if ($this->chatSystemRole) {
        $chat_input[] = [
          'role' => 'system',
          'content' => $this->chatSystemRole,
        ];
      }
      /** @var \Drupal\ai\OperationType\Chat\ChatMessage $message */
      foreach ($input->getMessages() as $message) {
        $content = $message->getText();
        if (count($message->getImages())) {
          $content = [
            [
              'type' => 'text',
              'text' => $message->getText(),
            ],
          ];
          foreach ($message->getImages() as $image) {
            $content[] = [
              'type' => 'image_url',
              'image_url' => [
                'url' => $image->getAsBase64EncodedString(),
              ],
            ];
          }
        }
        $new_message = [
          'role' => $message->getRole(),
          'content' => $content,
        ];

        // If its a tools response.
        if ($message->getToolsId()) {
          $new_message['tool_call_id'] = $message->getToolsId();
        }

        // If we want the results from some older tools call.
        if ($message->getTools()) {
          $new_message['tool_calls'] = $message->getRenderedTools();
        }

        $chat_input[] = $new_message;
      }
    }
    $payload = [
      'model' => $this->resolveModelName($info, $model_id),
      'messages' => $chat_input,
    ] + $this->configuration;

    // Newer OpenAI models reject max_tokens and expect max_completion_tokens.
    $payload = $this->applyTokenLimitParameter($payload, $info);

    // If we want to add tools to the input.
    if (is_object($input) && method_exists($input, 'getChatTools') && $input->getChatTools()) {
      $payload['tools'] = $input->getChatTools()->renderToolsArray();
      foreach ($payload['tools'] as $key => $tool) {
        $payload['tools'][$key]['function']['strict'] = FALSE;
      }
    }
    // Check for structured json schemas.
    if (is_object($input) && method_exists($input, 'getChatStructuredJsonSchema') && $input->getChatStructuredJsonSchema()) {
      // Reject a broken schema here, since the API accepts it silently when
      // strict mode is off.
      $this->validateStructuredSchema($input->getChatStructuredJsonSchema()['schema'] ?? []);
      $payload['response_format'] = [
        'type' => 'json_schema',
        'json_schema' => $input->getChatStructuredJsonSchema(),
      ];
    }

    $message = '';
    try {
      if ($this->streamed) {
        $response = $this->client->chat()->createStreamed($payload);
        $message = new AzureChatMessageIterator($response);
      }
      else {
        $response = $this->client->chat()->create($payload)->toArray();
        // If tools are generated.
        $tools = [];
        if (!empty($response['choices'][0]['message']['tool_calls']) && is_object($input) && method_exists($input, 'getChatTools') && $input->getChatTools()) {
          foreach ($response['choices'][0]['message']['tool_calls'] as $tool) {
            $arguments = Json::decode($tool['function']['arguments']);
            $tools[] = new ToolsFunctionOutput($input->getChatTools()->getFunctionByName($tool['function']['name']), $tool['id'], $arguments);
          }
        }
      }
    }
    catch (\Throwable $e) {
      $this->handleApiThrowable($e);
      throw $e;
    }

    if ($this->streamed) {
      $message = new AzureChatMessageIterator($response);
    }
    else {
      $consumer = $this->messageConsumers[$info['custom_consumer']] ?? NULL;
      // If no consumer, we consume as usual.
      if (!$consumer) {
        $message = new ChatMessage($response['choices'][0]['message']['role'], $response['choices'][0]['message']['content'] ?? '', []);
        if (!empty($tools)) {
          $message->setTools($tools);
        }
      }
      else {
        // Otherwise check if its multiple or not.
        if ($consumer['is_multiple']) {
          foreach ($response['choices'][0][$consumer['messages_key']] as $data) {
            // Pick out the message that is relevant.
            if ($data['role'] == $consumer['return_role']) {
              $message = new ChatMessage($data['role'], $data['content']);
            }
          }
        }
        else {
          $role = $consumer['flat_messages'] ? $response['choices'][0][$consumer['messages_key']]['role'] : $response['choices'][0][$consumer['messages_key']][0]['role'];
          $content = $consumer['flat_messages'] ? $response['choices'][0][$consumer['messages_key']]['content'] : $response['choices'][0][$consumer['messages_key']][0]['content'];
          $message = new ChatMessage($role, $content);
        }
      }
    }
    if (empty($message)) {
      throw new AiResponseErrorException('No message data found in response.');
    }

    return new ChatOutput($message, $response, []);
  }

  /**
   * Runs a chat request through the Responses API.
   *
   * @param array|string|\Drupal\ai\OperationType\Chat\ChatInput $input
   *   The chat input.
   * @param string $model_id
   *   The model id.
   * @param array $info
   *   The model configuration.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The chat output.
   *
   * @throws \Drupal\ai\Exception\AiResponseErrorException
   */
  protected function chatViaResponsesApi(array|string|ChatInput $input, string $model_id, array $info): ChatOutput {
    $payload = [
      'model' => $this->resolveModelName($info, $model_id),
      'input' => $input instanceof ChatInput ? $this->buildResponsesInput($input) : $input,
    ] + $this->mapResponsesTokenLimit($this->configuration);

    if ($input instanceof ChatInput && $this->chatSystemRole) {
      $payload['instructions'] = $this->chatSystemRole;
    }

    if (method_exists($input, 'getChatTools') && $input->getChatTools()) {
      $payload['tools'] = $this->buildResponsesTools($input->getChatTools());
    }

    if (method_exists($input, 'getChatStructuredJsonSchema') && $input->getChatStructuredJsonSchema()) {
      $schema = $input->getChatStructuredJsonSchema();
      $payload['text']['format'] = [
        'type' => 'json_schema',
        'name' => $schema['name'],
        'schema' => $schema['schema'],
        'description' => $schema['description'],
        'strict' => $schema['strict'],
      ];
    }

    try {
      if ($this->streamed) {
        $response = $this->client->responses()->createStreamed($payload);
        $message = new AzureResponsesMessageIterator($response);
      }
      else {
        $response = $this->client->responses()->create($payload)->toArray();
        $message = $this->parseResponsesOutput($response, $input);
      }
    }
    catch (\Throwable $e) {
      $this->handleApiThrowable($e);
      throw $e;
    }

    // A response carrying neither text nor a tool call (a content filter
    // block, for instance) would otherwise surface as an empty message.
    if ($message instanceof ChatMessage && $message->getText() === '' && empty($message->getTools())) {
      throw new AiResponseErrorException('No message data found in response.');
    }

    $chat_output = new ChatOutput($message, $response, []);
    if (is_array($response) && !empty($response['usage'])) {
      $chat_output = $this->setResponsesTokenUsage($chat_output, $response);
    }
    return $chat_output;
  }

  /**
   * Helper function to set the token usage from a Responses API payload.
   *
   * The Responses API reports usage under different keys than Chat
   * Completions, so the base class' setChatTokenUsage() cannot be reused.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatOutput $chat_output
   *   The chat output to set the token usage on.
   * @param array $response
   *   The decoded Responses API payload.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The chat output with the token usage set.
   */
  protected function setResponsesTokenUsage(ChatOutput $chat_output, array $response): ChatOutput {
    $chat_output->setTokenUsage(new TokenUsageDto(
      input: $response['usage']['input_tokens'] ?? NULL,
      output: $response['usage']['output_tokens'] ?? NULL,
      total: $response['usage']['total_tokens'] ?? NULL,
      reasoning: $response['usage']['output_tokens_details']['reasoning_tokens'] ?? NULL,
      cached: $response['usage']['input_tokens_details']['cached_tokens'] ?? NULL,
    ));
    return $chat_output;
  }

  /**
   * Builds the Responses API 'input' array from a ChatInput.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatInput $input
   *   The chat input.
   *
   * @return array
   *   The input items.
   */
  protected function buildResponsesInput(ChatInput $input): array {
    $items = [];
    /** @var \Drupal\ai\OperationType\Chat\ChatMessage $message */
    foreach ($input->getMessages() as $message) {
      // A tool result being sent back to the model.
      if ($message->getToolsId()) {
        $items[] = [
          'type' => 'function_call_output',
          'call_id' => $message->getToolsId(),
          'output' => $message->getText(),
        ];
        continue;
      }
      // Replay the assistant's own previous tool call(s).
      if ($message->getTools()) {
        foreach ($message->getTools() as $tool) {
          $rendered = $tool->getOutputRenderArray();
          $items[] = [
            'type' => 'function_call',
            'call_id' => $rendered['id'],
            'name' => $rendered['function']['name'],
            'arguments' => $rendered['function']['arguments'],
          ];
        }
        continue;
      }

      // Assistant content already produced by the model is echoed back as
      // 'output_text', everything else is submitted as 'input_text'.
      $content_type = $message->getRole() === 'assistant' ? 'output_text' : 'input_text';
      $content = [
        ['type' => $content_type, 'text' => $message->getText()],
      ];
      foreach ($message->getImages() as $image) {
        $content[] = [
          'type' => 'input_image',
          'image_url' => $image->getAsBase64EncodedString(),
        ];
      }
      $items[] = [
        'role' => $message->getRole(),
        'content' => $content,
      ];
    }
    return $items;
  }

  /**
   * Builds Responses API tool definitions from a ToolsInputInterface.
   *
   * @param \Drupal\ai\OperationType\Chat\Tools\ToolsInputInterface $tools
   *   The tools input.
   *
   * @return array
   *   The flattened Responses API tool definitions.
   */
  protected function buildResponsesTools(ToolsInputInterface $tools): array {
    $rendered = [];
    foreach ($tools->renderToolsArray() as $tool) {
      $rendered[] = [
        'type' => 'function',
        'name' => $tool['function']['name'],
        'description' => $tool['function']['description'] ?: NULL,
        'parameters' => $tool['function']['parameters'],
        'strict' => FALSE,
      ];
    }
    return $rendered;
  }

  /**
   * Parses a non-streamed Responses API response into a ChatMessage.
   *
   * @param array $response
   *   The decoded response.
   * @param array|string|\Drupal\ai\OperationType\Chat\ChatInput $input
   *   The original chat input, used to resolve tool definitions.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatMessage
   *   The chat message.
   */
  protected function parseResponsesOutput(array $response, array|string|ChatInput $input): ChatMessage {
    $text = '';
    $tools = [];
    foreach ($response['output'] ?? [] as $item) {
      if (($item['type'] ?? '') === 'message') {
        foreach ($item['content'] ?? [] as $content) {
          if (($content['type'] ?? '') === 'output_text') {
            $text .= $content['text'] ?? '';
          }
        }
      }
      elseif (($item['type'] ?? '') === 'function_call' && method_exists($input, 'getChatTools') && $input->getChatTools()) {
        $arguments = Json::decode($item['arguments'] ?? '{}') ?? [];
        $tools[] = new ToolsFunctionOutput($input->getChatTools()->getFunctionByName($item['name'] ?? ''), $item['call_id'] ?? '', $arguments);
      }
    }

    $message = new ChatMessage('assistant', $text, []);
    if (!empty($tools)) {
      $message->setTools($tools);
    }
    return $message;
  }

  /**
   * {@inheritdoc}
   */
  public function embeddings(string|EmbeddingsInput $input, string $model_id, array $tags = []): EmbeddingsOutput {
    $info = $this->getModelInfo('embeddings', $model_id);
    if (empty($info['endpoint'])) {
      throw new AiBadRequestException('Model endpoint missing.');
    }
    $this->prepareClient('embeddings', $info);
    // Normalize the input if needed.
    if ($input instanceof EmbeddingsInput) {
      $input = $input->getPrompt();
    }
    // Send the request.
    $payload = [
      'model' => $this->resolveModelName($info, $model_id),
      'input' => $input,
      'dimensions' => (int) ($info['dimensions'] ?? 1536),
    ] + $this->configuration;

    try {
      $response = $this->client->embeddings()->create($payload)->toArray();
    }
    catch (\Throwable $e) {
      $this->handleApiThrowable($e);
      throw $e;
    }

    return new EmbeddingsOutput($response['data'][0]['embedding'], $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function textToImage(string|TextToImageInput $input, string $model_id, array $tags = []): TextToImageOutput {
    $info = $this->getModelInfo('text_to_image', $model_id);
    if (empty($info['endpoint'])) {
      throw new AiBadRequestException('Model endpoint missing.');
    }
    $this->prepareClient('text_to_image', $info);
    // Normalize the input if needed.
    if ($input instanceof TextToImageInput) {
      $input = $input->getText();
    }
    // The send.
    $payload = [
      'model' => $this->resolveModelName($info, $model_id),
      'prompt' => $input,
    ] + $this->configuration;

    try {
      $response = $this->client->images()->create($payload)->toArray();
    }
    catch (\Throwable $e) {
      $this->handleApiThrowable($e);
      throw $e;
    }

    if (empty($response['data'][0])) {
      throw new AiResponseErrorException('No image data found in the response.');
    }
    $images = [];
    foreach ($response['data'] as $data) {
      if (empty($data['b64_json'])) {
        throw new AiResponseErrorException('No image data found in the response.');
      }
      $image_content = base64_decode($data['b64_json'], TRUE);
      if ($image_content === FALSE) {
        throw new AiResponseErrorException('The image data is not valid base64.');
      }
      // Determine the mime type from the actual binary data, so that only
      // real images can end up in the output.
      $mime_type = $this->detectMimeType($image_content, static::ALLOWED_IMAGE_MIME_TYPES);
      if ($mime_type === NULL) {
        throw new AiResponseErrorException('The image data is not a valid image type.');
      }
      $images[] = new ImageFile($image_content, $mime_type, 'azure.png');
    }
    return new TextToImageOutput($images, $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function speechToText(string|SpeechToTextInput $input, string $model_id, array $tags = []): SpeechToTextOutput {
    $info = $this->getModelInfo('speech_to_text', $model_id);
    if (empty($info['endpoint'])) {
      throw new AiBadRequestException('Model endpoint missing.');
    }
    $this->prepareClient('speech_to_text', $info);
    // Normalize the input if needed.
    if ($input instanceof SpeechToTextInput) {
      $input = $input->getBinary();
    }
    // The raw file has to become a resource, so we save a temporary file first.
    $path = $this->fileSystem->saveData($input, 'temporary://speech_to_text.mp3', FileExists::Replace);
    $resource = fopen($path, 'r');
    $payload = [
      'model' => $this->resolveModelName($info, $model_id),
      'file' => $resource,
    ] + $this->configuration;

    try {
      $response = $this->client->audio()->transcribe($payload)->toArray();
    }
    catch (\Throwable $e) {
      $this->handleApiThrowable($e);
      throw $e;
    }
    finally {
      if (is_resource($resource)) {
        fclose($resource);
      }
    }

    return new SpeechToTextOutput($response['text'], $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function textToSpeech(string|TextToSpeechInput $input, string $model_id, array $tags = []): TextToSpeechOutput {
    $info = $this->getModelInfo('text_to_speech', $model_id);
    if (empty($info['endpoint'])) {
      throw new AiBadRequestException('Model endpoint missing.');
    }
    $this->prepareClient('text_to_speech', $info);
    // Normalize the input if needed.
    if ($input instanceof TextToSpeechInput) {
      $input = $input->getText();
    }
    // Send the request.
    $payload = [
      'model' => $this->resolveModelName($info, $model_id),
      'input' => $input,
    ] + $this->configuration;

    try {
      $response = $this->client->audio()->speech($payload);
    }
    catch (\Throwable $e) {
      $this->handleApiThrowable($e);
      throw $e;
    }

    $file = new AudioFile($response, 'audio/mpeg', 'azure_tts.mp3');
    return new TextToSpeechOutput([$file], $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function maxEmbeddingsInput($model_id = ''): int {
    return 8191;
  }

  /**
   * {@inheritdoc}
   */
  public function loadModelsForm(array $form, $form_state, string $operation_type, string|NULL $model_id = NULL): array {
    $form = parent::loadModelsForm($form, $form_state, $operation_type, $model_id);
    $config = $this->loadModelConfig($operation_type, $model_id);
    $consumer_ids = array_keys($this->messageConsumers);
    $consumer_options = $consumer_ids ? array_combine($consumer_ids, $consumer_ids) : [];

    $form['model_data']['model_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Model name'),
      '#description' => $this->t('The name of the model you are using. This value is used to identify the model in requests to Azure, so make sure the model name is entered in the correct format, for example <code>gpt-5.1-chat</code>.'),
      '#default_value' => $config['model_name'] ?? '',
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['model_data']['endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Endpoint'),
      '#description' => $this->t('The endpoint needed to access the Azure API. Can be found in Azure AI Studio under the Target URI label. Note that some models have different versions, its the endpoints with completions in the end you need to copy.<br>For example <code>https://mysubdomain.cognitiveservices.azure.com/openai/v1/chat/completions</code>.'),
      '#default_value' => $config['endpoint'] ?? '',
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['model_data']['api_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('Key'),
      '#description' => $this->t('The key needed to access the Azure API. Can be found in Azure AI Studio under the Key label.'),
      '#default_value' => $config['api_key'] ?? '',
      '#required' => TRUE,
      '#weight' => 2,
    ];

    $form['model_data']['connect_header'] = [
      '#type' => 'select',
      '#title' => $this->t('Type of model'),
      '#options' => [
        'api-key' => $this->t('OpenAI Based Header'),
        'authorization' => $this->t('General Authorization Header'),
        'other' => $this->t('Other/Custom'),
      ],
      '#description' => $this->t('If you use generic OpenAI choose OpenAI Based Header, otherwise General Authorization Header. IF you have custom key headers, you can choose other and fill in a value.'),
      '#required' => TRUE,
      '#empty_option' => $this->t('Select'),
      '#default_value' => $config['connect_header'] ?? FALSE,
      '#weight' => 2,
    ];

    $form['model_data']['custom_key_header'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom Header'),
      '#description' => $this->t('If you have a custom header, fill in the value here.'),
      '#default_value' => $config['custom_key_header'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="connect_header"]' => ['value' => 'other'],
        ],
      ],
      '#weight' => 2,
    ];

    $form['model_data']['advanced_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Azure Settings'),
      '#open' => FALSE,
      '#weight' => 50,
    ];

    $form['model_data']['advanced_settings']['custom_consumer'] = [
      '#type' => 'select',
      '#title' => $this->t('Custom Consumer'),
      '#description' => $this->t('If you have a custom consumer and you are behind a proxy, choose the version here. Only set if you know what you are doing.'),
      '#default_value' => $config['custom_consumer'] ?? '',
      '#empty_option' => $this->t('-- Default --'),
      '#options' => $consumer_options,
    ];

    if ($operation_type === 'chat') {
      $form['model_data']['advanced_settings']['use_max_completion_tokens'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Use max_completion_tokens instead of max_tokens'),
        '#description' => $this->t('Newer OpenAI models, such as the GPT-5 series, reject the <code>max_tokens</code> parameter with the error "Unsupported parameter: max_tokens". Enable this to send the token limit as <code>max_completion_tokens</code> instead. For reasoning models the limit also covers the reasoning tokens. Other model families may accept or reject either name, so check the documentation of your model before enabling this.'),
        '#default_value' => $config['use_max_completion_tokens'] ?? FALSE,
      ];
    }

    $form['model_data']['advanced_settings']['extra_headers'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Extra headers'),
      '#description' => $this->t('Sometimes you might need extra headers for Azure, you can add it here. One header per line in key:value format. If Token module is installed, user tokens are available here.'),
      '#attributes' => [
        'placeholder' => "Authorization:Bearer 123\nContent-Type:application/json",
      ],
      '#default_value' => $config['extra_headers'] ?? '',
    ];

    if ($this->tokenTree) {
      $form['model_data']['advanced_settings']['token_help'] = $this->tokenTree->buildRenderable([
        'current-user',
      ]);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateModelsForm(array $form, $form_state): void {
    // Parent validation.
    parent::validateModelsForm($form, $form_state);
    // Model ID has to be alphanumeric, hyphens or underscore.
    if ($form_state->getValue('connect_header') == 'other' && empty($form_state->getValue('custom_key_header'))) {
      $form_state->setErrorByName('custom_key_header', 'If you set other type of model, you have to fill in a custom authorization header.');
    }
  }

  /**
   * Load the provider API key from the key module.
   *
   * @param string $key_id
   *   The key ID.
   *
   * @return string
   *   The API key.
   */
  protected function loadAzureApiKey(string $key_id): string {
    $key = $this->keyRepository->getKey($key_id);
    // If it came here, but the key is missing, something is wrong with the env.
    if (!$key || !($api_key = $key->getKeyValue())) {
      throw new AiSetupFailureException(sprintf('Could not load the %s API key, please check your environment settings or your setup key.', $this->getPluginDefinition()['label']));
    }
    return $api_key;
  }

  /**
   * Renames the token limit parameter for models that require the new name.
   *
   * The AI module and the API defaults always provide the limit as
   * max_tokens. Newer OpenAI models reject that name, so for models that
   * have the option enabled the value is sent as max_completion_tokens. Both
   * names are never sent together, since the API rejects that as well.
   *
   * @param array $payload
   *   The request payload.
   * @param array $info
   *   The model configuration.
   *
   * @return array
   *   The payload with the token limit under the name the model expects.
   */
  protected function applyTokenLimitParameter(array $payload, array $info): array {
    if (empty($info['use_max_completion_tokens']) || !array_key_exists('max_tokens', $payload)) {
      return $payload;
    }
    // A NULL limit means "no limit", so there is nothing to carry over.
    if ($payload['max_tokens'] !== NULL) {
      $payload['max_completion_tokens'] = $payload['max_tokens'];
    }
    unset($payload['max_tokens']);
    return $payload;
  }

  /**
   * Renames the token limit to the name the Responses API expects.
   *
   * The AI module provides the limit as max_tokens, and the Chat Completions
   * option may have turned it into max_completion_tokens. The Responses API
   * knows neither and rejects them as unknown parameters, since it takes the
   * limit as max_output_tokens.
   *
   * @param array $configuration
   *   The request configuration.
   *
   * @return array
   *   The configuration with the limit under max_output_tokens.
   */
  protected function mapResponsesTokenLimit(array $configuration): array {
    foreach (['max_tokens', 'max_completion_tokens'] as $legacy) {
      if (!array_key_exists($legacy, $configuration)) {
        continue;
      }
      // An explicit max_output_tokens wins, and a NULL limit means "no
      // limit", so only a real value is carried over.
      if ($configuration[$legacy] !== NULL && !isset($configuration['max_output_tokens'])) {
        $configuration['max_output_tokens'] = $configuration[$legacy];
      }
      unset($configuration[$legacy]);
    }
    return $configuration;
  }

  /**
   * Resolves the model/deployment name to send to the API.
   *
   * The Model ID is restricted by the ai module to a safe character set
   * (letters, numbers, hyphens, underscores), since it is also used as a
   * config schema key. Some deployments use names outside of that set
   * (e.g. "gpt-5.1"), so the 'model_name' field holds the exact value to
   * send, while the Model ID itself stays config-safe.
   *
   * @param array $info
   *   The model configuration.
   * @param string $model_id
   *   The model id.
   *
   * @return string
   *   The configured model name if set, otherwise the model id.
   */
  protected function resolveModelName(array $info, string $model_id): string {
    return !empty($info['model_name']) ? $info['model_name'] : $model_id;
  }

  /**
   * Parse endpoint into base + query params, removing operation path tail.
   *
   * Each operation type has a known, fixed REST path tail (e.g.
   * 'embeddings', or 'chat/completions' for Chat Completions chat models).
   * When the configured endpoint contains that tail, it is stripped here
   * and re-added by the client at request time - this is how Azure's own
   * deployment-scoped endpoints work, e.g.
   * ".../deployments/<name>/chat/completions?api-version=...".
   *
   * Azure and third-party proxies don't always fit that shape though (a
   * proxy can, for instance, place the deployment name after the
   * operation segment instead of before it), and a client that always
   * requests "<base-uri>/<tail>" cannot reproduce that. Whenever the
   * expected tail is not found, for any operation type, the endpoint is
   * instead used exactly as configured, with only a genuine trailing
   * query string split off ('literal' mode).
   *
   * For 'chat' specifically, the endpoint also decides which API it speaks,
   * since the two use different payload and response shapes (the other
   * operation types only have one API shape each). The Responses API is
   * selected only when the endpoint has a whole '/responses' path segment
   * (so a deployment merely named "responses-something" is not mistaken
   * for one); anything else is treated as Chat Completions. A chat endpoint
   * carrying neither marker is therefore not an error - it is a base URL
   * for the client to append the tail to, which is how Azure OpenAI
   * deployments have always been allowed to be configured here.
   *
   * '/responses' is safe to key on because it is a fixed segment of the
   * published REST contract rather than a naming convention, in every
   * route generation Azure documents:
   * - v1 GA: server "{endpoint}/openai/v1" with path '/responses', giving
   *   ".../openai/v1/responses". See the v1 specification linked from
   *   https://learn.microsoft.com/azure/foundry/openai/api-version-lifecycle
   *   and https://learn.microsoft.com/azure/foundry/openai/how-to/responses.
   * - 2025-03-01-preview and 2025-04-01-preview: server
   *   "https://{endpoint}/openai" with path '/responses', giving
   *   ".../openai/responses?api-version=...". See the dated inference
   *   specifications in https://github.com/Azure/azure-rest-api-specs.
   * - Upstream OpenAI, whose route Azure's v1 surface is documented as
   *   being client-compatible with, and which proxies therefore mirror:
   *   server "https://api.openai.com/v1" with path '/responses'.
   * Chat Completions is '/chat/completions' in all three, so the two
   * markers cannot collide. What none of this covers is a proxy that
   * invents its own path - hence the marker check plus 'literal'
   * passthrough here, instead of assuming either shape.
   *
   * @param string $operationType
   *   The operation type.
   * @param string $endpoint
   *   The endpoint URL as configured on the model.
   *
   * @return array
   *   An array with keys 'endpoint', 'query', 'api_style' and 'literal'.
   */
  protected function createVariablesFromEndpoint(string $operationType, string $endpoint): array {
    $tails = [
      'chat' => 'chat/completions',
      'embeddings' => 'embeddings',
      'text_to_image' => 'images/generations',
      'speech_to_text' => 'audio/translations',
      'text_to_speech' => 'audio/speech',
    ];
    if (!isset($tails[$operationType])) {
      throw new AiBadRequestException('Unsupported operation type.');
    }
    $tail = $tails[$operationType];

    $apiStyle = 'chat_completions';
    $literal = !str_contains($endpoint, $tail);
    if ($literal && $operationType === 'chat') {
      if (preg_match('#/responses(/|\?|$)#', $endpoint)) {
        $apiStyle = 'responses';
      }
      else {
        // Neither tail is present. Rather than reject the endpoint, keep the
        // behavior every earlier version had and let the client append the
        // Chat Completions tail: a configured base URL such as
        // ".../openai/deployments/<name>" is a long-standing, working setup.
        $literal = FALSE;
      }
    }

    if ($literal || !str_contains($endpoint, $tail)) {
      // Nothing to strip, either because the endpoint is used exactly as
      // configured or because the tail is absent and the client will append
      // it. Both cases keep the path as configured and only separate a
      // genuine trailing query string - which must not be left on the base,
      // or an appended tail would land after the query string.
      $query_position = strpos($endpoint, '?');
      $base = $query_position === FALSE ? $endpoint : substr($endpoint, 0, $query_position);
      $remainder = $query_position === FALSE ? '' : substr($endpoint, $query_position);
    }
    else {
      $parts = explode($tail, $endpoint);
      $base = $parts[0];
      $remainder = $parts[1] ?? '';
    }

    $query = [];
    if ($remainder !== '' && strpos($remainder, '?') !== FALSE) {
      foreach (explode('&', substr($remainder, strpos($remainder, '?') + 1)) as $q) {
        if (strpos($q, '=') === FALSE) {
          continue;
        }
        [$k, $v] = explode('=', $q, 2);
        $query[$k] = $v;
      }
    }
    return [
      'endpoint' => rtrim($base, '/'),
      'query' => $query,
      'api_style' => $apiStyle,
      'literal' => $literal,
    ];
  }

  /**
   * {@inheritdoc}
   *
   * Adds the Azure specific rate limit wording on top of the generic OpenAI
   * detection done by the base class.
   */
  protected function handleApiException(\Exception $e): void {
    if (strpos($e->getMessage(), 'have exceeded the call rate limit') !== FALSE) {
      throw new AiRateLimitException($e->getMessage());
    }
    try {
      parent::handleApiException($e);
    }
    catch (\Exception $mapped) {
      // The base class rethrows what it does not recognize. Turn a rejected
      // request into a typed exception, keeping the API message intact.
      // ErrorException::getStatusCode() only exists from openai-php/client
      // 0.10.3, while the supported range starts at 0.10.1.
      if (
        $mapped === $e &&
        $e instanceof ErrorException &&
        method_exists($e, 'getStatusCode') &&
        in_array($e->getStatusCode(), [400, 422], TRUE)
      ) {
        throw new AiBadRequestException($e->getMessage(), $e->getCode(), $e);
      }
      throw $mapped;
    }
  }

  /**
   * Checks that a structured output JSON schema is consistent.
   *
   * Only the case that the API does not reject on its own is covered: keys
   * listed in "required" that are not defined in "properties". Object nodes
   * that use composition or references are skipped, because their keys may
   * be defined elsewhere.
   *
   * @param array $schema
   *   The JSON schema content, without the name and strict wrapper.
   *
   * @throws \Drupal\ai\Exception\AiBadRequestException
   *   If a required key is not defined in the properties.
   */
  protected function validateStructuredSchema(array $schema): void {
    $errors = [];
    $this->collectSchemaErrors($schema, '#', $errors);
    if ($errors) {
      throw new AiBadRequestException('The structured output JSON schema is invalid. ' . implode(' ', $errors));
    }
  }

  /**
   * Collects required keys that are not defined, recursing into sub schemas.
   *
   * @param array $node
   *   The schema node to check.
   * @param string $path
   *   The JSON pointer style location of the node, used in the messages.
   * @param string[] $errors
   *   The error messages collected so far, passed by reference.
   */
  protected function collectSchemaErrors(array $node, string $path, array &$errors): void {
    $composition = ['allOf', 'anyOf', 'oneOf', '$ref', 'if', 'then', 'else', 'patternProperties', 'dependentSchemas'];
    if (isset($node['properties'], $node['required']) && is_array($node['properties']) && is_array($node['required']) && !array_intersect_key($node, array_flip($composition))) {
      $missing = array_diff($node['required'], array_keys($node['properties']));
      if ($missing) {
        $errors[] = sprintf('The required key(s) "%s" at %s are not defined in the properties.', implode('", "', $missing), $path);
      }
    }
    // Properties and definitions map names to sub schemas.
    foreach (['properties', '$defs', 'definitions'] as $key) {
      foreach (is_array($node[$key] ?? NULL) ? $node[$key] : [] as $name => $child) {
        if (is_array($child)) {
          $this->collectSchemaErrors($child, $path . '/' . $key . '/' . $name, $errors);
        }
      }
    }
    // Array items and additional properties hold a single sub schema, items
    // may also be a list of them.
    foreach (['items', 'additionalProperties'] as $key) {
      if (!is_array($node[$key] ?? NULL)) {
        continue;
      }
      $children = array_is_list($node[$key]) ? $node[$key] : [$node[$key]];
      foreach ($children as $child) {
        if (is_array($child)) {
          $this->collectSchemaErrors($child, $path . '/' . $key, $errors);
        }
      }
    }
  }

}
