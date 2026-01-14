# Installation

Getting started with Plugs is easy. Follow these steps to set up your environment and create your first project.

## Prerequisites

- **PHP**: 8.1 or higher
- **Extensions**: PDO, OpenSSL, Mbstring, Tokenizer, XML, Ctype, JSON
- **Web Server**: Apache, Nginx, or the built-in PHP server
- **Database**: MySQL, PostgreSQL, or SQLite

## Setup via Composer

To create a new project using Plugs, run:

```bash
composer create-project plugs/plugs your-app-name
```

## Directory Permissions

After installation, ensure the following directories are writable by your web server:

- `storage`
- `bootstrap/cache`

```bash
chmod -R 775 storage bootstrap/cache
```

## Environment Configuration

Copy the `.env.example` file to `.env` and configure your settings:

```bash
cp .env.example .env
```

Generate an application key:

```bash
php theplugs key:generate
```

## Local Development Server

You can quickly serve your application using the built-in server:

```bash
php theplugs serve
```

Your application will be available at `http://localhost:8000`.
