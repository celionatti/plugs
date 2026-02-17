<?php

declare(strict_types=1);

namespace Plugs\AI\Traits;

use Plugs\Facades\AI;

trait Embeddable
{
    /**
     * Boot the Embeddable trait.
     */
    public static function bootEmbeddable(): void
    {
        static::saved(function ($model) {
            $model->syncToVectorStore();
        });

        static::deleted(function ($model) {
            $model->deleteFromVectorStore();
        });
    }

    /**
     * Sync the model to the vector store.
     */
    public function syncToVectorStore(): void
    {
        $content = $this->toEmbeddingString();
        $vector = AI::embed($content);

        AI::vector()->add($this->getVectorId(), $vector, $this->getVectorMetadata());
    }

    /**
     * Delete the model from the vector store.
     */
    public function deleteFromVectorStore(): void
    {
        AI::vector()->delete($this->getVectorId());
    }

    /**
     * Get the unique ID for vector storage.
     */
    protected function getVectorId(): string
    {
        return get_class($this) . ':' . $this->getKey();
    }

    /**
     * Get the string representation of the model for embedding.
     */
    public function toEmbeddingString(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Get the metadata to be stored alongside the vector.
     */
    protected function getVectorMetadata(): array
    {
        return array_merge($this->toArray(), [
            '__model' => get_class($this),
            '__id' => $this->getKey(),
        ]);
    }
}
