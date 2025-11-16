# Plugs Framework Debug Integration Guide

## 🚀 Quick Start

### 1. Add Debug Functions to Your Project

Create a new file: `app/Helpers/debug.php`

```php
<?php
// Copy the entire content from the "Plugs Framework Debug Utility (Optimized)" artifact
require_once __DIR__ . '/../../vendor/plugs/debug.php';
```

### 2. Add Debuggable Trait to Base Model

Edit your `PlugModel.php` and add the trait at the top of the class:

```php
<?php

namespace Plugs\Base\Model;

use Plugs\Base\Model\Debuggable; // Add this

abstract class PlugModel
{
    use Debuggable; // Add this line
    
    // ... rest of your existing code
}
```

### 3. Auto-load Debug Functions

Add to your `composer.json`:

```json
{
    "autoload": {
        "files": [
            "app/Helpers/debug.php"
        ]
    }
}
```

Then run: `composer dump-autoload`

## 📋 Features Overview

### ✅ What's Included

1. **Beautiful Laravel 12-style UI** with blue gradient theme
2. **Query Performance Tracking** - See all executed queries with timing
3. **N+1 Detection** - Automatically detects N+1 query problems
4. **Memory Monitoring** - Track memory usage per variable/query
5. **Slow Query Detection** - Highlight queries that exceed threshold
6. **Model Debugging** - Dump models with their relationships
7. **Performance Profiling** - Profile specific operations
8. **Clean Display** - Variables displayed in readable grid format

## 🎯 Usage Examples

### Basic Debugging

```php
// Dump and die
$users = User::all();
dd($users);

// Dump without dying
d($user);

// Multiple variables
dd($users, $posts, $comments);
```

### Query Debugging

```php
// Enable query logging
User::enableQueryLog();

$users = User::with('posts')->get();

// Show all queries
dq(); // Dies and shows queries

// Or without dying
dq(false);
```

### Model Debugging

```php
// Dump model with related queries
$user = User::find(1);
dm($user);

// Get performance analysis
$analysis = User::analyzePerformance();
dd($analysis);
```

### Performance Profiling

```php
$result = User::profile(function() {
    return User::with('posts', 'comments')->get();
});

dd($result);
// Shows: execution_time, memory_used, queries, etc.
```

## 🔧 Configuration

### Set Slow Query Threshold

```php
// Queries slower than 100ms will be flagged
User::setSlowQueryThreshold(0.1);
```

### Enable Debug Mode

```php
// Enable for all queries
User::enableDebug();

// Your code...

// Disable when done
User::disableDebug();
```

### Environment-Based Debugging

```php
// Only enable in development
if (env('APP_ENV') === 'local') {
    User::enableDebug();
}
```

## 🎨 UI Features

### Clean Variable Display

- Variables displayed in collapsible cards
- Syntax highlighting for different data types
- Grid layout for easy scanning
- Proper indentation for nested structures
- Character limit for long strings (with "...")

### Query Performance Table

- Query execution order
- SQL with bindings
- Execution time with color coding:
  - 🟢 Green: < 10ms (fast)
  - 🟡 Yellow: 10-50ms (normal)
  - 🔴 Red: > 50ms (slow)
- Timestamp for each query

### Performance Alerts

- ✅ Good: < 10 queries
- ⚠️ Warning: 10-20 queries
- ❌ Critical: > 20 queries
- N+1 detection with recommendation

## 🐛 Debugging N+1 Problems

### Before (N+1 Problem)

```php
User::enableDebug();

$users = User::all(); // 1 query
foreach ($users as $user) {
    echo $user->posts->count(); // N queries!
}

dq(); // Shows N+1 warning
```

### After (Fixed)

```php
User::enableDebug();

$users = User::with('posts')->get(); // 2 queries only!
foreach ($users as $user) {
    echo $user->posts->count(); // No extra queries
}

dq(); // Shows "Good Performance" ✅
```

## 📊 Performance Analysis

```php
User::enableDebug();

// Your operations
$users = User::with('posts', 'comments')->get();

// Get detailed analysis
$analysis = User::analyzePerformance();

/*
Returns:
[
    'status' => 'good|warning|critical',
    'recommendations' => [
        'Use eager loading...',
        'Add indexes...',
    ],
    'stats' => [
        'total_queries' => 5,
        'total_time' => 0.045,
        'slow_queries' => 0,
    ]
]
*/
```

## 🔍 Debugging Best Practices

### ✅ DO

- Enable query logging only when debugging
- Use `with()` for eager loading relationships
- Check `analyzePerformance()` before production
- Clear query log between tests: `User::flushQueryLog()`
- Use `dm()` for model-specific debugging

### ❌ DON'T

- Leave query logging enabled in production
- Access relationships in loops without eager loading
- Ignore N+1 warnings
- Dump large datasets without pagination
- Enable debug for all users in production

## 🚨 Production Considerations

### Safe Production Debugging

```php
// Only for admins
if (auth()->check() && auth()->user()->is_admin) {
    User::enableDebug();
}

// Log to file instead of displaying
User::enableQueryLog();
$users = User::all();
$queries = User::getQueryLog();

file_put_contents(
    storage_path('logs/queries.log'),
    json_encode($queries, JSON_PRETTY_PRINT),
    FILE_APPEND
);
```

## 📱 Mobile Responsive

The debug UI is fully responsive and works on mobile devices:

- Collapsible sections
- Scrollable code blocks
- Touch-friendly buttons
- Readable font sizes

## 🎨 Customization

### Change Theme Colors

Edit the CSS in `plugs_render_styles()`:

```css
/* Primary gradient */
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);

/* Change to your colors */
background: linear-gradient(135deg, #your-color-1 0%, #your-color-2 100%);
```

### Adjust Display Limits

```php
// In plugs_format_value() function
if ($count > 50) { // Change this number
    // Limit displayed items
}
```

## 🔧 Troubleshooting

### Issue: Functions not found

**Solution**: Make sure `composer dump-autoload` was run after adding the file.

### Issue: Queries not logging

**Solution**: Call `User::enableQueryLog()` before your operations.

### Issue: N+1 not detected

**Solution**: Ensure threshold is met (>5 similar queries by default).

### Issue: Memory errors with large datasets

**Solution**: Use pagination or chunking for large datasets.

## 📚 Additional Resources

- Check `debug_usage_example.php` for more examples
- See `Debuggable` trait for advanced features
- Review `PlugModel` integration points

## 🎉 Summary

Your debug tools now include:

- ✅ Beautiful Laravel 12-inspired UI
- ✅ Automatic query tracking
- ✅ N+1 detection
- ✅ Performance analysis
- ✅ Memory monitoring
- ✅ Slow query alerts
- ✅ Model-specific debugging
- ✅ Production-safe options

Happy debugging! 🐛🔍
