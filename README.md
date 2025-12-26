# Plugs Framework

Plugs is a modern, lightweight PHP framework designed for speed, structure, and freedom. Built by Celio Natti, it offers a modular design and intuitive syntax to make web development faster and cleaner.

## Features

- **Lightweight Core**: Minimal overhead for maximum performance.
- **Modular Design**: Use what you need, plug in the rest.
- **Intuitive Routing**: Simple and expressive routing syntax.
- **Modern PHP**: Built for PHP 8.0+.
- **Database Support**: Integrated with Illuminate Database (Eloquent).

## Installation

You can install Plugs via Composer:

```bash
composer require plugs/plugs
```

## Getting Started

1.  **Install Dependencies**:
    ```bash
    composer install
    ```

2.  **Environment Setup**:
    Copy `.env.example` to `.env` and configure your database and other settings.
    ```bash
    cp .env.example .env
    ```

3.  **Serve Application**:
    You can use the built-in PHP server for development:
    ```bash
    php -S localhost:8000 -t public
    ```

## Documentation

For full documentation, please visit [our website](https://github.com/celionatti/plugs).

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

Plugs is open-sourced software licensed under the [Apache-2.0 license](LICENSE).
