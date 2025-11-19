<?php

declare(strict_types=1);

namespace App\Controllers;

use Plugs\Base\Controller\Controller;
use Plugs\View\ErrorBag;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CategoryController extends Controller
{
    /**
     * Show the create category form
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        // $errors is automatically available globally in all views
        // No need to pass it manually!
        return $this->view('categories.create');
    }

    /**
     * Store a new category
     */
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        // Validate the request
        $errors = $this->validateCategory($request);

        // If validation fails, redirect back with errors
        if ($errors->any()) {
            return $this->back($errors);
        }

        // Get validated data
        $data = $request->getParsedBody();

        // Save to database
        $this->db->table('categories')->insert([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Redirect with success message
        return $this->redirectWithSuccess('/categories', 'Category created successfully!');
    }

    /**
     * Show the edit category form
     */
    public function edit(ServerRequestInterface $request): ResponseInterface
    {
        $id = $this->param($request, 'id');
        
        // Get category from database
        $category = $this->db->table('categories')->find($id);

        if (!$category) {
            return $this->redirectWithError('/categories', 'Category not found');
        }

        // $errors is automatically available globally
        // old() values are also automatically available
        return $this->view('categories.edit', [
            'category' => $category
        ]);
    }

    /**
     * Update an existing category
     */
    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $id = $this->param($request, 'id');

        // Validate the request
        $errors = $this->validateCategory($request, $id);

        // If validation fails, redirect back with errors and old input
        if ($errors->any()) {
            return $this->back($errors);
        }

        // Get validated data
        $data = $request->getParsedBody();

        // Update in database
        $updated = $this->db->table('categories')->where('id', $id)->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        if (!$updated) {
            return $this->redirectWithError("/categories/{$id}/edit", 'Failed to update category');
        }

        // Redirect with success message
        return $this->redirectWithSuccess('/categories', 'Category updated successfully!');
    }

    /**
     * Delete a category
     */
    public function destroy(ServerRequestInterface $request): ResponseInterface
    {
        $id = $this->param($request, 'id');

        $deleted = $this->db->table('categories')->where('id', $id)->delete();

        if (!$deleted) {
            return $this->redirectWithError('/categories', 'Failed to delete category');
        }

        return $this->redirectWithSuccess('/categories', 'Category deleted successfully!');
    }

    /**
     * Validate category data
     */
    private function validateCategory(ServerRequestInterface $request, ?int $id = null): ErrorBag
    {
        $data = $request->getParsedBody();
        $errors = new ErrorBag();

        // Name validation
        if (empty($data['name'])) {
            $errors->add('name', 'Category name is required');
        } elseif (strlen($data['name']) < 3) {
            $errors->add('name', 'Category name must be at least 3 characters');
        } elseif (strlen($data['name']) > 100) {
            $errors->add('name', 'Category name must not exceed 100 characters');
        }

        // Slug validation
        if (empty($data['slug'])) {
            $errors->add('slug', 'Slug is required');
        } elseif (!preg_match('/^[a-z0-9-]+$/', $data['slug'])) {
            $errors->add('slug', 'Slug can only contain lowercase letters, numbers, and hyphens');
        } else {
            // Check if slug is unique
            $query = $this->db->table('categories')->where('slug', $data['slug']);
            
            // Exclude current category when updating
            if ($id) {
                $query->where('id', '!=', $id);
            }
            
            if ($query->exists()) {
                $errors->add('slug', 'This slug is already in use');
            }
        }

        // Description validation (optional)
        if (!empty($data['description']) && strlen($data['description']) > 500) {
            $errors->add('description', 'Description must not exceed 500 characters');
        }

        return $errors;
    }
}