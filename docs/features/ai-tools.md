# AI-Powered CLI Tools

The Plugs framework provides a suite of AI-driven commands to accelerate development, improve code quality, and automate testing.

## 1. AI Migration Generator

Generate database migrations from natural language descriptions.

### Usage

```bash
php theplugs make:ai-migration "create a posts table with title, body, and user_id"
```

### Options

- `prompt`: (Optional) The description of the table. If omitted, the command will ask interactively.

### How it Works

1. The AI analyzes your prompt and suggests a database structure using Plugs Blueprint syntax.
2. It automatically infers a suitable table name.
3. A new migration file is created in `database/Migrations` with the suggested schema.

---

## 2. AI Test Generator

Automatically generate PHPUnit tests for any class or source file.

### Usage

```bash
php theplugs make:ai-test "App\Http\Controllers\UserController"
```

Or use a file path:

```bash
php theplugs make:ai-test src/Services/PaymentService.php
```

### Options

- `--type`: Specify the test type. Options: `Unit` (default) or `Integration`.

### How it Works

The command extracts the source code, analyzes its logic/dependencies, and scaffolds a comprehensive test file (including mocks) in the `tests/` directory.

---

## 3. AI Code Fixer

Refactor code, fix bugs, or modernize syntax using AI.

### Usage

```bash
php theplugs ai:fix src/Utils/Security.php "refactor for better readability and PHP 8.2 features"
```

### Interaction

1. The AI suggests improvements based on your instructions.
2. You will be shown a preview of the changes.
3. You must confirm before the file is updated.

---

## 4. AI Project Auditor

Audit your codebase for security vulnerabilities and performance bottlenecks.

### Usage

```bash
php theplugs ai:audit [directory]
```

### Deep Audit

After the initial scan, you can specify a specific file path for a **line-by-line deep audit**. The AI will look for:

- SQL Injection risks
- Cross-Site Scripting (XSS)
- N+1 query problems
- Inefficient algorithms
- Insecure configurations
