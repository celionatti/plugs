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

$users = DB::table('users')
                ->skip(10)
                ->take(5)
                ->get();
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
