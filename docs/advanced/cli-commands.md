# CLI Tool (theplugs)

The `theplugs` CLI tool is the command-line interface included with the Plugs framework. It provides a number of helpful commands that can assist you while you build your application.

## Usage

To see a list of all available commands, you may use the `list` command:

```bash
php theplugs list
```

## Built-in Commands

### Framework Insights (New)

The `framework:explain` command is an educational tool that explains framework concepts, lifecycles, and architecture directly in your terminal:

```bash
php theplugs framework:explain controller
php theplugs framework:explain model
php theplugs framework:explain middleware
```

Outputs include:

- **Concept Definition**: Clear explanation of the role.
- **Visual Lifecycle**: ASCII diagrams of request/data flow.
- **Common Mistakes**: Proactive tips to avoid typical developer pitfalls.

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

### Database Backup & Recovery (New)

Back up your database:

```bash
php theplugs db:backup
```

Restore from a backup:

```bash
php theplugs db:restore [path]
```

**Alias:** `dbb` (backup)

### Generators

Plugs includes several "make" commands to quickly scaffold components:

```bash
php theplugs make:controller UserController
php theplugs make:model Post -m  # With migration
php theplugs make:middleware AuthMiddleware
php theplugs make:migration create_posts_table
php theplugs make:module Core  # Create a simple system module
php theplugs make:feature-module Shop  # Create a full feature module (Controllers, Models, etc.)
php theplugs make:auth-module MemberAuth  # Create a complete Auth system (Login, Register, etc.)
php theplugs make:event UserRegistered
php theplugs make:listener SendWelcomeEmail
php theplugs make:notification InvoicePaid
php theplugs make:pdf-template invoice
php theplugs make:pagination-template tailwind
php theplugs make:spa-asset --min  # Generate SPA bridge with minification
php theplugs make:component MyButton  # Create a simple view component
php style="color: #979797"> theplugs make:component SearchBar --bolt  # Create a reactive Bolt component
php theplugs make:theme Minimal  # Scaffold a new theme directory
```

### Theming (New)

Manage your application's visual identity with specialized theme commands:

```bash
# Create a new theme scaffolding
php theplugs make:theme [name]

# Install the premium Nebula (Space) theme
php theplugs theme:nebula
```

**Options for `make:theme`:**

- `--force, -f`: Overwrite existing theme directories.

**Alias:** `g:theme`

### Events & Listeners (New)

Generate event and listener classes:

```bash
php theplugs make:event [name]
php theplugs make:listener [name]
```

**Aliases:** `g:evt`, `g:lis`

### Notifications (New)

Generate notification classes:

```bash
php theplugs make:notification [name]
```

**Alias:** `g:not`

### View Components (New)

Automate the creation of view components:

```bash
php theplugs make:component [name]
```

**Options:**

- `--bolt, -b`: Create a reactive Bolt component (with PHP class).
- `--force, -f`: Overwrite existing files.

**Alias:** `g:comp`

### Translation Files

Scaffold translation files for a new locale with pre-filled common translations:

```bash
php theplugs make:lang [locale]
```

**Options:**

- `--force, -f`: Overwrite existing translation files.
- `--groups`: Comma-separated list of groups to create (default: `messages,validation,auth`).

**Pre-filled locales:** `en`, `fr`, `es`, `de`, `pt`, `zh`, `ar`.

**Alias:** `g:lang`

### SPA Bridge (New)

Generate the Plugs SPA Bridge asset for your application:

```bash
php theplugs make:spa-asset
```

**Options:**

- `--min`: Create a minified version (`plugs-spa.min.js`).
- `--force`: Overwrite existing files.

**Alias:** `g:spa`

### Module Generation (New)

Scaffold different types of modules for your application:

```bash
# Simple system module
php theplugs make:module [name]

# Full-featured module (Controllers, Models, Routes, Views)
php theplugs make:feature-module [name]

# Ready-to-use Auth module (Login, Register, Services, Repositories)
php theplugs make:auth-module [name]
```

**Options:**

- `--force, -f`: Overwrite existing module files.

**Aliases:** `g:mod`, `g:fmod`, `g:auth`

### Plugs Core Assets (New)

Publish and minify core framework assets (Editor, Lazy Loading) to the `public/plugs` directory:

```bash
php theplugs make:plugs-assets
```

**Options:**

- `--min`: Create minified versions (`.min.js`, `.min.css`).
- `--force`: Overwrite existing files.

**Alias:** `g:assets`

````

### PDF Templates

Generate professional, pre-designed PDF templates:

```bash
php theplugs make:pdf-template [type]
````

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

### Optimization (New)

Caching your framework state is the best way to achieve maximum performance in production. These commands eliminate filesystem hits and expensive parsing.

#### Caching Everything

The `optimize` command is a shortcut to cache routes, container reflection, and configuration all at once.

```bash
php theplugs optimize
```

To remove all optimization caches:

```bash
php theplugs optimize:clear
```

#### Route Caching

Specifically caches your application routes for O(1) matching speed.

```bash
php theplugs route:cache
php theplugs route:clear
```

#### Container Caching

Specifically caches the dependency injection container's reflection data.

```bash
php theplugs container:cache
php theplugs container:clear
```

#### Configuration Caching

Specifically caches your configuration files and environment variables.

```bash
php theplugs config:cache
php theplugs config:clear
```

#### Security Shield Management (New)

Manage automatic blocks and monitor security logs:

```bash
# List all blocked IPs and fingerprints
php theplugs shield:list

# Unblock a specific IP or fingerprint
php theplugs shield:unblock 192.168.1.1

# Clear all blocks and security logs
php theplugs shield:clear

# Display security statistics
php theplugs shield:stats
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
