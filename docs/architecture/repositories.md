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

This command creates three files if they don't exist:
1. `app/Repositories/BaseRepository.php`
2. `app/Repositories/Interfaces/UserRepositoryInterface.php`
3. `app/Repositories/UserRepository/UserRepository.php`

---

## 🛠️ Repository Interface

The interface defines the contract that the repository must follow. All interfaces are located in the `Interfaces` subfolder.

```php
namespace App\Repositories\Interfaces;

interface UserRepositoryInterface
{
    public function all(): \Plugs\Database\Collection;
    public function find(int $id): ?User;
    public function create(array $data): ?User;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}
```

---

## 📦 Repository Implementation

The implementation interacts with the Plugs ORM or Query Builder. Each repository implementation is stored in its own subfolder and extends `BaseRepository`.

```php
namespace App\Repositories\UserRepository;

use App\Models\User;
use App\Repositories\BaseRepository;
use App\Repositories\Interfaces\UserRepositoryInterface;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function all(): \Plugs\Database\Collection
    {
        return User::all();
    }

    public function find(int $id): ?User
    {
        return User::find($id);
    }

    public function create(array $data): ?User
    {
        return User::create($data);
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
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Repositories\UserRepository\UserRepository;

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
