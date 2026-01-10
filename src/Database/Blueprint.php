<?php

declare(strict_types=1);

namespace Plugs\Database;

/*
|--------------------------------------------------------------------------
| Blueprint Class
|--------------------------------------------------------------------------
|
| This class provides a fluent interface for defining table schemas.
| It supports creating columns, indexes, foreign keys, and more with
| a Laravel-like syntax.
*/

class Blueprint
{
    private $connection;
    private $table;
    private $commands = [];
    private $columns = [];
    private $engine = 'InnoDB';
    private $charset = 'utf8mb4';
    private $collation = 'utf8mb4_unicode_ci';
    private $temporary = false;

    public function __construct(Connection $connection, string $table)
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    // ==================== COLUMN TYPES ====================

    /**
     * Create an auto-incrementing integer (primary key) column
     */
    public function id(string $column = 'id'): ColumnDefinition
    {
        return $this->bigIncrements($column);
    }

    /**
     * Create an auto-incrementing BIGINT column
     */
    public function bigIncrements(string $column): ColumnDefinition
    {
        $def = new ColumnDefinition($column, 'BIGINT');
        $def->unsigned()->autoIncrement()->primary();
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create an auto-incrementing INT column
     */
    public function increments(string $column): ColumnDefinition
    {
        $def = new ColumnDefinition($column, 'INT');
        $def->unsigned()->autoIncrement()->primary();
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create a VARCHAR column
     */
    public function string(string $column, int $length = 255): ColumnDefinition
    {
        $def = new ColumnDefinition($column, "VARCHAR({$length})");
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create a CHAR column
     */
    public function char(string $column, int $length = 255): ColumnDefinition
    {
        $def = new ColumnDefinition($column, "CHAR({$length})");
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create a TEXT column
     */
    public function text(string $column): ColumnDefinition
    {
        $def = new ColumnDefinition($column, 'TEXT');
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create a MEDIUMTEXT column
     */
    public function mediumText(string $column): ColumnDefinition
    {
        $def = new ColumnDefinition($column, 'MEDIUMTEXT');
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create a LONGTEXT column
     */
    public function longText(string $column): ColumnDefinition
    {
        $def = new ColumnDefinition($column, 'LONGTEXT');
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create an INT column
     */
    public function integer(string $column): ColumnDefinition
    {
        $def = new ColumnDefinition($column, 'INT');
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create a TINYINT column
     */
    public function tinyInteger(string $column): ColumnDefinition
    {
        $def = new ColumnDefinition($column, 'TINYINT');
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create a SMALLINT column
     */
    public function smallInteger(string $column): ColumnDefinition
    {
        $def = new ColumnDefinition($column, 'SMALLINT');
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create a MEDIUMINT column
     */
    public function mediumInteger(string $column): ColumnDefinition
    {
        $def = new ColumnDefinition($column, 'MEDIUMINT');
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create a BIGINT column
     */
    public function bigInteger(string $column): ColumnDefinition
    {
        $def = new ColumnDefinition($column, 'BIGINT');
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create an unsigned BIGINT column (typically for foreign keys)
     */
    public function unsignedBigInteger(string $column): ColumnDefinition
    {
        $def = new ColumnDefinition($column, 'BIGINT');
        $def->unsigned();
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create an unsigned INT column
     */
    public function unsignedInteger(string $column): ColumnDefinition
    {
        $def = new ColumnDefinition($column, 'INT');
        $def->unsigned();
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create a FLOAT column
     */
    public function float(string $column, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        $def = new ColumnDefinition($column, "FLOAT({$precision}, {$scale})");
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create a DOUBLE column
     */
    public function double(string $column, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        $def = new ColumnDefinition($column, "DOUBLE({$precision}, {$scale})");
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create a DECIMAL column
     */
    public function decimal(string $column, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        $def = new ColumnDefinition($column, "DECIMAL({$precision}, {$scale})");
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create a BOOLEAN (TINYINT(1)) column
     */
    public function boolean(string $column): ColumnDefinition
    {
        $def = new ColumnDefinition($column, 'TINYINT(1)');
        $def->default(0);
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create an ENUM column
     */
    public function enum(string $column, array $allowed): ColumnDefinition
    {
        $values = implode("','", $allowed);
        $def = new ColumnDefinition($column, "ENUM('{$values}')");
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create a SET column
     */
    public function set(string $column, array $allowed): ColumnDefinition
    {
        $values = implode("','", $allowed);
        $def = new ColumnDefinition($column, "SET('{$values}')");
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create a JSON column
     */
    public function json(string $column): ColumnDefinition
    {
        $def = new ColumnDefinition($column, 'JSON');
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create a JSONB column (alias for JSON in MySQL)
     */
    public function jsonb(string $column): ColumnDefinition
    {
        return $this->json($column);
    }

    /**
     * Create a DATE column
     */
    public function date(string $column): ColumnDefinition
    {
        $def = new ColumnDefinition($column, 'DATE');
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create a DATETIME column
     */
    public function dateTime(string $column, int $precision = 0): ColumnDefinition
    {
        $type = $precision > 0 ? "DATETIME({$precision})" : 'DATETIME';
        $def = new ColumnDefinition($column, $type);
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create a DATETIME column with timezone (same as dateTime in MySQL)
     */
    public function dateTimeTz(string $column, int $precision = 0): ColumnDefinition
    {
        return $this->dateTime($column, $precision);
    }

    /**
     * Create a TIME column
     */
    public function time(string $column, int $precision = 0): ColumnDefinition
    {
        $type = $precision > 0 ? "TIME({$precision})" : 'TIME';
        $def = new ColumnDefinition($column, $type);
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create a TIMESTAMP column
     */
    public function timestamp(string $column, int $precision = 0): ColumnDefinition
    {
        $type = $precision > 0 ? "TIMESTAMP({$precision})" : 'TIMESTAMP';
        $def = new ColumnDefinition($column, $type);
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create a TIMESTAMP column with timezone (same as timestamp in MySQL)
     */
    public function timestampTz(string $column, int $precision = 0): ColumnDefinition
    {
        return $this->timestamp($column, $precision);
    }

    /**
     * Create created_at and updated_at timestamp columns
     */
    public function timestamps(int $precision = 0): void
    {
        $this->timestamp('created_at', $precision)->nullable()->useCurrent();
        $this->timestamp('updated_at', $precision)->nullable()->useCurrent()->useCurrentOnUpdate();
    }

    /**
     * Create created_at and updated_at nullable timestamp columns
     */
    public function nullableTimestamps(int $precision = 0): void
    {
        $this->timestamp('created_at', $precision)->nullable();
        $this->timestamp('updated_at', $precision)->nullable();
    }

    /**
     * Create a deleted_at timestamp column for soft deletes
     */
    public function softDeletes(string $column = 'deleted_at', int $precision = 0): ColumnDefinition
    {
        return $this->timestamp($column, $precision)->nullable();
    }

    /**
     * Create a BINARY column
     */
    public function binary(string $column): ColumnDefinition
    {
        $def = new ColumnDefinition($column, 'BLOB');
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create a UUID column
     */
    public function uuid(string $column): ColumnDefinition
    {
        $def = new ColumnDefinition($column, 'CHAR(36)');
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create an IP address column
     */
    public function ipAddress(string $column): ColumnDefinition
    {
        $def = new ColumnDefinition($column, 'VARCHAR(45)');
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create a MAC address column
     */
    public function macAddress(string $column): ColumnDefinition
    {
        $def = new ColumnDefinition($column, 'VARCHAR(17)');
        $this->columns[] = $def;
        return $def;
    }

    /**
     * Create a YEAR column
     */
    public function year(string $column): ColumnDefinition
    {
        $def = new ColumnDefinition($column, 'YEAR');
        $this->columns[] = $def;
        return $def;
    }

    // ==================== COLUMN MODIFIERS (Global) ====================

    /**
     * Add a comment to the table
     */
    public function comment(string $comment): self
    {
        $this->commands[] = ['type' => 'tableComment', 'comment' => $comment];
        return $this;
    }

    /**
     * Set the storage engine
     */
    public function engine(string $engine): self
    {
        $this->engine = $engine;
        return $this;
    }

    /**
     * Set the character set
     */
    public function charset(string $charset): self
    {
        $this->charset = $charset;
        return $this;
    }

    /**
     * Set the collation
     */
    public function collation(string $collation): self
    {
        $this->collation = $collation;
        return $this;
    }

    /**
     * Create a temporary table
     */
    public function temporary(): self
    {
        $this->temporary = true;
        return $this;
    }

    // ==================== INDEXES ====================

    /**
     * Add a primary key
     */
    public function primary(string|array $columns, ?string $name = null): self
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name = $name ?? 'PRIMARY';
        $this->commands[] = [
            'type' => 'primary',
            'columns' => $columns,
            'name' => $name
        ];
        return $this;
    }

    /**
     * Add a unique index
     */
    public function unique(string|array $columns, ?string $name = null): self
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name = $name ?? $this->createIndexName('unique', $columns);
        $this->commands[] = [
            'type' => 'unique',
            'columns' => $columns,
            'name' => $name
        ];
        return $this;
    }

    /**
     * Add an index
     */
    public function index(string|array $columns, ?string $name = null): self
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name = $name ?? $this->createIndexName('index', $columns);
        $this->commands[] = [
            'type' => 'index',
            'columns' => $columns,
            'name' => $name
        ];
        return $this;
    }

    /**
     * Add a fulltext index
     */
    public function fullText(string|array $columns, ?string $name = null): self
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name = $name ?? $this->createIndexName('fulltext', $columns);
        $this->commands[] = [
            'type' => 'fulltext',
            'columns' => $columns,
            'name' => $name
        ];
        return $this;
    }

    /**
     * Add a spatial index
     */
    public function spatialIndex(string|array $columns, ?string $name = null): self
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name = $name ?? $this->createIndexName('spatial', $columns);
        $this->commands[] = [
            'type' => 'spatial',
            'columns' => $columns,
            'name' => $name
        ];
        return $this;
    }

    /**
     * Create an index name
     */
    private function createIndexName(string $type, array $columns): string
    {
        $index = strtolower($this->table . '_' . implode('_', $columns) . '_' . $type);
        return str_replace(['-', '.'], '_', $index);
    }

    // ==================== FOREIGN KEYS ====================

    /**
     * Add a foreign key constraint
     */
    public function foreign(string|array $columns, ?string $name = null): ForeignKeyDefinition
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name = $name ?? $this->createIndexName('foreign', $columns);
        
        $foreign = new ForeignKeyDefinition($columns, $name);
        $this->commands[] = ['type' => 'foreign', 'definition' => $foreign];
        
        return $foreign;
    }

    /**
     * Drop a foreign key constraint
     */
    public function dropForeign(string|array $index): self
    {
        $index = is_array($index) ? $index : [$index];
        $this->commands[] = ['type' => 'dropForeign', 'index' => $index];
        return $this;
    }

    // ==================== TABLE ALTERATIONS ====================

    /**
     * Drop a column
     */
    public function dropColumn(string|array $columns): self
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $this->commands[] = ['type' => 'dropColumn', 'columns' => $columns];
        return $this;
    }

    /**
     * Rename a column
     */
    public function renameColumn(string $from, string $to): self
    {
        $this->commands[] = ['type' => 'renameColumn', 'from' => $from, 'to' => $to];
        return $this;
    }

    /**
     * Drop primary key
     */
    public function dropPrimary(?string $index = null): self
    {
        $this->commands[] = ['type' => 'dropPrimary', 'index' => $index];
        return $this;
    }

    /**
     * Drop unique index
     */
    public function dropUnique(string|array $index): self
    {
        $index = is_array($index) ? $index : [$index];
        $this->commands[] = ['type' => 'dropUnique', 'index' => $index];
        return $this;
    }

    /**
     * Drop an index
     */
    public function dropIndex(string|array $index): self
    {
        $index = is_array($index) ? $index : [$index];
        $this->commands[] = ['type' => 'dropIndex', 'index' => $index];
        return $this;
    }

    /**
     * Drop a spatial index
     */
    public function dropSpatialIndex(string|array $index): self
    {
        $index = is_array($index) ? $index : [$index];
        $this->commands[] = ['type' => 'dropSpatialIndex', 'index' => $index];
        return $this;
    }

    // ==================== BUILD SQL ====================

    /**
     * Build the CREATE TABLE SQL
     */
    public function toSql(): array
    {
        $sql = [];
        
        // Build column definitions
        $columnDefinitions = [];
        foreach ($this->columns as $column) {
            $columnDefinitions[] = $column->toSql();
        }

        // Build command definitions (indexes, foreign keys, etc.)
        $commandDefinitions = [];
        foreach ($this->commands as $command) {
            $commandDefinitions[] = $this->buildCommand($command);
        }

        // Combine all definitions
        $definitions = array_merge($columnDefinitions, array_filter($commandDefinitions));

        // Build CREATE TABLE statement
        $temp = $this->temporary ? 'TEMPORARY ' : '';
        $createSql = "CREATE {$temp}TABLE IF NOT EXISTS `{$this->table}` (\n  " 
            . implode(",\n  ", $definitions) 
            . "\n) ENGINE={$this->engine} DEFAULT CHARSET={$this->charset} COLLATE={$this->collation}";

        $sql[] = $createSql;

        return $sql;
    }

    /**
     * Build the ALTER TABLE SQL
     */
    public function toAlterSql(): array
    {
        $sql = [];

        // Process column additions
        foreach ($this->columns as $column) {
            $sql[] = "ALTER TABLE `{$this->table}` ADD COLUMN " . $column->toSql();
        }

        // Process commands
        foreach ($this->commands as $command) {
            switch ($command['type']) {
                case 'dropColumn':
                    foreach ($command['columns'] as $col) {
                        $sql[] = "ALTER TABLE `{$this->table}` DROP COLUMN `{$col}`";
                    }
                    break;

                case 'renameColumn':
                    $sql[] = "ALTER TABLE `{$this->table}` CHANGE `{$command['from']}` `{$command['to']}`";
                    break;

                case 'dropPrimary':
                    $sql[] = "ALTER TABLE `{$this->table}` DROP PRIMARY KEY";
                    break;

                case 'dropUnique':
                case 'dropIndex':
                case 'dropSpatialIndex':
                    foreach ($command['index'] as $index) {
                        $sql[] = "ALTER TABLE `{$this->table}` DROP INDEX `{$index}`";
                    }
                    break;

                case 'dropForeign':
                    foreach ($command['index'] as $index) {
                        $sql[] = "ALTER TABLE `{$this->table}` DROP FOREIGN KEY `{$index}`";
                    }
                    break;

                default:
                    $commandSql = $this->buildCommand($command);
                    if ($commandSql) {
                        $sql[] = "ALTER TABLE `{$this->table}` ADD " . $commandSql;
                    }
                    break;
            }
        }

        return $sql;
    }

    /**
     * Build command SQL
     */
    private function buildCommand(array $command): string
    {
        switch ($command['type']) {
            case 'primary':
                $cols = implode('`, `', $command['columns']);
                return "PRIMARY KEY (`{$cols}`)";

            case 'unique':
                $cols = implode('`, `', $command['columns']);
                return "UNIQUE KEY `{$command['name']}` (`{$cols}`)";

            case 'index':
                $cols = implode('`, `', $command['columns']);
                return "INDEX `{$command['name']}` (`{$cols}`)";

            case 'fulltext':
                $cols = implode('`, `', $command['columns']);
                return "FULLTEXT KEY `{$command['name']}` (`{$cols}`)";

            case 'spatial':
                $cols = implode('`, `', $command['columns']);
                return "SPATIAL KEY `{$command['name']}` (`{$cols}`)";

            case 'foreign':
                return $command['definition']->toSql();

            default:
                return '';
        }
    }

    /**
     * Execute the blueprint
     */
    public function build(): void
    {
        $statements = $this->toSql();
        foreach ($statements as $sql) {
            $this->connection->execute($sql);
        }
    }

    /**
     * Execute as ALTER statements
     */
    public function alter(): void
    {
        $statements = $this->toAlterSql();
        foreach ($statements as $sql) {
            $this->connection->execute($sql);
        }
    }

    /**
     * Get the table name
     */
    public function getTable(): string
    {
        return $this->table;
    }
}
