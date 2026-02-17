<?php

declare(strict_types=1);

namespace Plugs\AI\Drivers\Vector;

use Plugs\AI\Contracts\VectorStore;

class LocalVectorStore implements VectorStore
{
    protected string $path;
    protected array $data = [];

    public function __construct(array $config)
    {
        $this->path = $config['path'] ?? storage_path('ai/vectors.json');
        $this->load();
    }

    protected function load(): void
    {
        if (file_exists($this->path)) {
            $this->data = json_decode(file_get_contents($this->path), true) ?: [];
        }
    }

    protected function save(): void
    {
        $directory = dirname($this->path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        file_put_contents($this->path, json_encode($this->data));
    }

    /**
     * @inheritDoc
     */
    public function add(string $id, array $vector, array $metadata = []): void
    {
        $this->data[$id] = [
            'vector' => $vector,
            'metadata' => $metadata,
        ];
        $this->save();
    }

    /**
     * @inheritDoc
     */
    public function search(array $vector, int $limit = 10): array
    {
        $results = [];

        foreach ($this->data as $id => $item) {
            $similarity = $this->cosineSimilarity($vector, $item['vector']);
            $results[] = [
                'id' => $id,
                'metadata' => $item['metadata'],
                'similarity' => $similarity,
            ];
        }

        usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($results, 0, $limit);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $id): void
    {
        unset($this->data[$id]);
        $this->save();
    }

    /**
     * Calculate cosine similarity between two vectors.
     */
    protected function cosineSimilarity(array $vec1, array $vec2): float
    {
        $dotProduct = 0;
        $normA = 0;
        $normB = 0;

        foreach ($vec1 as $i => $val) {
            $dotProduct += $val * ($vec2[$i] ?? 0);
            $normA += $val * $val;
            $normB += ($vec2[$i] ?? 0) * ($vec2[$i] ?? 0);
        }

        if ($normA == 0 || $normB == 0) {
            return 0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
}
