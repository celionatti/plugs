# API Integrations

The Plugs framework includes a powerful, object-oriented API integration layer inspired by the [Saloon](https://docs.saloon.dev/) library. This system allows you to define API integrations using structured Connector and Request classes, making your code cleaner, more reusable, and easier to test.

## Key Concepts

There are two main concepts to understand:

1.  **Connectors**: Represent an API service (e.g., Stripe, GitHub, Twitter).
2.  **Requests**: Represent a specific endpoint call (e.g., Get User, Create Post).

---

## Creating a Connector

A connector class MUST extend `Plugs\Http\Integration\Connector`. It defines the base URL and standard configuration for the API service.

```php
<?php

namespace App\Http\Integrations\GitHub;

use Plugs\Http\Integration\Connector;

class GitHubConnector extends Connector
{
    /**
     * Define the base URL for the API.
     */
    public function resolveBaseUrl(): string
    {
        return 'https://api.github.com';
    }

    /**
     * Define default headers for every request.
     */
    public function headers(): array
    {
        return [
            'Accept' => 'application/vnd.github.v3+json',
            'Authorization' => 'token ' . getenv('GITHUB_TOKEN'),
        ];
    }
    
    /**
     * Define default configuration (timeout, retries, etc).
     */
    public function defaultConfig(): array
    {
        return [
            'timeout' => 30,
        ];
    }
}
```

---

## Creating a Request

A request class MUST extend `Plugs\Http\Integration\Request`. It defines the method, endpoint, and data for a specific API call.

```php
<?php

namespace App\Http\Integrations\GitHub\Requests;

use Plugs\Http\Integration\Request;
use Plugs\Http\Integration\Enums\Method;

class GetUserRequest extends Request
{
    /**
     * The HTTP method.
     */
    protected string $method = Method::GET;

    /**
     * The endpoint for the request.
     */
    public function resolveEndpoint(): string
    {
        return '/user';
    }
}
```

### Sending Data

You can include data in your requests using methods or properties. For convenience, you can use traits like `HasJsonBody` or `HasFormParams`.

```php
<?php

namespace App\Http\Integrations\GitHub\Requests;

use Plugs\Http\Integration\Request;
use Plugs\Http\Integration\Enums\Method;
use Plugs\Http\Integration\Concerns\HasJsonBody;

class CreateIssueRequest extends Request
{
    use HasJsonBody;

    protected string $method = Method::POST;

    public function resolveEndpoint(): string
    {
        return '/repos/owner/repo/issues';
    }
    
    public function defaultBody(): array
    {
        return [
            'title' => 'Found a bug',
            'body' => 'Something went wrong...',
        ];
    }
}
```

---

## Sending Requests

To send a request, instantiate the connector and pass the request object to the `send` method.

```php
use App\Http\Integrations\GitHub\GitHubConnector;
use App\Http\Integrations\GitHub\Requests\CreateIssueRequest;

$connector = new GitHubConnector();
$request = new CreateIssueRequest();

// You can modify data on the fly if using the HasJsonBody trait
$request->withData(['title' => 'New Feature Request']);

$response = $connector->send($request);

if ($response->successful()) {
    $issue = $response->json();
    echo "Issue created: " . $issue['html_url'];
} else {
    echo "Error: " . $response->body();
}
```

### Asynchronous Requests

You can also send requests asynchronously using `sendAsync`. This returns a Guzzle Promise.

```php
$promise = $connector->sendAsync($request);

$promise->then(function ($response) {
    echo "Success: " . $response->status();
});

$promise->wait();
```

---

## Comparison with Standard HTTP Client

While you can still use `HTTPClient` for simple, one-off requests, the Integration layer provides:

-   **Structure**: Group related requests together.
-   **Reusability**: Define headers and base URLs in one place.
-   **Type Safety**: Classes strictly define what an API can do.
-   **Testability**: Easier to mock entire connectors or specific requests.

---

## Console Commands

You can quickly generate Connectors and Requests using the console.

**Create a Connector:**
```bash
php theplugs make:connector GitHub --base-url="https://api.github.com"
```

**Create a Request:**
```bash
php theplugs make:api-request GetUser GitHub --method=GET --endpoint="/user"
```
