<?php

declare(strict_types=1);

namespace Plugs\AI\Drivers;

use GuzzleHttp\Client;
use RuntimeException;

class AnthropicDriver extends AIBaseDriver
{
    protected Client $client;

    public function __construct(array $config)
    {
        parent::__construct($config);

        $this->client = new Client([
            'base_uri' => 'https://api.anthropic.com/v1/',
            'headers' => [
                'x-api-key' => $this->getConfig('api_key'),
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * @inheritDoc
     */
    public function prompt(string $prompt, array $options = []): string
    {
        return $this->chat([
            ['role' => 'user', 'content' => $prompt]
        ], $options);
    }

    /**
     * @inheritDoc
     */
    public function chat(array $messages, array $options = []): string
    {
        try {
            $response = $this->client->post('messages', [
                'json' => array_merge([
                    'model' => $this->model,
                    'messages' => $messages,
                    'max_tokens' => $options['max_tokens'] ?? 1024,
                ], $options),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['content'][0]['text'] ?? '';
        } catch (\Exception $e) {
            throw new RuntimeException("Anthropic API Error: " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}
