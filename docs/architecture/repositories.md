# Repository Pattern

Repositories are used to abstract the data access layer from the business logic. They provide a clean API for your services or controllers to interact with the database without being concerned with the underlying ORM or query builder implementation.

In the Plugs framework, repositories typically interact with **Models** and are injected into **Services**.

## Benefits
- **Separation of Concerns**: Decouples data access from business logic.
- **Testability**: Easier to mock data access in unit tests.
- **Maintainability**: Centralizes query logic for a specific entity.

---

## 🏗️ Creating a Repository

You can generate a repository using the `theplugs` CLI:

```bash
php theplugs make:repository UserRepository --model=User --interface
```

This command creates two files:
1. `app/Repositories/UserRepositoryInterface.php`
2. `app/Repositories/UserRepository.php`

---

## 🛠️ Repository Interface

The interface defines the contract that the repository must follow. This is crucial for dependency injection and mocking.

```php
namespace App\Repositories;

interface UserRepositoryInterface
{
    public function all(): array;
    public function find(int $id): ?array;
    public function create(array $data): ?array;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}
```

---

## 📦 Repository Implementation

The implementation interacts with the Plugs ORM or Query Builder.

```php
namespace App\Repositories;

use App\Models\User;

class UserRepository implements UserRepositoryInterface
{
    public function all(): array
    {
        return User::all()->toArray();
    }

    public function find(int $id): ?array
    {
        $user = User::find($id);
        return $user ? $user->toArray() : null;
    }

    public function create(array $data): ?array
    {
        $user = User::create($data);
        return $user ? $user->toArray() : null;
    }

    public function update(int $id, array $data): bool
    {
        return User::where('id', $id)->update($data) > 0;
    }

    public function delete(int $id): bool
    {
        return User::destroy($id) > 0;
    }
}
```

---

## 🔗 Binding the Repository

In your application's `AppServiceProvider` (usually in `app/Providers`), you should bind the interface to the implementation:

```php
namespace App\Providers;

use Plugs\Support\ServiceProvider;
use App\Repositories\UserRepositoryInterface;
use App\Repositories\UserRepository;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    }
}
```

---

## 💡 Usage Example

Once bound, you can inject the repository into your services:

```php
namespace App\Services;

use App\Repositories\UserRepositoryInterface;

class UserService
{
    protected $repository;

    public function __construct(UserRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getActiveUsers()
    {
        return $this->repository->all();
    }
}
```
