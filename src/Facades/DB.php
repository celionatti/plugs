<?php

declare(strict_types=1);

namespace Plugs\Facades;

/*
|--------------------------------------------------------------------------
| DB Facade
|--------------------------------------------------------------------------
|
| Provides static access to the DatabaseManager instance.
|
| Usage:
|   use Plugs\Facades\DB;
|
|   DB::table('users')->where('id', 1)->get();
|   DB::fetch("SELECT * FROM users WHERE id = ?", [1]);
*/

use Plugs\Facade;

/**
 * @method static \Plugs\Database\QueryBuilder table(string $table, string|null $connection = null)
 * @method static \Plugs\Database\Connection getConnection()
 * @method static mixed query(string $sql, array $params = [])
 * @method static array|null fetch(string $sql, array $params = [])
 * @method static array fetchAll(string $sql, array $params = [])
 * @method static bool execute(string $sql, array $params = [])
 * @method static string lastInsertId()
 * @method static bool beginTransaction()
 * @method static bool commit()
 * @method static bool rollBack()
 *
 * @see \Plugs\Database\DatabaseManager
 */
class DB extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'db';
    }
}
