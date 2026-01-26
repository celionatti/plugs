# CLI Tool (theplugs)

The `theplugs` CLI tool is the command-line interface included with the Plugs framework. It provides a number of helpful commands that can assist you while you build your application.

## Usage

To see a list of all available commands, you may use the `list` command:

```bash
php theplugs list
```

## Built-in Commands

### Key Generation

The `key:generate` command sets your application key in your `.env` file:

```bash
php theplugs key:generate
```

### Server

The `serve` command starts the PHP built-in server:

```bash
php theplugs serve --port=8080
```

### Database Migrations

Run your database migrations:

```bash
php theplugs migrate
```

Check the status of your migrations:

```bash
php theplugs migrate:status
```

Rollback the last migration:

```bash
php theplugs migrate:rollback
```

Reset all migrations (rollback everything):

```bash
php theplugs migrate:reset
```

Fresh migration (drop all tables and re-run all):

```bash
php theplugs migrate:fresh
```

Validate migration integrity:

```bash
php theplugs migrate:validate
```

### Generators

Plugs includes several "make" commands to quickly scaffold components:

```bash
php theplugs make:controller UserController
php theplugs make:model Post -m  # With migration
php theplugs make:middleware AuthMiddleware
php theplugs make:migration create_posts_table
php theplugs make:pdf-template invoice
php theplugs make:pagination-template tailwind
```

### PDF Templates

Generate professional, pre-designed PDF templates:

```bash
php theplugs make:pdf-template [type]
```

**Types:** `invoice`, `receipt`, `ticket`, `booking`, `certificate`, `cv`.

### Pagination Templates

Generate modern pagination templates:

```bash
php theplugs make:pagination-template [type]
```

**Types:** `tailwind`, `bootstrap`, `simple`.

```bash
php theplugs queue:work
```

### Scheduling

List the scheduled tasks:

```bash
php theplugs schedule:list
```

Run the scheduled tasks that are due:

```bash
php theplugs schedule:run
```

## Creating Custom Commands

You can create your own custom commands by extending the `Plugs\Console\Command` class and registering it in your `app/Console/Kernel.php`.

```php
namespace App\Console\Commands;

use Plugs\Console\Command;

class SendEmails extends Command
{
    protected $signature = 'mail:send {user}';
    protected $description = 'Send an email to a user';

    public function handle()
    {
        $userId = $this->argument('user');
        // Logic to send email...
        $this->info("Email sent to user {$userId}!");
    }
}
```
