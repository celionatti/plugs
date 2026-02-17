<?php

declare(strict_types=1);

namespace Plugs\AI\Contracts;

interface VectorStore
{
    /**
     * Add a vector to the store.
     *
     * @param string $id
     * @param array $vector
     * @param array $metadata
     * @return void
     */
    public function add(string $id, array $vector, array $metadata = []): void;

    /**
     * Search for similar vectors.
     *
     * @param array $vector
     * @param int $limit
     * @return array
     */
    public function search(array $vector, int $limit = 10): array;

    /**
     * Delete a vector from the store.
     *
     * @param string $id
     * @return void
     */
    public function delete(string $id): void;
}
