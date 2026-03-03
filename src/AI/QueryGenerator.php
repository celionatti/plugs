<?php

declare(strict_types=1);

namespace Plugs\AI;

use Plugs\Base\Model\PlugModel;
use Plugs\Container\Container;
use Exception;

class QueryGenerator
{
    protected $ai;

    public function __construct()
    {
        $this->ai = Container::getInstance()->make('ai');
    }

    /**
     * Generate SQL from natural language for a specific model.
     * 
     * @param string|PlugModel $model
     * @param string $prompt
     * @return array{sql: string, params: array}
     */
    public function generate($model, string $prompt): array
    {
        $modelInstance = is_string($model) ? new $model() : $model;
        $tableName = $modelInstance->getTable();
        $schemaContext = $this->getSchemaContext($modelInstance);

        $systemPrompt = "You are a senior database engineer. Convert the user's natural language request into a valid SQL SELECT statement for the table '{$tableName}'.\n\n"
            . "Table Schema:\n{$schemaContext}\n\n"
            . "CRITICAL RULES:\n"
            . "1. Return ONLY the SQL query. No explanation.\n"
            . "2. Use standard SQL syntax compatible with MySQL/SQLite.\n"
            . "3. MANDATORY: Use named parameters (e.g., :param_0) for all user-provided values or filters. NEVER embed values directly in the SQL string.\n"
            . "4. Return the parameters in a JSON block after the SQL.\n"
            . "5. ONLY 'SELECT' queries are allowed. NEVER generate INSERT, UPDATE, DELETE, or DROP.\n\n"
            . "Format your response as:\n"
            . "SQL: [Your SQL here]\n"
            . "PARAMS: [JSON params here or {}]";

        $response = $this->ai->prompt("{$systemPrompt}\n\nUser Request: {$prompt}");

        return $this->parseResponse($response);
    }

    /**
     * Extract schema information for the AI.
     */
    protected function getSchemaContext(PlugModel $model): string
    {
        $definition = method_exists($model, 'getSchemaDefinition') ? $model->getSchemaDefinition() : null;

        if (!$definition) {
            // Fallback: try to get columns from DB or just use basic info
            return "Table: " . $model->getTable() . " (Detailed schema not available via HasSchema)";
        }

        $context = "Table: " . $model->getTable() . "\nColumns:\n";
        $sensitive = method_exists($model, 'getSensitiveAttributes') ? $model->getSensitiveAttributes() : [];

        foreach ($definition->getFields() as $name => $field) {
            // Security: Never show sensitive columns (passwords, tokens, etc.) to the AI
            if (in_array($name, $sensitive)) {
                continue;
            }

            $type = basename(str_replace('\\', '/', get_class($field)));
            $context .= "- {$name} ({$type})\n";
        }

        return $context;
    }

    /**
     * Parse the AI response into SQL and params.
     */
    protected function parseResponse(string $response): array
    {
        $sql = '';
        $params = [];

        if (preg_match('/SQL:\s*(.*)/i', $response, $matches)) {
            $sql = trim($matches[1]);
        }

        if (preg_match('/PARAMS:\s*(\{.*\})/is', $response, $matches)) {
            $params = json_decode($matches[1], true) ?: [];
        }

        // Clean up SQL if it's wrapped in backticks or markdown
        $sql = preg_replace('/^```sql\s*|```$/i', '', $sql);
        $sql = trim($sql, " \n\r\t\v\0;");

        if (empty($sql)) {
            throw new Exception("AI failed to generate a valid SQL query. Response: {$response}");
        }

        // Security check: Only allow SELECT queries
        if (!preg_match('/^\s*SELECT\b/i', $sql)) {
            throw new Exception("Security Violation: AI generated a non-SELECT query. For safety, only read operations are permitted via the AI assistant.");
        }

        // Security check: Prevent multi-statement queries (semicolon injection)
        if (str_contains($sql, ';')) {
            throw new Exception("Security Violation: Multi-statement SQL query detected in AI response.");
        }

        // Security check: Prevent SQL comments (obfuscation)
        if (str_contains($sql, '--') || str_contains($sql, '/*')) {
            throw new Exception("Security Violation: SQL comments detected in AI response.");
        }

        return [
            'sql' => $sql,
            'params' => $params
        ];
    }
}
