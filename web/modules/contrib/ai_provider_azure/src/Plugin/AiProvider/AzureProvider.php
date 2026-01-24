<?php

namespace Drupal\ai_provider_azure\Plugin\AiProvider;

use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\Enum\AiProviderCapability;
use Drupal\ai\Exception\AiBadRequestException;
use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\Exception\AiSetupFailureException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatInterface;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInterface;
use Drupal\ai\OperationType\Embeddings\EmbeddingsOutput;
use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextInput;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextInterface;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextOutput;
use Drupal\ai\OperationType\TextToImage\TextToImageInput;
use Drupal\ai\OperationType\TextToImage\TextToImageInterface;
use Drupal\ai\OperationType\TextToImage\TextToImageOutput;
use Drupal\ai\OperationType\TextToSpeech\TextToSpeechInput;
use Drupal\ai\OperationType\TextToSpeech\TextToSpeechInterface;
use Drupal\ai\OperationType\TextToSpeech\TextToSpeechOutput;
use Drupal\ai\Traits\OperationType\ChatTrait;
use Drupal\ai_provider_azure\AzureChatMessageIterator;
use Drupal\ai_provider_azure\Client\LightweightProviderClient;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\File\FileExists;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use OpenAI\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation of the 'azure' provider.
 */
#[AiProvider(
  id: 'azure',
  label: new TranslatableMarkup('Azure'),
)]
class AzureProvider extends AiProviderClientBase implements
  ContainerFactoryPluginInterface,
  ChatInterface,
  EmbeddingsInterface,
  TextToImageInterface,
  SpeechToTextInterface,
  TextToSpeechInterface {

  use ChatTrait;
  use StringTranslationTrait;

  /**
   * The OpenAI Client.
   *
   * @var \OpenAI\Client\Drupal\ai_azure_provide\LightweightProviderClient|null
   */
  protected $client;

  /**
   * The API key.
   *
   * @var string
   */
  protected $apiKey;

  /**
   * We want to add models to the provider dynamically.
   *
   * @var bool
   */
  protected bool $hasPredefinedModels = FALSE;

  /**
   * The token tree builder.
   *
   * @var \Drupal\token\TreeBuilder
   */
  protected $tokenTree;

  /**
   * The token consumer.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * Mapping of messages key to special consumers.
   *
   * @var array
   */
  protected array $messageConsumers = [
    '2023-06-01-preview-extensions-chat-completion' => [
      'messages_key' => 'messages',
      'is_multiple' => TRUE,
      'return_role' => 'assistant',
    ],
  ];

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    if ($container->has('token.tree_builder')) {
      $instance->tokenTree = $container->get('token.tree_builder');
    }
    $instance->token = $container->get('token');
    $instance->currentUser = $container->get('current_user');
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
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('ai_provider_azure.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getApiDefinition(): array {
    // Load the configuration.
    $definition = Yaml::parseFile($this->moduleHandler->getModule('ai_provider_azure')->getPath() . '/definitions/api_defaults.yml');
    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    return $generalConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthentication(mixed $authentication): void {
    // Set the new API key and reset the client.
    $this->apiKey = $authentication;
    $this->client = NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function canOverrideConfiguration(): bool {
    return TRUE;
  }

  /**
   * Gets the raw client.
   *
   * @param string $operation_type
   *   The operation type.
   * @param array $model
   *   The model info.
   * @param string $api_key
   *   If the API key should be hot swapped.
   *
   * @return \OpenAI\Client|\Drupal\ai_provider_azure\Client\LightweightProviderClient
   *   The OpenAI client.
   */
  public function getClient(string $operation_type, array $model, string $api_key = ''): Client|LightweightProviderClient {
    // Get the configuration for operation type.
    if ($api_key) {
      $this->setAuthentication($api_key);
    }
    else {
      // Get the API key from the configuration.
      $this->setAuthentication($this->loadAzureApiKey($model['api_key']));
    }
    $this->loadClient($operation_type, $model['endpoint'], $model);
    return $this->client;
  }

  /**
   * Loads the Azure Client with authentication if not initialized.
   *
   * @param string $operation_type
   *   The operation type.
   * @param string $endpoint
   *   The model ID endpoint.
   * @param array $data
   *   The model data.
   */
  protected function loadClient($operation_type, $endpoint, array $data): void {
    $variables = $this->createVariablesFromEndpoint($operation_type, $endpoint);
    // Load specialize client for certain tasks.
    if (isset($data['custom_consumer']) && $data['custom_consumer'] == '2023-06-01-preview-extensions-chat-completion') {
      $client = new LightweightProviderClient();
    }
    else {
      $client = \OpenAI::factory();
    }

    $client->withBaseUri($variables['endpoint']);
    $client->withHttpClient($this->httpClient);
    if (isset($variables['query'])) {
      foreach ($variables['query'] as $key => $value) {
        $client->withQueryParam($key, $value);
      }
    }
    $header = $data['connect_header'] ?? 'api-key';
    if ($data['connect_header'] == 'other' && !empty($data['custom_key_header'])) {
      $header = $data['custom_key_header'];
    }
    $client->withHttpHeader($header, $this->apiKey);

    // Also check for extra headers.
    if (!empty($data['extra_headers'])) {
      foreach (explode("\n", $data['extra_headers']) as $header) {
        // Tokenize the header.
        if ($this->tokenTree) {
          $header = $this->token->replace($header, [
            'user' => $this->currentUser,
          ]);
        }
        // Explode on the first :.
        $header = explode(':', $header, 2);
        if (count($header) == 2) {
          $client->withHttpHeader($header[0], $header[1]);
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
    if (!isset($info['endpoint'])) {
      throw new AiBadRequestException('The model does not exist.');
    }
    $this->getClient('chat', $info);

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
      'model' => $model_id,
      'messages' => $chat_input,
    ] + $this->configuration;

    // If we want to add tools to the input.
    if (method_exists($input, 'getChatTools') && $input->getChatTools()) {
      $payload['tools'] = $input->getChatTools()->renderToolsArray();
      foreach ($payload['tools'] as $key => $tool) {
        $payload['tools'][$key]['function']['strict'] = FALSE;
      }
    }
    // Check for structured json schemas.
    if (method_exists($input, 'getChatStructuredJsonSchema') && $input->getChatStructuredJsonSchema()) {
      $payload['response_format'] = [
        'type' => 'json_schema',
        'json_schema' => $input->getChatStructuredJsonSchema(),
      ];
    }

    try {
      if ($this->streamed) {
        $response = $this->client->chat()->createStreamed($payload);
        $message = new AzureChatMessageIterator($response);
      }
      else {
        $response = $this->client->chat()->create($payload)->toArray();
        // If tools are generated.
        $tools = [];
        if (!empty($response['choices'][0]['message']['tool_calls'])) {
          foreach ($response['choices'][0]['message']['tool_calls'] as $tool) {
            $arguments = Json::decode($tool['function']['arguments']);
            $tools[] = new ToolsFunctionOutput($input->getChatTools()->getFunctionByName($tool['function']['name']), $tool['id'], $arguments);
          }
        }
        $message = '';
      }
    }
    catch (\Exception $e) {
      // Try to figure out rate limit issues.
      if (strpos($e->getMessage(), 'Request too large') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      // Try to figure out quota issues.
      if (strpos($e->getMessage(), 'You exceeded your current quota') !== FALSE) {
        throw new AiQuotaException($e->getMessage());
      }
      else {
        throw $e;
      }
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
          $message = new ChatMessage($response['choices'][0][$consumer['messages_key']][0]['role'], $response['choices'][0][$consumer['messages_key']][0]['content']);
        }
      }
    }
    if (!$message) {
      throw new AiResponseErrorException('No message data found in the response.');
    }

    return new ChatOutput($message, $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function embeddings(string|EmbeddingsInput $input, string $model_id, array $tags = []): EmbeddingsOutput {
    $info = $this->getModelInfo('embeddings', $model_id);
    if (!isset($info['endpoint'])) {
      throw new AiBadRequestException('The model does not exist.');
    }
    $this->getClient('embeddings', $info);
    // Normalize the input if needed.
    if ($input instanceof EmbeddingsInput) {
      $input = $input->getPrompt();
    }
    // Send the request.
    $payload = [
      'model' => $model_id,
      'input' => $input,
      'dimensions' => (int) $info['dimensions'] ?? 1536,
    ] + $this->configuration;
    try {
      $response = $this->client->embeddings()->create($payload)->toArray();
    }
    catch (\Exception $e) {
      // Try to figure out rate limit issues.
      if (strpos($e->getMessage(), 'Request too large') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      // Try to figure out quota issues.
      if (strpos($e->getMessage(), 'You exceeded your current quota') !== FALSE) {
        throw new AiQuotaException($e->getMessage());
      }
      else {
        throw $e;
      }
    }

    return new EmbeddingsOutput($response['data'][0]['embedding'], $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function textToImage(string|TextToImageInput $input, string $model_id, array $tags = []): TextToImageOutput {
    $info = $this->getModelInfo('text_to_image', $model_id);
    if (!isset($info['endpoint'])) {
      throw new AiBadRequestException('The model does not exist.');
    }
    $this->getClient('text_to_image', $info);
    // Normalize the input if needed.
    if ($input instanceof TextToImageInput) {
      $input = $input->getText();
    }
    // The send.
    $payload = [
      'model' => $model_id,
      'prompt' => $input,
    ] + $this->configuration;
    try {
      $response = $this->client->images()->create($payload)->toArray();
    }
    catch (\Exception $e) {
      // Try to figure out rate limit issues.
      if (strpos($e->getMessage(), 'Request too large') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      else {
        throw $e;
      }
    }
    $images = [];

    if (empty($response['data'][0])) {
      throw new AiResponseErrorException('No image data found in the response.');
    }
    foreach ($response['data'] as $data) {
      if (isset($this->configuration['response_format']) && $this->configuration['response_format'] === 'url') {
        $images[] = new ImageFile(file_get_contents($data['url']), 'image/png', 'dalle.png');
      }
      else {
        $images[] = new ImageFile(base64_decode($data['b64_json']), 'image/png', 'dalle.png');
      }
    }
    return new TextToImageOutput($images, $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function speechToText(string|SpeechToTextInput $input, string $model_id, array $tags = []): SpeechToTextOutput {
    $info = $this->getModelInfo('speech_to_text', $model_id);
    if (!isset($info['endpoint'])) {
      throw new AiBadRequestException('The model does not exist.');
    }
    $this->getClient('speech_to_text', $info);
    // Normalize the input if needed.
    if ($input instanceof SpeechToTextInput) {
      $input = $input->getBinary();
    }
    // The raw file has to become a resource, so we save a temporary file first.
    $path = $this->fileSystem->saveData($input, 'temporary://speech_to_text.mp3', FileExists::Replace);
    $input = fopen($path, 'r');
    $payload = [
      'model' => $model_id,
      'file' => $input,
    ] + $this->configuration;
    try {
      $response = $this->client->audio()->transcribe($payload)->toArray();
    }
    catch (\Exception $e) {
      // Try to figure out rate limit issues.
      if (strpos($e->getMessage(), 'Request too large') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      else {
        throw $e;
      }
    }

    return new SpeechToTextOutput($response['text'], $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function textToSpeech(string|TextToSpeechInput $input, string $model_id, array $tags = []): TextToSpeechOutput {
    $info = $this->getModelInfo('text_to_speech', $model_id);
    if (!isset($info['endpoint'])) {
      throw new AiBadRequestException('The model does not exist.');
    }
    $this->getClient('text_to_speech', $info);
    // Normalize the input if needed.
    if ($input instanceof TextToSpeechInput) {
      $input = $input->getText();
    }
    // Moderation.
    // Send the request.
    $payload = [
      'model' => $model_id,
      'input' => $input,
    ] + $this->configuration;
    try {
      $response = $this->client->audio()->speech($payload);
    }
    catch (\Exception $e) {
      // Try to figure out rate limit issues.
      if (strpos($e->getMessage(), 'Request too large') !== FALSE) {
        throw new AiRateLimitException($e->getMessage());
      }
      // Try to figure out quota issues.
      if (strpos($e->getMessage(), 'You exceeded your current quota') !== FALSE) {
        throw new AiQuotaException($e->getMessage());
      }
      else {
        throw $e;
      }
    }
    $output = new AudioFile($response, 'audio/mpeg', 'openai.mp3');

    // Return a normalized response.
    return new TextToSpeechOutput([$output], $response, []);
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

    $form['model_data']['endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Endpoint'),
      '#description' => $this->t('The endpoint needed to access the Azure API. Can be found in Azure AI Studio under the Target URI label. NOTE that some models have different versions, its the endpoints with completions in the end you need to copy.'),
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
      '#options' => [
        '2023-06-01-preview-extensions-chat-completion' => '2023-06-01-preview-extensions-chat-completion',
      ],
    ];

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
  protected function loadAzureApiKey($key_id): string {
    $key = $this->keyRepository->getKey($key_id);
    // If it came here, but the key is missing, something is wrong with the env.
    if (!$key || !($api_key = $key->getKeyValue())) {
      throw new AiSetupFailureException(sprintf('Could not load the %s API key, please check your environment settings or your setup key.', $this->getPluginDefinition()['label']));
    }
    return $api_key;
  }

  /**
   * Create variables from endpoint.
   *
   * @param string $operation_type
   *   The operation type.
   * @param string $endpoint
   *   The endpoint.
   *
   * @return array
   *   The variables needed.
   */
  protected function createVariablesFromEndpoint(string $operation_type, string $endpoint): array {
    $types = [
      'chat' => 'chat/completions',
      'embeddings' => 'embeddings',
      'text_to_image' => 'images/generations',
      'speech_to_text' => 'audio/translations',
      'text_to_speech' => 'audio/speech',
    ];
    if (!isset($types[$operation_type])) {
      throw new AiBadRequestException('The operation type is not supported.');
    }
    $parts = explode($types[$operation_type], $endpoint);

    $variables = [
      'endpoint' => $parts[0],
      'query' => [],
    ];
    if (!empty($parts[1]) && strpos($parts[1], '?') !== FALSE) {
      foreach (explode('&', substr($parts[1], 1)) as $q) {
        $q = explode('=', $q);
        $variables['query'][$q[0]] = $q[1];
      }
    }
    return $variables;
  }

}
