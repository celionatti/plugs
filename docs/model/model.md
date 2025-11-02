# Enhanced PlugModel - Complete Usage Guide

## 🚀 New Features Added

### 1. **Query Logging**

Track all database queries with execution time.

```php
// Enable logging
PlugModel::enableQueryLog();

// Run queries
$users = User::where('active', 1)->get();
$count = User::count();

// Get log
$log = PlugModel::getQueryLog();
foreach ($log as $entry) {
    echo "Query: {$entry['query']}\n";
    echo "Bindings: " . json_encode($entry['bindings']) . "\n";
    echo "Time: {$entry['time']}s\n";
    echo "Timestamp: {$entry['timestamp']}\n\n";
}

// Clear log
PlugModel::flushQueryLog();

// Disable logging
PlugModel::disableQueryLog();
```

### 2. **Transactions**

```php
// Simple transaction
User::transaction(function() {
    $user = User::create(['name' => 'John']);
    $user->posts()->create(['title' => 'First Post']);
    // Auto-commits if no exception
});

// Manual transaction control
User::beginTransaction();
try {
    $user = User::create(['name' => 'Jane']);
    $profile = Profile::create(['user_id' => $user->id]);
    User::commit();
} catch (Exception $e) {
    User::rollBack();
    throw $e;
}

// Nested transactions (uses savepoints)
User::beginTransaction();
    // Some operations
    User::beginTransaction(); // Nested
        // More operations
    User::commit(); // Releases savepoint
User::commit(); // Final commit

// Check transaction level
$level = User::transactionLevel(); // Returns depth
```

### 3. **Raw Queries**

```php
// Raw SELECT - returns Collection of models
$users = User::raw('SELECT * FROM users WHERE age > ?', [18]);

// Raw statement (INSERT/UPDATE/DELETE) - returns bool
$success = User::statement('UPDATE users SET active = 1 WHERE id = ?', [5]);

// Get scalar value
$count = User::scalar('SELECT COUNT(*) FROM users WHERE active = ?', [1]);
$avgAge = User::scalar('SELECT AVG(age) FROM users');
```

### 4. **Batch Operations**

```php
// Batch insert
User::insert([
    ['name' => 'John', 'email' => 'john@example.com'],
    ['name' => 'Jane', 'email' => 'jane@example.com'],
    ['name' => 'Bob', 'email' => 'bob@example.com']
]);

// Batch update
User::updateMany([
    ['id' => 1, 'name' => 'Updated John'],
    ['id' => 2, 'name' => 'Updated Jane']
]);

// Upsert (insert or update if exists)
User::upsert(
    [
        ['email' => 'john@example.com', 'name' => 'John', 'age' => 25],
        ['email' => 'jane@example.com', 'name' => 'Jane', 'age' => 30]
    ],
    ['email'], // Unique keys
    ['name', 'age'] // Columns to update if exists
);

// Chunk processing (memory efficient)
User::chunk(100, function($users, $page) {
    foreach ($users as $user) {
        // Process each user
        echo "Processing: {$user->name}\n";
    }
    // Return false to stop
});

// Process each record individually
User::where('active', 1)->each(function($user) {
    $user->update(['processed' => true]);
}, 500); // Chunk size
```

### 5. **Advanced Query Builder**

```php
// Select specific columns
$users = User::select('id', 'name', 'email')->get();

// Add more columns to select
$users = User::select('id', 'name')
    ->addSelect('email', 'created_at')
    ->get();

// Group by with having
$stats = User::select('role', 'COUNT(*) as count')
    ->groupBy('role')
    ->having('count', '>', 5)
    ->get();

// Conditional queries
$query = User::query()
    ->when($isAdmin, function($q) {
        return $q->where('role', 'admin');
    })
    ->unless($includeInactive, function($q) {
        return $q->where('active', 1);
    });

// Tap into query chain
$users = User::where('active', 1)
    ->tap(function($query) {
        // Log or debug
        error_log('Building query...');
    })
    ->get();
```

### 6. **Appended Attributes**

```php
class User extends PlugModel
{
    protected $appends = ['full_name', 'is_admin'];
    
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }
    
    public function getIsAdminAttribute()
    {
        return $this->role === 'admin';
    }
}

// These appear in toArray() and toJson()
$user = User::find(1);
$array = $user->toArray();
// ['id' => 1, 'first_name' => 'John', 'last_name' => 'Doe', 
//  'full_name' => 'John Doe', 'is_admin' => false]

// Dynamically append
$user->append('custom_attribute')->toArray();
```

## 📋 Complete Examples

### Example 1: E-commerce Order Processing

```php
// Enable query logging for debugging
PlugModel::enableQueryLog();

User::transaction(function() {
    // Create order
    $order = Order::create([
        'user_id' => 1,
        'total' => 0,
        'status' => 'pending'
    ]);
    
    // Batch insert order items
    $items = [
        ['order_id' => $order->id, 'product_id' => 1, 'quantity' => 2, 'price' => 29.99],
        ['order_id' => $order->id, 'product_id' => 2, 'quantity' => 1, 'price' => 49.99],
    ];
    OrderItem::insert($items);
    
    // Update order total
    $total = OrderItem::where('order_id', $order->id)->sum('price');
    $order->update(['total' => $total]);
    
    // Update product stock
    foreach ($items as $item) {
        Product::raw(
            'UPDATE products SET stock = stock - ? WHERE id = ?',
            [$item['quantity'], $item['product_id']]
        );
    }
});

// Review query log
$queries = PlugModel::getQueryLog();
echo "Total queries: " . count($queries) . "\n";
```

### Example 2: Bulk User Import with Progress

```php
$csvData = [...]; // Array of user data

// Chunk into batches of 1000
$batches = array_chunk($csvData, 1000);

foreach ($batches as $index => $batch) {
    User::transaction(function() use ($batch) {
        // Upsert users
        User::upsert(
            $batch,
            ['email'], // Unique key
            ['name', 'phone', 'updated_at'] // Update these fields
        );
    });
    
    echo "Processed batch " . ($index + 1) . "\n";
}
```

### Example 3: Reporting with Aggregates

```php
// Get statistics by department
$stats = Employee::select('department', 
        'COUNT(*) as employee_count',
        'AVG(salary) as avg_salary',
        'MAX(salary) as max_salary',
        'MIN(salary) as min_salary')
    ->groupBy('department')
    ->having('employee_count', '>', 10)
    ->orderByDesc('avg_salary')
    ->get();

foreach ($stats as $stat) {
    echo "Department: {$stat->department}\n";
    echo "Employees: {$stat->employee_count}\n";
    echo "Avg Salary: \${$stat->avg_salary}\n\n";
}
```

### Example 4: Complex Filtering

```php
class User extends PlugModel
{
    // Define scope
    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }
    
    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }
    
    public function scopeRole($query, $role)
    {
        return $query->where('role', $role);
    }
}

// Use scopes with conditional logic
$users = User::active()
    ->verified()
    ->when($filterByRole, function($q) use ($role) {
        return $q->role($role);
    })
    ->when($sortBy === 'name', function($q) {
        return $q->orderBy('name');
    })
    ->unless($includeAll, function($q) {
        return $q->limit(50);
    })
    ->get();
```

### Example 5: Efficient Data Processing

```php
// Process millions of records without memory issues
User::where('status', 'pending')
    ->each(function($user) {
        // Send email
        sendWelcomeEmail($user->email);
        
        // Update status
        $user->update(['status' => 'processed']);
        
        // Log
        error_log("Processed user: {$user->id}");
    }, 100); // Process 100 at a time
```

## 🎯 Performance Tips

### 1. **Use Chunking for Large Datasets**

```php
// BAD - Loads all into memory
$users = User::all();
foreach ($users as $user) {
    // process
}

// GOOD - Processes in chunks
User::chunk(1000, function($users) {
    foreach ($users as $user) {
        // process
    }
});
```

### 2. **Use Batch Operations**

```php
// BAD - Multiple queries
foreach ($data as $item) {
    User::create($item);
}

// GOOD - Single query
User::insert($data);
```

### 3. **Select Only Needed Columns**

```php
// BAD - Selects all columns
$users = User::all();

// GOOD - Selects specific columns
$users = User::select('id', 'name', 'email')->get();
```

### 4. **Use Transactions for Multiple Operations**

```php
// Ensures data consistency and can improve performance
User::transaction(function() {
    // Multiple operations
});
```

## 🔍 Debugging

```php
// Enable query logging
PlugModel::enableQueryLog();

// Run your queries
$users = User::where('active', 1)->get();

// Check what was executed
$log = PlugModel::getQueryLog();
print_r($log);

// Output:
// [
//     [
//         'query' => 'SELECT * FROM users WHERE active = ?',
//         'bindings' => [1],
//         'time' => 0.00234,
//         'timestamp' => '2025-11-02 10:30:45'
//     ]
// ]
```

## ⚠️ Important Notes

1. **Nested Transactions**: Only MySQL 5.5+ and PostgreSQL support savepoints
2. **Upsert**: Uses MySQL's `ON DUPLICATE KEY UPDATE` syntax
3. **Query Logging**: Disable in production for better performance
4. **Chunking**: Processes records in batches to avoid memory issues
5. **Raw Queries**: Be careful with SQL injection - always use bindings

## 🧪 Testing Your Code

```php
// Test transaction rollback
try {
    User::transaction(function() {
        User::create(['name' => 'Test']);
        throw new Exception('Rollback test');
    });
} catch (Exception $e) {
    // User should not be created
    assert(User::where('name', 'Test')->doesntExist());
}

// Test query logging
PlugModel::enableQueryLog();
User::find(1);
$log = PlugModel::getQueryLog();
assert(count($log) === 1);
assert(strpos($log[0]['query'], 'SELECT') !== false);
```