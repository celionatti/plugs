<?php

declare(strict_types=1);

namespace App\Controllers;

use Plugs\Base\Controller\Controller;
use Plugs\Security\Validator;
use Plugs\View\ErrorMessage;

class ArticleController extends Controller
{
    /**
     * Simplified validation using Validator class
     */
    private function validateArticleData($request, bool $isPublishing = false): ErrorMessage
    {
        $data = $request->getParsedBody();
        
        // Define validation rules
        $rules = [
            'title' => 'required|max:255',
            'content' => $isPublishing ? 'required|min:50' : 'nullable',
            'excerpt' => 'nullable|max:500',
            'seo_title' => 'nullable|max:60',
            'seo_description' => 'nullable|max:160',
            'seo_keywords' => 'nullable|max:255',
            'categories' => $isPublishing ? 'required|array' : 'nullable|array',
        ];
        
        // Custom error messages (optional)
        $messages = [
            'title.required' => 'Title is required',
            'title.max' => 'Title must not exceed 255 characters',
            'content.required' => 'Content is required',
            'content.min' => 'Content must be at least 50 characters',
            'excerpt.max' => 'Excerpt must not exceed 500 characters',
            'seo_title.max' => 'SEO title should not exceed 60 characters',
            'seo_description.max' => 'SEO description should not exceed 160 characters',
            'seo_keywords.max' => 'SEO keywords should not exceed 255 characters',
            'categories.required' => 'Please select a category for your article',
        ];
        
        // Custom attribute names (optional)
        $attributes = [
            'seo_title' => 'SEO Title',
            'seo_description' => 'SEO Description',
            'seo_keywords' => 'SEO Keywords',
        ];
        
        // Create validator and validate
        $validator = new Validator($data, $rules, $messages, $attributes);
        $validator->validate();
        
        // Return the ErrorMessage instance
        return $validator->errors();
    }
    
    /**
     * Example: Create article action
     */
    public function store($request, $response)
    {
        $errors = $this->validateArticleData($request, false);
        
        if ($errors->any()) {
            return $this->view->render('articles/create', [
                'errors' => $errors,
                'old' => $request->getParsedBody()
            ]);
        }
        
        // Save article...
        
        return $response->redirect('/articles');
    }
    
    /**
     * Example: Publish article action
     */
    public function publish($request, $response, $args)
    {
        $errors = $this->validateArticleData($request, true);
        
        if ($errors->any()) {
            return $this->view->render('articles/edit', [
                'errors' => $errors,
                'article' => $this->getArticle($args['id']),
                'old' => $request->getParsedBody()
            ]);
        }
        
        // Publish article...
        
        return $response->redirect('/articles/' . $args['id']);
    }
    
    /**
     * Alternative: Manual validation still possible
     */
    private function manualValidation($request): ErrorMessage
    {
        $data = $request->getParsedBody();
        $errors = new ErrorMessage();
        
        // You can still add errors manually when needed
        if (empty($data['title'])) {
            $errors->add('title', 'Title is required');
        }
        
        if (strlen($data['title'] ?? '') > 255) {
            $errors->add('title', 'Title is too long');
        }
        
        return $errors;
    }
    
    /**
     * Example: Complex validation with custom logic
     */
    private function validateWithCustomLogic($request): ErrorMessage
    {
        $data = $request->getParsedBody();
        
        // Use Validator for standard validation
        $validator = new Validator($data, [
            'title' => 'required|max:255',
            'slug' => 'required|alpha_dash',
            'content' => 'required',
        ]);
        
        $validator->validate();
        $errors = $validator->errors();
        
        // Add custom validation logic
        if (!empty($data['slug'])) {
            $existingArticle = $this->articleRepository->findBySlug($data['slug']);
            if ($existingArticle && $existingArticle->id !== ($data['id'] ?? null)) {
                $errors->add('slug', 'This slug is already in use');
            }
        }
        
        // Custom content validation
        if (!empty($data['content'])) {
            $strippedContent = strip_tags($data['content']);
            if (strlen($strippedContent) < 50) {
                $errors->add('content', 'Content must be at least 50 characters (excluding HTML tags)');
            }
        }
        
        return $errors;
    }
    
    /**
     * Example: Nested array validation
     */
    private function validateWithNestedData($request): ErrorMessage
    {
        $data = $request->getParsedBody();
        
        $validator = new Validator($data, [
            'title' => 'required|max:255',
            'tags' => 'array',
            'tags.*' => 'string|max:50',
            'images' => 'array',
            'images.*.url' => 'required|url',
            'images.*.alt' => 'required|max:100',
        ], [
            'tags.*.max' => 'Each tag must not exceed 50 characters',
            'images.*.url.required' => 'Image URL is required',
            'images.*.alt.required' => 'Image alt text is required',
        ]);
        
        $validator->validate();
        
        return $validator->errors();
    }
    
    /**
     * Example: Conditional validation
     */
    private function validateConditional($request): ErrorMessage
    {
        $data = $request->getParsedBody();
        
        $validator = new Validator($data, [
            'type' => 'required|in:post,page,product',
            'price' => 'required_if:type,product|numeric|min:0',
            'sku' => 'required_if:type,product|alpha_dash',
            'template' => 'required_if:type,page',
        ], [
            'price.required_if' => 'Price is required for products',
            'sku.required_if' => 'SKU is required for products',
            'template.required_if' => 'Template is required for pages',
        ]);
        
        $validator->validate();
        
        return $validator->errors();
    }
}