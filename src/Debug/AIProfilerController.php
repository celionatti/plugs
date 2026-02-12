<?php

declare(strict_types=1);

namespace Plugs\Debug;

use Plugs\Facades\AI;
use Plugs\Http\Message\ServerRequest as Request;
use Plugs\Http\ResponseFactory;

class AIProfilerController
{
    /**
     * Analyze a slow request and provide optimization tips
     */
    public function analyzeRequest(Request $request)
    {
        if (!config('security.ai_profiler.enabled', true)) {
            return ResponseFactory::json(['success' => false, 'error' => 'AI Profiler is disabled'], 403);
        }

        $profile = $request->input('profile');
        if (!$profile) {
            return ResponseFactory::json(['success' => false, 'error' => 'No profile data provided'], 400);
        }

        // Format prompt for AI
        $prompt = "Analyze this PHP request performance profile and provide 3 actionable optimization tips. Focus on the bottlenecks.\n\n";
        $prompt .= "Total Time: " . ($profile['execution_time_ms'] ?? 0) . "ms\n";
        $prompt .= "Middleware Time: " . ($profile['middleware_time_ms'] ?? 0) . "ms\n";
        $prompt .= "DB Queries: " . ($profile['query_count'] ?? 0) . " (" . ($profile['query_time_ms'] ?? 0) . "ms)\n";
        $prompt .= "Memory: " . ($profile['memory_formatted'] ?? 'N/A') . "\n";

        if (!empty($profile['queries'])) {
            $prompt .= "\nSlowest Queries:\n";
            foreach (array_slice($profile['queries'], 0, 3) as $q) {
                $prompt .= "- " . ($q['time'] ?? 0) . "s: " . ($q['query'] ?? 'N/A') . "\n";
            }
        }

        try {
            $analysis = AI::chat([
                ['role' => 'system', 'content' => 'You are a PHP performance expert. Be concise and technical.'],
                ['role' => 'user', 'content' => $prompt]
            ]);

            return ResponseFactory::json(['success' => true, 'analysis' => $analysis]);
        } catch (\Throwable $e) {
            return ResponseFactory::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Analyze a specific SQL query
     */
    public function analyzeSql(Request $request)
    {
        if (!config('security.ai_profiler.enabled', true)) {
            return ResponseFactory::json(['success' => false, 'error' => 'AI Profiler is disabled'], 403);
        }

        $sql = $request->input('sql');
        if (!$sql) {
            return ResponseFactory::json(['success' => false, 'error' => 'No SQL provided'], 400);
        }

        try {
            $analysis = AI::chat([
                ['role' => 'system', 'content' => 'You are a MySQL expert. Explain this query and suggest indexes or optimizations.'],
                ['role' => 'user', 'content' => "Analyze this SQL query:\n```sql\n$sql\n```"]
            ]);

            return ResponseFactory::json(['success' => true, 'analysis' => $analysis]);
        } catch (\Throwable $e) {
            return ResponseFactory::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
