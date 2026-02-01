# Service Layer

The Service Layer is where your application's **Business Logic** lives. It sits between the Controller and the Repository (or Model), ensuring that controllers remain thin and logic is reusable across different entry points (Web, API, CLI).

## Why Use Services?
- **Thin Controllers**: Controllers only handle requests and responses.
- **Reusable Logic**: Logic can be shared between different controllers.
- **Transaction Management**: Services are the perfect place to handle database transactions.
- **External API Interaction**: Logic for calling 3rd party services (Stripe, Twilio, etc.) belongs here.

---

## 🏗️ Creating a Service

You can generate a service using the `theplugs` CLI:

```bash
php theplugs make:service UserService --repository
```

This command creates a service that is automatically configured to use a repository.

---

## 🛠️ Service Implementation

A typical service handles orchestrating data operations and applying business rules.

```php
namespace App\Services;

use App\Repositories\UserRepositoryInterface;
use Plugs\Support\Facades\DB;
use Exception;

class UserService
{
    protected $repository;

    public function __construct(UserRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Create a new user with business logic validation.
     */
    public function registerUser(array $data): User
    {
        return DB::transaction(function () use ($data) {
            // 1. Business Logic: Check if registration is open
            if (!config('app.registration_open')) {
                throw new Exception("Registration is currently closed.");
            }

            // 2. Data Persistence via Repository
            $user = $this->repository->create($data);

            // 3. Post-creation logic: Send Welcome Email
            // Mail::to($user->email)->send(new WelcomeMail($user));

            return $user;
        });
    }

    public function getUserProfile(int $id): ?User
    {
        return $this->repository->find($id);
    }
}
```

---

## 🔗 Using Services in Controllers

Simply type-hint the service in your controller constructor to have the Plugs Dependency Injection container inject it for you.

```php
namespace App\Http\Controllers;

use App\Services\UserService;
use App\Http\Requests\RegisterUserRequest;

class AuthController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function register(RegisterUserRequest $request)
    {
        // 1. Request is already validated by StoreUserRequest
        $data = $request->validated();

        try {
            // 2. Delegate logic to the Service
            $user = $this->userService->registerUser($data);

            return redirect('/dashboard')->with('success', 'Welcome!');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
```

---

## 💡 Best Practices

1. **Keep it focused**: A service should handle logic for a single domain or entity.
2. **Avoid Global State**: Do not rely on `session()` or `auth()` inside a service; pass required data as method arguments.
3. **Return Data, Not Responses**: A service should return models, arrays, or throw exceptions. It should never return a `Response` or `Redirect`.
