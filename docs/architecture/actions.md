# Actions

Actions are single-purpose classes that encapsulate a specific piece of business logic. They adhere to the Single Responsibility Principle, making your code easier to test, maintain, and reuse.

## Generating Actions

You can generate a new action using the `make:action` command:

```bash
php theplugs make:action CreateUserAction
```

This will create a `CreateUserAction.php` file in the `app/Actions` directory.

### Options

- `--model=ModelName`: Associate the action with a specific model.
- `--queued`: Make the action queueable (adds the `Queueable` trait).
- `--strict`: Add strict type declarations to the file.

Example:

```bash
php theplugs make:action UpdateUserAction --model=User --strict
```

## Structure of an Action

An Action class typically contains a single public method, often `__invoke` or `handle`, which executes the logic.

```php
<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateUserAction
{
    /**
     * Execute the action.
     *
     * @param array $data
     * @return User
     */
    public function __invoke(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
    }
}
```

## Using Actions

You can inject Actions into your Controllers, Console Commands, or other classes.

### In a Controller

```php
<?php

namespace App\Http\Controllers;

use App\Actions\CreateUserAction;
use App\Http\Requests\StoreUserRequest;
use Plugs\Base\Controller\Controller;

class UserController extends Controller
{
    public function store(StoreUserRequest $request, CreateUserAction $createUser)
    {
        $user = $createUser($request->validated());

        return response()->json($user, 201);
    }
}
```

By invoking the action instance directly (`$createUser(...)`), you utilize the `__invoke` magic method, keeping the call site clean.

## Queueable Actions

If you use the `--queued` flag, your action will be generated with the `Queueable` trait, allowing you to easily dispatch it to the background queue.

```php
<?php

namespace App\Actions;

use Plugs\Queue\Queueable;

class ProcessReportAction
{
    use Queueable;

    public function __invoke(int $reportId): void
    {
        // Long running process...
    }
}
```

To dispatch:

```php
ProcessReportAction::dispatch($reportId);
```
