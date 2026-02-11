<?php

declare(strict_types=1);

namespace Plugs\AI\Drivers;

use GuzzleHttp\Client;
use RuntimeException;

class GeminiDriver extends AIBaseDriver
{
    protected Client $client;

    public function __construct(array $config)
    {
        parent::__construct($config);

        $this->client = new Client([
            'base_uri' => 'https://generativelanguage.googleapis.com/v1beta/models/',
            'query' => ['key' => $this->getConfig('api_key')],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * @inheritDoc
     */
    public function prompt(string $prompt, array $options = []): string
    {
        try {
            $response = $this->client->post("{$this->model}:generateContent", [
                'json' => [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]]
                    ]
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        } catch (\Exception $e) {
            throw new RuntimeException("Gemini API Error: " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function chat(array $messages, array $options = []): string
    {
        $contents = array_map(function ($message) {
            return [
                'role' => $message['role'] === 'user' ? 'user' : 'model',
                'parts' => [['text' => $message['content']]]
            ];
        }, $messages);

        try {
            $response = $this->client->post("{$this->model}:generateContent", [
                'json' => [
                    'contents' => $contents
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        } catch (\Exception $e) {
            throw new RuntimeException("Gemini API Error: " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}
