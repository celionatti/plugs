# Testing

The Plugs framework is built with testing in mind. In fact, support for testing with PHPUnit is included out of the box and a `phpunit.xml` file is already set up for your application. The framework also ships with convenient helper methods that allow you to expressively test your applications.

By default, your application's `tests` directory contains two directories: `Unit` and `Feature`. Unit tests are tests that focus on a very small, isolated portion of your code. Feature tests may test a larger portion of your code, including how several objects interact with each other or even a full HTTP request to a JSON endpoint.

## Environment

When running tests, Plugs will automatically set the configuration environment to `testing` because of the environment variables defined in the `phpunit.xml` file. 

## Creating Tests

To create a new test case, you can simply create a new file in the `tests/Unit` or `tests/Feature` directory. Each test class should extend the `Tests\TestCase` class.

```php
namespace Tests\Unit;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_basic_test(): void
    {
        $this->assertTrue(true);
    }
}
```

## Running Tests

To run your tests, you may use the `vendor/bin/phpunit` command:

```bash
vendor/bin/phpunit
```

Alternative ways to run tests:
```bash
./vendor/bin/phpunit --testdox
```

## HTTP Testing

Plugs provides a fluent API for making HTTP requests to your application and examining the output.

### Fluent Assertions

The `TestResponse` class allows you to chain assertions for a clear and readable testing style.

```php
public function test_api_endpoint(): void
{
    $response = $this->get('/api/users');

    $response->assertStatus(200)
             ->assertHeader('Content-Type', 'application/json')
             ->assertJson([
                 'success' => true
             ]);
}
```

#### Available Assertions

| Method | Description |
|--------|-------------|
| `assertStatus($code)` | Assert the response has the given status code. |
| `assertOk()` | Assert the response has a 200 status code. |
| `assertJson($data)` | Assert the response contains the given JSON data. |
| `assertHeader($name, $value)` | Assert the response has the given header. |
| `assertRedirect($uri)` | Assert the response is a redirect to the given URI. |

This fluent interface makes it significantly faster to build robust integration test suites for your APIs and web pages.
