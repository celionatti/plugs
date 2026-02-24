# AI Production Examples

Here are common production use cases for the Plugs AI integration.

## 1. Automated Content Moderation

Use AI validation to flag or provide feedback on user-generated content.

```php
// In a Controller
public function storeComment(Request $request)
{
    $this->validate($request, [
        'content' => 'required|min:10|ai:check if this comment is respectful and constructive'
    ]);

    Comment::create($request->all());
    return back()->withSuccess('Comment posted!');
}
```

## 2. Automatic Metadata & SEO Generation

Leverage AI in models to automatically populate SEO fields or tags.

```php
// In an Article model
protected static function boot()
{
    parent::boot();

    static::creating(function ($article) {
        // Automatically generate an SEO description based on the body
        $article->generate('seo_description', 'Write a SEO-optimized meta description for this article.');

        // Predict tags from title and content
        $article->tags = $article->predict(['tags'])['tags'] ?? '';
    });
}
```

## 3. Intelligent Search & Recommendations

Use the `AIManager` to interpret user intent in search queries.

```php
public function search(Request $request)
{
    $query = $request->input('q');

    // Use AI to extract product attributes from a natural language query
    $intent = ai()->prompt("Extract JSON with: color, category, price_range from: {$query}. Return ONLY JSON.");
    $params = json_decode($intent, true);

    $products = Product::query()
        ->when($params['category'], fn($q) => $q->where('category', $params['category']))
        ->when($params['color'], fn($q) => $q->where('color', $params['color']))
        ->get();

    return view('search.results', compact('products'));
}
```

## 4. Automatic Support Ticket Classification

Classify incoming support requests to route them to the correct department.

```php
public function handleSupport(Request $request)
{
    $department = $this->aiClassify($request->input('message'), [
        'Billing', 'Technical Support', 'Feature Requests', 'Security'
    ]);

    $ticket = Ticket::create($request->all());
    $ticket->department = $department;
    $ticket->save();

    return response()->json(['message' => 'Ticket assigned to ' . $department]);
}
```

## 5. Zero-Latency Admin Dashboard

Use SWR to ensure your dashboard stays fast even with heavy AI content.

```php
public function index()
{
    // These calls return instantly from cache (0ms)
    // Refreshes happen in the background after page load
    $headlines = ai()->prompt('news_headlines', [], ['swr' => true]);
    $marketAnalysis = ai()->prompt('market_summary', [], ['swr' => true]);

    return view('admin.dashboard', compact('headlines', 'marketAnalysis'));
}
```

## 6. Non-Blocking Page Processing

Use `defer()` to run AI calls in parallel with expensive database operations.

```php
public function show($id)
{
    // Start AI request (non-blocking)
    $recommendations = ai()->defer()->prompt("Recommend similar products to ID: {$id}");

    // AI is running in the background while we fetch data from DB
    $product = Product::findOrFail($id);
    $related = Product::where('category', $product->category)->limit(5)->get();

    // AI request is only "awaited" when the view renders $recommendations
    return view('products.show', compact('product', 'related', 'recommendations'));
}
```
