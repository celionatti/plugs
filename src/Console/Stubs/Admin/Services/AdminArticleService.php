<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

use App\Models\Article;
use Plugs\Database\Collection;

class AdminArticleService
{
    /**
     * Get all articles for admin management.
     *
     * @return array|Collection
     */
    public function getAllArticles(): array|Collection
    {
        return Article::all();
    }

    /**
     * Find an article by ID.
     *
     * @param int $id
     * @return Article|null
     */
    public function findArticle(int $id): ?Article
    {
        return Article::find($id);
    }

    /**
     * Create a new article.
     *
     * @param array $data
     * @return Article
     */
    public function createArticle(array $data): Article
    {
        if (!isset($data['slug']) && isset($data['title'])) {
            $data['slug'] = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['title'])));
        }

        return Article::create($data);
    }

    /**
     * Update an existing article.
     *
     * @param Article $article
     * @param array $data
     * @return bool
     */
    public function updateArticle(Article $article, array $data): bool
    {
        if (isset($data['title']) && (!isset($data['slug']) || empty($data['slug']))) {
            $data['slug'] = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['title'])));
        }

        return $article->update($data);
    }

    /**
     * Delete an article.
     *
     * @param Article $article
     * @return bool
     */
    public function deleteArticle(Article $article): bool
    {
        return $article->delete();
    }
}
