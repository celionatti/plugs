# Request Lifecycle

Understanding the Plugs Request Lifecycle is key to building complex applications and optimizing performance.

## 1. Entry Point

Every request starts in `public/index.php`. This file is the gateway to your application. It loads the Composer autoloader and initializes the framework.

## 2. Bootstrapping

The `Plugs\Bootstrap\Bootstrapper` takes over. It:

- Loads the `.env` file and merges configurations.
- **Module Registration**: Registers all core framework modules (Log, DB, Session, etc.) with the `ModuleManager`.
- **Modular Boot**: Executes the `bootstrap()` phase on the application, which boots all enabled modules for the current context.
- Registers core services in the DI Container.
- Discovers and boots all `ServiceProviders` in `app/Providers`.
- Sets up the Error and Exception handlers.

## 3. Router Dispatch

Once bootstrapped, the `Router` analyzes the incoming `Request`:

- It matches the URI and HTTP method against registered routes.
- It resolves any route parameters and performs **ValueObject Binding**.
- It wraps the execution in a **Middleware Pipeline**.

## 4. Middleware Pipeline

Requests pass through a chain of global and route-specific middlewares (CSRF, Security Shield, Auth). Each middleware can:

- Modify the Request.
- Halt execution and return a Response (e.g., Redirect if not auth).
- Pass the Request to the next layer.

## 5. Controller Resolution

The Container instantiates your `Controller` and **Auto-Wires** its constructor and method dependencies. Your logic executes, potentially interacting with Models or Services, and returns a `Response` or `View`.

## 6. Exit Pipeline

The `Response` travels back through those same middlewares (allowing them to modify headers, etc.) and is finally sent to the client's browser.

## 7. Termination Hook (`terminate`)

After the response has been sent and the connection to the client is potentially closed (via `fastcgi_finish_request`), the framework enters the termination phase.

- **Background Execution**: Logic registered via `Plugs::terminating()` is executed here.
- **Cleanup & Storage**: Post-response tasks like AI cache refreshing (SWR), session closing, and profiler data storage are performed.
- **Zero-Latency Post-Processing**: This allows expensive tasks to run without affecting the user-perceived load time.
