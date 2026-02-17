<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;

class ExplainCommand extends Command
{
    protected string $description = 'Explain framework concepts and architecture.';

    protected function defineArguments(): array
    {
        return [
            'concept' => 'The concept to explain (e.g., controller, model, middleware, event)'
        ];
    }

    public function handle(): int
    {
        $concept = strtolower($this->argument('0') ?? '');

        if (!$concept) {
            $this->error('Please specify a concept to explain.');
            $this->info('Available concepts: controller, model, middleware, event, cache, routing');
            return 1;
        }

        $this->branding();
        $this->newLine();

        switch ($concept) {
            case 'controller':
                $this->explainController();
                break;
            case 'model':
                $this->explainModel();
                break;
            case 'middleware':
                $this->explainMiddleware();
                break;
            default:
                $this->error("Sorry, I don't have an explanation for '{$concept}' yet.");
                $this->info("Try: controller, model, or middleware.");
                return 1;
        }

        return 0;
    }

    private function explainController(): void
    {
        $this->advancedHeader('EXPLAINING: CONTROLLERS', 'The Brain of Your Request');

        $this->section('What is a Controller?');
        $this->line('A Controller acts as a traffic cop. it receives an HTTP Request, ');
        $this->line('interacts with Models/Services, and returns a Response.');
        $this->line('In Plugs, controllers should be "Thin" - move logic to Services!');

        $this->section('Lifecycle of a Controller Request');
        $this->bulletList([
            '1. Routing: dispatcher identifies the controller and method.',
            '2. Middleware: Global and Route middleware run BEFORE the controller.',
            '3. Injection: Dependencies are resolved via the Service Container.',
            '4. Execution: The controller method is called.',
            '5. Response: The method returns a String, Array, View, or Response object.',
            '6. Post-processing: Post-middleware runs before sending the output.'
        ]);

        $this->section('Flow Diagram');
        $this->line('  [ Browser ] ────▶ [ Router ] ────▶ [ Middleware ]');
        $this->line('                                          │');
        $this->line('  [ View/JSON ] ◀─── [ Controller ] ◀─────┘');
        $this->line('          │                │');
        $this->line('          └────────────────┴──▶ [ Models / DB ]');

        $this->section('Common Mistakes 🚩');
        $this->line("\033[91m✖ Fat Controllers:\033[0m Putting DB queries and business logic inside methods.");
        $this->line("\033[91m✖ Direct Access:\033[0m Accessing \$_GET or \$_POST directly instead of using Request class.");
        $this->line("\033[91m✖ No Validation:\033[0m Trusting user input without using \$request->validate().");

        $this->newLine();
        $this->info('Tip: Use `php theplugs make:controller BlogController` to get started!');
    }

    private function explainModel(): void
    {
        $this->advancedHeader('EXPLAINING: MODELS (PlugModel)', 'The Heart of Your Data');

        $this->section('What is a Model?');
        $this->line('Models represent your data structure and business logic.');
        $this->line('Plugs uses an Active Record implementation called PlugModel.');

        $this->section('Lifecycle of a Model');
        $this->bulletList([
            '1. Booting: Static boot() method initializes traits (SoftDeletes, HasEvents).',
            '2. Instantiation: new Model() or Model::find() creates an instance.',
            '3. Hydration: Raw DB results are mapped to model attributes.',
            '4. Events: firing `creating`, `updating`, `saving` hooks.',
            '5. Persistence: Changes are written back to the database.'
        ]);

        $this->section('Common Mistakes 🚩');
        $this->line("\033[91m✖ N+1 Queries:\033[0m Fetching relations inside a loop without eager loading (::with()).");
        $this->line("\033[91m✖ Mass Assignment:\033[0m Not defining \$fillable, allowing users to overwrite sensitive fields.");

        $this->newLine();
        $this->info('Tip: Always use \$fillable for security!');
    }

    private function explainMiddleware(): void
    {
        $this->advancedHeader('EXPLAINING: MIDDLEWARE', 'The Guardians of Your App');

        $this->section('What is Middleware?');
        $this->line('Middleware provides a convenient mechanism for filtering HTTP requests.');
        $this->line('For example, Plugs includes middleware that verifies authentication.');

        $this->section('Flow Diagram');
        $this->line(' [Req] ──▶ [MW 1: Auth] ──▶ [MW 2: CSRF] ──▶ [Controller]');
        $this->line('                                                │');
        $this->line(' [Res] ◀── [MW 1: Logs] ◀── [MW 2: SetHead] ◀───┘');

        $this->section('Common Mistakes 🚩');
        $this->line("\033[91m✖ Forgetting next():\033[0m Middleware MUST return \$next(\$request) or it breaks the request.");
        $this->line("\033[91m✖ Order Matters:\033[0m Auth middleware should usually run before Permission middleware.");

        $this->newLine();
        $this->info('Tip: Use middleware for session, auth, and security headers.');
    }
}
