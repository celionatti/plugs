# The Application Architecture: Putting It All Together

This guide explains how the core components of the framework—Requests, Controllers, Services, Actions, Repositories, and Models—work together to build scalable and maintainable applications.

## The Request Life Cycle

Here is a high-level overview of how a typical HTTP request is processed:

1.  **Request**: The user sends an HTTP request (e.g., POST `/users`).
2.  **Routing**: The framework routes the request to the appropriate Controller.
3.  **Form Request**: Before reaching the controller, the request is validated by a **Form Request** class.
4.  **Controller**: The **Controller** receives the validated request. It does not contain business logic. instead, it delegates to a **Service** or an **Action**.
5.  **Service/Action**: The **Service** (or Action) contains the business logic. It performs calculations, calls external APIs, and pushes data to the database via a **Repository**.
6.  **Repository**: The **Repository** handles the actual data retrieval and storage using **Models**.
7.  **Model**: The **Model** represents the database table and relationships.
8.  **Response**: The result bubbles back up: Repository -> Service -> Controller, which then returns a JSON response or a View to the user.

## Example Workflow: Registering a User

Let's walk through a concrete example of registering a new user.

### 1. The Route

```php
// routes/api.php
Route::post('/register', [AuthController::class, 'register']);
```

### 2. The Form Request

We generate a request to validate the input.

```bash
php theplugs make:request RegisterRequest
```

```php
// app/Http/Requests/RegisterRequest.php
public function rules(): array
{
    return [
        'name' => 'required|string',
        'email' => 'required|email|unique:users',
        'password' => 'required|string|min:8|confirmed',
    ];
}
```

### 3. The Controller

The controller is thin. It injects the `UserService` and delegates the work.

```php
// app/Http/Controllers/AuthController.php
class AuthController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function register(RegisterRequest $request)
    {
        $user = $this->userService->registerUser($request->validated());

        return response()->json(['user' => $user], 201);
    }
}
```

### 4. The Service

The service handles the business logic (hashing password, sending emails) and uses the repository to save the user.

```php
// app/Services/UserService.php
class UserService
{
    protected $users;

    public function __construct(UserRepositoryInterface $users)
    {
        $this->users = $users;
    }

    public function registerUser(array $data): User
    {
        $data['password'] = Hash::make($data['password']);

        $user = $this->users->create($data);

        // Send welcome email...
        // Dispatch events...

        return $user;
    }
}
```

### 5. The Repository

The repository abstracts the database layer.

```php
// app/Repositories/UserRepository/UserRepository.php
class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function create(array $data): User
    {
        return User::create($data);
    }
}
```

### 6. The Model

The model is a simple representation of the data.

```php
// app/Models/User.php
class User extends PlugModel
{
    protected $fillable = ['name', 'email', 'password'];
}
```

## Summary of Responsibilities

| Component      | Responsibility                                         | Command to Generate            |
| :------------- | :----------------------------------------------------- | :----------------------------- |
| **Route**      | Maps URL to Controller                                 | N/A                            |
| **Request**    | Validates and authorizes input                         | `php theplugs make:request`    |
| **Controller** | Handles HTTP request/response, delegates logic         | `php theplugs make:controller` |
| **Service**    | Contains business logic                                | `php theplugs make:service`    |
| **Action**     | Single-purpose business logic (Alternative to Service) | `php theplugs make:action`     |
| **Repository** | Abstraction for data access                            | `php theplugs make:repository` |
| **Model**      | Represents database table                              | `php theplugs make:model`      |

By following this architecture, you ensure your application remains modular, testable, and easy to understand.
