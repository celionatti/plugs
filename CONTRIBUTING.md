# Contributing to Plugs

First off, thank you for considering contributing to the Plugs framework! It's people like you that make Plugs such a great tool for the PHP community.

By participating in this project, you agree to abide by our code of conduct.

## Getting Started

### 1. Fork and Clone

Fork the `plugs` repository on GitHub, then clone your fork locally:

```bash
git clone https://github.com/your-username/plugs.git
cd plugs
```

### 2. Set Up Environment

Install dependencies using Composer:

```bash
composer install
```

Copy the example environment file and generate an application key:

```bash
cp .env.example .env
php theplugs key:generate
```

### 3. Run Tests

Ensure the test suite passes before making any changes:

```bash
vendor/bin/phpunit
```

## Development Workflow

### Branching Strategy

Create a new branch for each feature or bug fix:

- **Features**: `feat/description-of-feature`
- **Bug Fixes**: `fix/description-of-bug`
- **Docs**: `docs/description-of-change`

```bash
git checkout -b feat/new-middleware
```

### Coding Standards

Plugs follows the **PSR-12** coding standard. Please ensure your code adheres to this standard.

- **Type Hinting**: Use strict typing (`declare(strict_types=1);`) in all PHP files.
- **Docblocks**: Add DocBlocks for all public methods and classes.
- **Naming**: Use camelCase for variables/methods and PascalCase for classes.

You can run our style checker to verify your code:

```bash
vendor/bin/php-cs-fixer fix
```

### Testing

- **New Features**: Must include accompanying unit or feature tests.
- **Bug Fixes**: Must include a regression test that fails without the fix and passes with it.

Run specific tests to save time:

```bash
vendor/bin/phpunit --filter=MiddlewareTest
```

## Pull Request Process

1.  **Rebase**: Ensure your branch is up-to-date with the main repository.
    ```bash
    git fetch upstream
    git rebase upstream/master
    ```
2.  **Push**: Push your changes to your fork.
    ```bash
    git push origin feat/new-middleware
    ```
3.  **Open PR**: Submit a Pull Request to the `master` branch of the main repository.
4.  **Description**: detailed description of your changes, including:
    - What does this PR do?
    - Why is this change needed?
    - Screenshots (if UI-related)
    - Linked Issues (e.g., `Closes #123`)

## Security Vulnerabilities

If you discover a security vulnerability within Plugs, please send an e-mail to **celionatti@gmail.com**. All security vulnerabilities will be promptly addressed.

**Please do not open a public issue for security vulnerabilities.**

## License

By contributing, you agree that your code will be licensed under the [Apache 2.0 License](LICENSE).
