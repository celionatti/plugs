# Database Backup & Restoration

The Plugs framework provides a powerful, driver-based system for backing up and restoring your database. This ensures your data is protected and easily recoverable.

## Features

- **Multi-Driver Support**: Compatible with MySQL and SQLite out of the box.
- **Automated Filename Generation**: Automatically creates timestamped backups.
- **Customizable Storage Path**: Choose where your backups are stored.
- **Integrity Checks**: Verifies existence of required tools (like `mysqldump`) and backup files before operations.
- **Safety Prompts**: Confirmation dialogs for destructive restoration tasks.
- **Developer Aliases**: Quick CLI shortcuts for faster workflow.

---

## Command Usage

### 1. Basic Backup

To create a backup using default settings (saved in `storage/backups/`):

```bash
php theplugs db:backup
```

_Alias: `php theplugs dbb`_

### 2. Custom Filename

Specify a filename for your backup:

```bash
php theplugs db:backup my-backup.sql
```

### 3. Custom Storage Path

Specify a specific directory for the backup:

```bash
php theplugs db:backup --path=/path/to/custom/directory
```

### 4. Restoration

To restore your database from a specific file:

```bash
php theplugs db:restore storage/backups/backup-2026-02-26.sql
```

> [!WARNING]
> Restoring a database will overwrite your current data. Always ensure you have a fresh backup before performing a restoration.

---

## Automation & Production

### Task Scheduling

You can automate your backups by adding them to the scheduler in `app/Console/Kernel.php`:

```php
// app/Console/Kernel.php

public function schedule(Schedule $schedule): void
{
    // Backup daily at midnight
    $schedule->command('db:backup')->daily();
}
```

### Production Cron Setup

On your production server, add a Cron job to trigger the Plugs scheduler every minute:

```bash
* * * * * cd /your-project-path && php theplugs schedule:run >> /dev/null 2>&1
```

---

## Configuration Settings

The system uses your existing `DB_` environment variables from the `.env` file to handle connections.

- **MySQL**: Uses `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`.
- **SQLite**: Uses the file path specified in `DB_DATABASE`.
