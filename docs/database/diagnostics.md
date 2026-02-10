# Model Health & Diagnostics

Automated self-diagnostics help maintain data integrity and database health by detecting orphaned records and missing optimizations.

## Getting Started

Enable diagnostics on your model using the `#[Diagnostic]` attribute.

```php
use Plugs\Database\Attributes\Diagnostic;

#[Diagnostic(checks: ['orphans', 'indexes', 'integrity'])]
class User extends PlugModel {
    // ...
}
```

## Standard Checks

### 1. Orphaned Relations (`orphans`)
Scans active `BelongsTo` relationships to ensure foreign keys point to valid records in the owner table.

### 2. Missing Indexes (`indexes`)
Inspects the database schema to verify that foreign key columns defined in model relationships have corresponding database indexes.

### 3. Custom Integrity (`integrity`)
Runs custom logic defined in the `validateHealth()` method of your model.

```php
public function validateHealth(): array {
    $issues = [];
    if ($this->email && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
        $issues[] = "Invalid email format in database: {$this->email}";
    }
    return $issues;
}
```

## Running Diagnostics

You can run diagnostics manually on an instance or use the `DiagnosticsManager`.

```php
// Single model
$issues = $user->checkHealth();

// System-wide via Manager
use Plugs\Database\Observability\DiagnosticsManager;

$manager = DiagnosticsManager::getInstance();
$report = $manager->checkMultiple([User::class, Post::class, Comment::class]);
$summary = $manager->getSummary($report);

echo "Total Issues Found: " . $summary['total_issues'];
```

## Performance Note

Plugs implements **Schema Caching** for diagnostic inspections. The first time a table's indexes are checked, the results are cached for the duration of the request, significantly reducing the overhead of running diagnostics across large collections.
