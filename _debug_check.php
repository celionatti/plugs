<?php
require 'vendor/autoload.php';

// Bootstrap the app
$app = require 'bootstrap/app.php';

// Test the relationship
$articles = \Modules\Article\Models\Article::with('author')->all();

foreach ($articles as $article) {
    $authorName = $article->author->name ?? 'NULL_RELATION';
    $authorId = $article->author_id;
    $relLoaded = $article->relationLoaded('author') ? 'YES' : 'NO';
    echo "Article: {$article->title}, author_id: {$authorId}, relation loaded: {$relLoaded}, author name: {$authorName}\n";
}
