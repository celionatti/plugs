<?php

declare(strict_types=1);

namespace Modules\Admin\Controllers;

use Modules\Admin\Services\AdminArticleService;
use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AdminArticleController
{
    protected AdminArticleService $articleService;

    public function __construct(AdminArticleService $articleService)
    {
        $this->articleService = $articleService;
    }

    /**
     * Display a listing of the articles.
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $articles = $this->articleService->getAllArticles();
        }
        catch (\Throwable $e) {
            $articles = [];
        }

        return response(view('admin::articles.index', [
            'title' => 'Articles Management',
            'articles' => $articles
        ]));
    }

    /**
     * Show the form for creating a new article.
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        return response(view('admin::articles.create', [
            'title' => 'Create Article'
        ]));
    }

    /**
     * Store a newly created article.
     */
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();

        $this->articleService->createArticle($data);

        return ResponseFactory::redirect('/admin/articles')
            ->with('success', 'Article created successfully.');
    }

    /**
     * Show edit form.
     */
    public function edit(ServerRequestInterface $request, $id): ResponseInterface
    {
        $article = $this->articleService->findArticle((int)$id);

        if (!$article) {
            return ResponseFactory::redirect('/admin/articles')
                ->with('error', 'Article not found.');
        }

        return response(view('admin::articles.edit', [
            'title' => 'Edit Article',
            'article' => $article
        ]));
    }

    /**
     * Update article.
     */
    public function update(ServerRequestInterface $request, $id): ResponseInterface
    {
        $article = $this->articleService->findArticle((int)$id);

        if (!$article) {
            return ResponseFactory::redirect('/admin/articles')
                ->with('error', 'Article not found.');
        }

        $data = $request->getParsedBody();
        $this->articleService->updateArticle($article, $data);

        return ResponseFactory::redirect('/admin/articles')
            ->with('success', 'Article updated successfully.');
    }

    /**
     * Delete article.
     */
    public function destroy(ServerRequestInterface $request, $id): ResponseInterface
    {
        $article = $this->articleService->findArticle((int)$id);

        if ($article) {
            $this->articleService->deleteArticle($article);
        }

        return ResponseFactory::redirect('/admin/articles')
            ->with('success', 'Article deleted successfully.');
    }
}
