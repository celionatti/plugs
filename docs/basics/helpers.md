# Helper Functions

Plugs includes a variety of global "helper" PHP functions. These functions are designed to make your development process faster and your code more readable.

---

## 1. Application Helpers

| Helper | Description |
| --- | --- |
| `app()` | Get the service container instance or resolve a class. |
| `config()` | Get or set configuration values. |
| `env()` | Retrieve an environment variable with a fallback. |
| `logger()` | Log a message to the system logs. |
| `auth()` | Access the current authentication instance. |

## 2. URLs and Routing

| Helper | Description |
| --- | --- |
| `route($name, $params)` | Generate a URL for a named route. |
| `url($path)` | Generate a fully qualified URL for the given path. |
| `asset($path)` | Generate a URL for an asset (CSS, JS, Images). |
| `redirect($url)` | Create a redirect response instance. |

## 3. Views and Input

| Helper | Description |
| --- | --- |
| `view($name, $data)` | Render a view template. |
| `request()` | Get the current request instance. |
| `old($key, $default)` | Retrieve old input from the previous request. |
| `csrf_token()` | Get the current CSRF token hash. |

## 4. Debugging

| Helper | Description |
| --- | --- |
| `dd($vars)` | "Dump and Die": Output variables and terminate execution. |
| `d($vars)` | Dump variables without terminating. |
| `abort($code, $msg)` | Throw an HTTP exception (e.g., 404, 403). |

---

## Next Steps
Master the [View Layer](../views/engine.md) to start building your frontend.
