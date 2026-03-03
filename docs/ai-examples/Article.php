<?php

declare(strict_types=1);

/**
 * Example Article Model
 *
 * This file demonstrates how to use the Plugs HasAI trait.
 * To use this in your app, move it to app/Models and adjust the namespace.
 */

namespace Examples;

use Plugs\AI\Traits\HasAI;
use Plugs\Base\Model\PlugModel;

class Article extends PlugModel
{
    use HasAI;

    protected $table = 'articles';

    protected $fillable = [
        'title',
        'slug',
        'content',
        'summary',
        'status',
        'seo_keywords',
    ];

    /** @var string|null */
    public $summary;

    /**
     * Get suggested topics from AI.
     */
    public static function suggestTopics(string $niche = 'Technology'): array
    {
        $prompt = "Suggest 5 trending blog post topics for '{$niche}'. Return ONLY a JSON array of strings.";
        $response = \Plugs\Facades\AI::prompt($prompt);

        if (preg_match('/\[[\s\S]*\]/', $response, $matches)) {
            return json_decode($matches[0], true) ?: [];
        }

        return [];
    }
}
