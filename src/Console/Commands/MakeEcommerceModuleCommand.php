<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Support\Str;

class MakeEcommerceModuleCommand extends Command
{
    protected string $description = 'Create a fully functional eCommerce module with products, categories, and orders';

    protected function defineArguments(): array
    {
        return [
            'name' => 'The name of the module (default: Ecommerce)',
        ];
    }

    protected function defineOptions(): array
    {
        return [
            '--force' => 'Overwrite existing module files',
        ];
    }

    public function handle(): int
    {
        $this->advancedHeader('eCommerce Module Generator', 'Scaffolding full store management systems');

        $name = $this->argument('0') ?: 'Ecommerce';
        $lowerName = strtolower($name);
        $basePath = getcwd() . '/modules/' . $name;

        if (Filesystem::isDirectory($basePath) && !$this->hasOption('force')) {
            $this->error("Module '{$name}' already exists at modules/{$name}/");
            $this->note("Use --force to overwrite existing files.");
            return 1;
        }

        $this->task('Creating directory structure', function () use ($basePath) {
            $directories = [
                $basePath,
                $basePath . '/Controllers/Admin',
                $basePath . '/Migrations',
                $basePath . '/Models',
                $basePath . '/Routes',
                $basePath . '/Views/admin/categories',
                $basePath . '/Views/admin/brands',
                $basePath . '/Views/admin/products',
                $basePath . '/Views/admin/orders',
            ];

            foreach ($directories as $dir) {
                Filesystem::ensureDir($dir);
            }
        });

        $stubsPath = __DIR__ . '/../Stubs/Ecommerce';

        $filesToGenerate = [
            'EcommerceModule.php.stub' => $name . 'Module.php',
            'Models/Category.php.stub' => 'Models/Category.php',
            'Models/Brand.php.stub' => 'Models/Brand.php',
            'Models/Product.php.stub' => 'Models/Product.php',
            'Models/ProductImage.php.stub' => 'Models/ProductImage.php',
            'Models/Order.php.stub' => 'Models/Order.php',
            'Models/OrderItem.php.stub' => 'Models/OrderItem.php',
            'Controllers/Admin/CategoryController.php.stub' => 'Controllers/Admin/CategoryController.php',
            'Controllers/Admin/BrandController.php.stub' => 'Controllers/Admin/BrandController.php',
            'Controllers/Admin/ProductController.php.stub' => 'Controllers/Admin/ProductController.php',
            'Controllers/Admin/OrderController.php.stub' => 'Controllers/Admin/OrderController.php',
            'Migrations/create_ecommerce_tables.php.stub' => 'Migrations/' . date('Y_m_d_His') . '_create_' . $lowerName . '_tables.php',
            'Routes/web.php.stub' => 'Routes/web.php',
            'Routes/api.php.stub' => 'Routes/api.php',
            'Views/admin/categories/index.plug.php.stub' => 'Views/admin/categories/index.plug.php',
            'Views/admin/categories/create.plug.php.stub' => 'Views/admin/categories/create.plug.php',
            'Views/admin/categories/edit.plug.php.stub' => 'Views/admin/categories/edit.plug.php',
            'Views/admin/brands/index.plug.php.stub' => 'Views/admin/brands/index.plug.php',
            'Views/admin/brands/create.plug.php.stub' => 'Views/admin/brands/create.plug.php',
            'Views/admin/brands/edit.plug.php.stub' => 'Views/admin/brands/edit.plug.php',
            'Views/admin/products/index.plug.php.stub' => 'Views/admin/products/index.plug.php',
            'Views/admin/products/create.plug.php.stub' => 'Views/admin/products/create.plug.php',
            'Views/admin/products/edit.plug.php.stub' => 'Views/admin/products/edit.plug.php',
            'Views/admin/orders/index.plug.php.stub' => 'Views/admin/orders/index.plug.php',
            'Views/admin/orders/show.plug.php.stub' => 'Views/admin/orders/show.plug.php',
            'module.json.stub' => 'module.json',
        ];

        $this->output->section('Generating Module Files');

        foreach ($filesToGenerate as $stub => $destination) {
            $content = Filesystem::get($stubsPath . '/' . $stub);
            $content = str_replace(
                ['{{name}}', '{{lowerName}}'],
                [$name, $lowerName],
                $content
            );
            $fullPath = $basePath . '/' . $destination;
            Filesystem::put($fullPath, $content);
            $this->fileCreated($fullPath);
        }

        $this->newLine();
        $this->resultSummary([
            'Module' => $name,
            'Location' => "modules/{$name}/",
            'Namespace' => "Modules\\{$name}\\"
        ], $this->elapsed());

        $this->section('Next Steps');
        $this->numberedList([
            "Run `php theplugs migrate` to create the database tables.",
            "Visit `/admin/{$lowerName}/products` to start managing your store."
        ]);

        return 0;
    }
}
