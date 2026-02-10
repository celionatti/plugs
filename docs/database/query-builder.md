# Database: Query Builder

The Plugs query builder provides a fluent, convenient interface to creating and running database queries. It can be used to perform most database operations in your application and works perfectly with all supported database systems.

## Retrieving Results

### Retrieving All Rows From A Table

```php
use Plugs\Facades\DB;

$users = DB::table('users')->get();

foreach ($users as $user) {
    echo $user->name;
}
```

### Retrieving A Single Row / Column From A Table

```php
$user = DB::table('users')->where('name', 'John')->first();

echo $user->email;

### Retrieving Results Or Throwing Exceptions

If you would like to retrieve a record or throw an exception if none is found, use the `findOrFail` or `firstOrFail` methods:

```php
$user = DB::table('users')->where('active', true)->firstOrFail();

$user = DB::table('users')->findOrFail(1);
```

### Retrieving Multiple Records By ID

```php
$users = DB::table('users')->findMany([1, 2, 3]);
```
```

## Aggregates

The query builder also provides a variety of methods for retrieving aggregate values like `count`, `max`, `min`, `avg`, and `sum`.

```php
$users = DB::table('users')->count();

$price = DB::table('orders')->max('price');
```

## Select Statements

```php
$users = DB::table('users')
            ->select('name', 'email as user_email')
            ->get();
```

## Where Clauses

### Basic Where Clauses

```php
$users = DB::table('users')
                ->where('votes', '=', 100)
                ->where('age', '>', 35)
                ->get();
```

### Or Where Clauses

```php
$users = DB::table('users')
                    ->where('votes', '>', 100)
                    ->orWhere('name', 'John')
                    ->get();
```

### Null Where Clauses

The `whereNull` method verifies that the value of the given column is `NULL`:

```php
$users = DB::table('users')
                ->whereNull('updated_at')
                ->get();
```

The `whereNotNull` method verifies that the column's value is not `NULL`:

```php
$users = DB::table('users')
                ->whereNotNull('updated_at')
                ->get();
```

### Additional Where Clauses

- `whereBetween` / `whereNotBetween`
- `whereIn` / `whereNotIn`
- `whereNull` / `whereNotNull`
- `whereDate` / `whereMonth` / `whereDay` / `whereYear`

## Joins

### Inner Join Clause

The query builder may also be used to add join clauses to your queries. To perform a basic "inner join", you may use the `join` method on a query builder instance. The first argument passed to the `join` method is the name of the table you need to join to, while the remaining arguments specify the column constraints for the join:

```php
$users = DB::table('users')
            ->join('contacts', 'users.id', '=', 'contacts.user_id')
            ->select('users.*', 'contacts.phone')
            ->get();
```

### Left Join / Right Join Clause

If you would like to perform a "left join" or "right join" instead of an "inner join", use the `leftJoin` or `rightJoin` methods:

```php
$users = DB::table('users')
            ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
            ->get();

$users = DB::table('users')
            ->rightJoin('posts', 'users.id', '=', 'posts.user_id')
            ->get();
```

## Ordering, Grouping, Limit & Offset

```php
$users = DB::table('users')
                ->orderBy('name', 'desc')
                ->get();

$user = DB::table('users')
                ->latest()
                ->first();

$user = DB::table('users')
                ->oldest()
                ->first();

$users = DB::table('users')
                ->skip(10)
                ->take(5)
                ->get();
```

## Chunking Results

If you need to work with thousands of database records, consider using the `chunk` method. This method retrieves a small chunk of the results at a time and feeds each chunk into a `Closure` for processing. This method is very useful for writing console commands that process thousands of records. For example, let's work with the entire `users` table in chunks of 100 records at a time:

```php
DB::table('users')->orderBy('id')->chunk(100, function ($users) {
    foreach ($users as $user) {
        //
    }
});
```

You may stop further chunks from being processed by returning `false` from the `Closure`:

```php
DB::table('users')->orderBy('id')->chunk(100, function ($users) {
    // Process the records...

    return false;
});
```

### Chunking By ID

If you are updating database records while chunking results, your chunk results could change in unexpected ways. So, when updating records while chunking, it is always best to use the `chunkById` method. This method will automatically paginate the results based on the record's primary key:

```php
DB::table('users')->where('active', false)
    ->chunkById(100, function ($users) {
        foreach ($users as $user) {
            DB::table('users')
                ->where('id', $user->id)
                ->update(['active' => true]);
        }
    });
```

## Inserts, Updates, Deletes

### Insert

```php
DB::table('users')->insert([
    'email' => 'kayla@example.com',
    'votes' => 0
]);
```

### Update

```php
DB::table('users')
    ->where('id', 1)
    ->update(['votes' => 1]);
```

### Delete

```php
DB::table('users')->where('votes', '>', 100)->delete();
```

## Relationship Queries (whereHas)

Query records based on the existence of related records:

```php
// Get all posts that have at least one comment
$posts = Post::query()
    ->whereHas('comments')
    ->get();

// Get all posts with approved comments
$posts = Post::query()
    ->whereHas('comments', function($query) {
        $query->where('approved', true);
    })
    ->get();

// With OR condition
$posts = Post::query()
    ->whereHas('comments')
    ->orWhereHas('likes')
    ->get();
```

## Raw Expressions

For complex queries requiring raw SQL:

```php
use Plugs\Database\Raw;

// Column-to-column comparison
$users = DB::table('users')
    ->where('views', '>', new Raw('likes * 2'))
    ->get();

// In calculations
$users = DB::table('users')
    ->where('balance', '>', new Raw('credit_limit - used_credit'))
    ->get();
```

## Query Filtering

For advanced filtering from request parameters, see the [Query Filtering](query-filtering.md) documentation.

## API Response Helpers

The query builder provides several methods to return standardized API responses directly from the query:

```php
// Standardized paginated response
return DB::table('posts')->paginateResponse(15);

// Standardized single record response
return DB::table('posts')->where('slug', $slug)->firstResponse();

// Standardized list response
return DB::table('posts')->where('status', 'active')->allResponse();
```

## Pagination

For details on paginating query builder results, see the [Pagination documentation](pagination.md).
