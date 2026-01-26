# Database: Getting Started

The Plugs framework makes interacting with databases extremely simple across various database backends using the Query Builder and the PlugModel ORM.

## Configuration

The database configuration for your application is located in `config/database.php`. In this file, you may define all of your database connections, as well as specify which connection should be used by default.

```php
return [
    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'plugs'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
        // ...
    ],
];
```

## Running Raw SQL Queries

Once you have configured your database connection, you may run queries using the `DB` facade.

### Select Queries

```php
use Plugs\Facades\DB;

$users = DB::select('select * from users where active = ?', [1]);

foreach ($users as $user) {
    echo $user->name;
}
```

### Insert Queries

```php
DB::insert('insert into users (id, name) values (?, ?)', [1, 'Marc']);
```

### Update Queries

```php
$affected = DB::update('update users set votes = 100 where name = ?', ['John']);
```

### Delete Queries

```php
$deleted = DB::delete('delete from users');
```

## Database Transactions

You may use the `transaction` method on the `DB` facade to run a set of operations within a database transaction:

```php
DB::transaction(function () {
    DB::update('update users set votes = 1');
    DB::delete('delete from posts');
});
```

> [!TIP]
> If an exception is thrown within the transaction closure, the transaction will automatically be rolled back and the exception will be re-thrown.

## Next Steps

Now that you understand the basics of interacting with your database, you may want to learn more about:

- [Database Migrations](file:///docs/database/migrations.md)
- [Fluent Query Builder](file:///docs/database/query-builder.md)
- [PlugModel ORM](file:///docs/database/models.md)
