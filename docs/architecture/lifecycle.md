# Request Lifecycle

Understanding the **Plugs Request Lifecycle** is essential for mastering the framework and building high-performance applications.

---

## 1. Entry Point
Every request to a Plugs application enters through `public/index.php`. This file:
- Loads the Composer autoloader.
- Retrieves an instance of the `Plugs\Plugs` application.
- Hands off management to the **Kernel**.

## 2. Bootstrapping
Before a request is handled, the framework must "boot." The `Bootstrapper` performs the following steps:
- **Environment**: Loads the `.env` file or configuration cache.
- **Error Handling**: Initializes error and exception handlers based on `APP_DEBUG`.
- **Core Modules**: The `ModuleManager` registers essential services (Log, Database, Session).
- **Service Providers**: Discovers and runs the `register()` and `boot()` methods of all providers in `app/Providers`.

## 3. Router Dispatch
Once the application is booted, the `Router` takes over:
- It matches the incoming URI and HTTP method to a route.
- It identifies any **Route Parameters** (e.g., `/user/{id}`).
- It gathers the **Middleware** assigned to the route.

## 4. Middleware Pipeline
The request then travels through a "pipe" of middleware. Each middleware can inspect or modify the request before passing it to the next one, or return a response immediately (e.g., if authentication fails).

## 5. Execution & Response
The final destination is usually a **Controller** or a **Closure**.
- Dependencies are **Auto-Wired** from the DI Container.
- The logic executes and returns a `Response` object (or a `View`).
- The response travels back through the middleware stack in reverse order.

## 6. Termination
After the response is sent to the client, the `terminate()` phase begins:
- **FastCGI Finish**: If supported, the connection is closed so the user doesn't wait.
- **Background Tasks**: The `terminating` callbacks are executed (useful for logging, AI cache updates, or cleanup).
- **Events**: The `ResponseSent` event is dispatched.

---

## Next Steps
Learn more about the [Modular Architecture](./modular-architecture.md) that powers this lifecycle.
