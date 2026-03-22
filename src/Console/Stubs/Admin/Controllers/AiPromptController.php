<?php

declare(strict_types=1);

namespace Modules\Admin\Controllers;

use Plugs\AI\AIManager;
use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Plugs\Container\Container;

class AiPromptController
{
    protected AIManager $aiManager;

    public function __construct()
    {
        $this->aiManager = Container::getInstance()->make(AIManager::class);
    }

    /**
     * Display the AI prompt interface.
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        return response(view('admin::ai-prompt.index', [
            'title' => 'AI Prompt Section',
        ]));
    }

    /**
     * Return AI configuration status for the frontend.
     */
    public function status(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $config = config('ai');
            $defaultDriver = $config['default'] ?? 'unknown';
            $providers = $config['providers'] ?? [];

            // Find the active driver (one with a key)
            $activeDriver = null;
            $activeModel = null;

            // First try the default
            if (!empty($providers[$defaultDriver]['api_key'])) {
                $activeDriver = $defaultDriver;
                $activeModel = $providers[$defaultDriver]['model'] ?? 'unknown';
            } else {
                // Fallback: find any driver with a key
                foreach ($providers as $name => $providerConfig) {
                    if (!empty($providerConfig['api_key'])) {
                        $activeDriver = $name;
                        $activeModel = $providers[$defaultDriver]['model'] ?? 'unknown';
                        break;
                    }
                }
            }

            return ResponseFactory::json([
                'configured' => $activeDriver !== null,
                'driver' => $activeDriver ?? $defaultDriver,
                'model' => $activeModel ?? 'none',
                'default_driver' => $defaultDriver,
                'has_key' => $activeDriver !== null,
                'available_drivers' => array_keys(array_filter($providers, fn($p) => !empty($p['api_key']))),
            ]);
        } catch (\Exception $e) {
            return ResponseFactory::json([
                'configured' => false,
                'driver' => 'unknown',
                'model' => 'none',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate content based on the prompt.
     */
    public function generate(ServerRequestInterface $request): ResponseInterface
    {
        // Parse request body (handle both JSON and form data)
        $contentType = $request->getHeaderLine('Content-Type');

        if (str_contains($contentType, 'application/json')) {
            $data = json_decode((string) $request->getBody(), true) ?? [];
        } else {
            $data = $request->getParsedBody() ?? [];
        }

        $prompt = $data['prompt'] ?? '';

        if (empty(trim($prompt))) {
            return ResponseFactory::json([
                'error' => 'Prompt is required.',
                'error_type' => 'validation',
                'details' => 'Please enter a prompt describing what you want to generate.',
            ], 400);
        }

        // Step 1: Verify AI configuration
        try {
            $config = config('ai');
            $driver = $this->aiManager->driver();
        } catch (\Exception $e) {
            return ResponseFactory::json([
                'error' => 'AI is not configured properly.',
                'error_type' => 'config',
                'details' => $e->getMessage(),
                'suggestion' => 'Check your .env file and ensure at least one AI provider has an API key set (e.g. GROQ_API_KEY, OPENAI_API_KEY).',
            ], 500);
        }

        // Step 2: Load prompt template
        $promptPath = base_path('src/AI/Prompts/scaffold.prompt');
        if (!file_exists($promptPath)) {
            return ResponseFactory::json([
                'error' => 'Prompt template not found.',
                'error_type' => 'template',
                'details' => "Missing file: src/AI/Prompts/scaffold.prompt",
                'suggestion' => 'The scaffold prompt template is missing from the framework. Reinstall or restore the file.',
            ], 500);
        }

        $promptTemplate = file_get_contents($promptPath);
        $fullPrompt = str_replace('{{prompt}}', $prompt, $promptTemplate);

        // Step 3: Call the AI
        try {
            $result = $this->aiManager->prompt($fullPrompt);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $suggestion = 'Check that your API key is valid and you have available credits/quota.';

            // Provide specific suggestions based on error type
            if (str_contains($errorMessage, '401') || str_contains(strtolower($errorMessage), 'unauthorized')) {
                $suggestion = 'Your API key appears to be invalid or expired. Check your .env file.';
            } elseif (str_contains($errorMessage, '429') || str_contains(strtolower($errorMessage), 'rate')) {
                $suggestion = 'You have hit the API rate limit. Wait a moment and try again.';
            } elseif (str_contains($errorMessage, '500') || str_contains(strtolower($errorMessage), 'server')) {
                $suggestion = 'The AI provider is experiencing issues. Try again later or switch to a different driver.';
            } elseif (str_contains(strtolower($errorMessage), 'connect') || str_contains(strtolower($errorMessage), 'curl')) {
                $suggestion = 'Cannot connect to the AI provider. Check your internet connection.';
            }

            return ResponseFactory::json([
                'error' => 'AI generation failed.',
                'error_type' => 'api',
                'details' => $errorMessage,
                'suggestion' => $suggestion,
                'driver' => $this->aiManager->getDefaultDriver(),
            ], 500);
        }

        // Step 4: Parse the response
        if (empty(trim($result))) {
            return ResponseFactory::json([
                'error' => 'AI returned an empty response.',
                'error_type' => 'empty_response',
                'details' => 'The AI model returned no content. This may be due to the prompt being too long or a content filter.',
                'suggestion' => 'Try a simpler prompt or check your AI provider dashboard for details.',
            ], 500);
        }

        // Try to extract JSON from the result (AI might wrap it in markdown code blocks)
        $jsonResult = $result;

        // Strip markdown code blocks if present
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?\s*```/s', $result, $matches)) {
            $jsonResult = $matches[1];
        }

        $decoded = json_decode(trim($jsonResult), true);

        // Track what was created
        $createdFiles = [];
        $failedFiles = [];

        // Step 5: Create files from response
        if ($decoded !== null && isset($decoded['files']) && is_array($decoded['files'])) {
            $basePath = rtrim(base_path(''), "/\\") . DIRECTORY_SEPARATOR; // Ensure trailing slash logic works

            foreach ($decoded['files'] as $fileDef) {
                if (isset($fileDef['path']) && isset($fileDef['content'])) {
                    // Sanitize path (prevent directory traversal)
                    $cleanPath = trim($fileDef['path'], "/\\");
                    
                    // Basic security check to prevent writing outside project root
                    if (str_contains($cleanPath, '../') || str_contains($cleanPath, '..\\')) {
                        $failedFiles[] = [
                            'path' => $cleanPath,
                            'error' => 'Path traversal detected.'
                        ];
                        continue;
                    }

                    $absolutePath = $basePath . $cleanPath;
                    $dir = dirname($absolutePath);

                    try {
                        // Create directory recursively if it doesn't exist
                        if (!is_dir($dir)) {
                            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                                throw new \RuntimeException(sprintf('Directory "%s" was not created', $dir));
                            }
                        }

                        // Write the file
                        if (file_put_contents($absolutePath, $fileDef['content']) !== false) {
                            $createdFiles[] = $cleanPath;
                        } else {
                            $failedFiles[] = [
                                'path' => $cleanPath,
                                'error' => 'Failed to write file to disk.'
                            ];
                        }
                    } catch (\Exception $fileEx) {
                        $failedFiles[] = [
                            'path' => $cleanPath,
                            'error' => $fileEx->getMessage()
                        ];
                    }
                }
            }
        }

        return ResponseFactory::json([
            'success' => true,
            'result' => $decoded ?: null,
            'created_files' => $createdFiles,
            'failed_files' => $failedFiles,
            'raw' => $result,
            'parsed' => $decoded !== null,
        ]);
    }
}
