<?php

declare(strict_types=1);

/**
 * Example Article Model
 * 
 * This file demonstrates how to use the Plugs HasAI trait.
 * To use this in your app, move it to app/Models and adjust the namespace.
 */

namespace Examples;

use Plugs\Base\Model\PlugModel;
use Plugs\AI\Traits\HasAI;

class Article extends PlugModel
{
    use HasAI;

    protected string $table = 'articles';

    protected array $fillable = [
        'title',
        'slug',
        'content',
        'summary',
        'status',
        'seo_keywords',
    ];

    /**
     * Get suggested topics from AI.
     */
    public static function suggestTopics(string $niche = 'Technology'): array
    {
        $ai = \Plugs\Facades\AI::getInstance();
        $prompt = "Suggest 5 trending blog post topics for '{$niche}'. Return ONLY a JSON array of strings.";
        $response = $ai->prompt($prompt);

        if (preg_match('/\[[\s\S]*\]/', $response, $matches)) {
            return json_decode($matches[0], true) ?: [];
        }

        return [];
    }
}
