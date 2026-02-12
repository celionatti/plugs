<?php

declare(strict_types=1);

/**
 * Example Article Admin Controller
 * 
 * This file demonstrates a workflow for AI-assisted blogging.
 */

namespace Examples;

use Plugs\Base\Controller\Controller;
use Plugs\Facades\AI;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class ArticleAdminController extends Controller
{
    public function dashboard(): ResponseInterface
    {
        $topics = Article::suggestTopics('Modern PHP');
        return $this->view('admin/articles/ai-dashboard', ['topics' => $topics]);
    }

    public function generate(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();
        $topic = $data['topic'] ?? 'AI in Web Dev';

        $article = new Article(['title' => $topic]);
        $article->generate('content', "Write a post about {$topic}");
        $article->summary = $article->summarize();
        $article->save();

        return $this->redirect('/admin/articles')->with('success', 'AI Drafted!');
    }
}
