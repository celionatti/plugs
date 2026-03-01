<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;

class IdentityInstallCommand extends Command
{
    protected string $description = 'Publish the identity (passwordless key) database migrations';

    public function handle(): int
    {
        $this->title('Identity Scaffolding');

        $this->task('Publishing users table identity modifier migration', function () {
            $this->publishIdentityMigration();
        });

        $this->newLine();
        $this->box(
            "Identity scaffolding installed successfully!\n\n" .
            "Run 'php plugs migrate' to apply the schema changes.",
            "✅ Success",
            "success"
        );

        return 0;
    }

    private function publishIdentityMigration(): void
    {
        $migrationFile = 'add_identity_columns_to_users_table.php';
        $basePath = base_path('database/Migrations');

        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }

        // Check if it already exists
        $files = glob($basePath . '/*_' . $migrationFile);
        if (!empty($files)) {
            $this->warning(" Migration [{$migrationFile}] already exists.");
            return;
        }

        // Wait 1 second to ensure it doesn't get the exact same timestamp as auth:install if run immediately after
        sleep(1);
        $timestamp = date('Y_m_d_His');

        $filename = $timestamp . '_' . $migrationFile;
        $path = $basePath . '/' . $filename;

        $content = $this->getIdentityMigrationContent();
        Filesystem::put($path, $content);

        $this->info(" Created migration: {$filename}");
    }

    private function getIdentityMigrationContent(): string
    {
        return <<<'EOT'
<?php

declare(strict_types=1);

use Plugs\Database\Migration;
use Plugs\Database\Schema;
use Plugs\Database\Blueprint;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('public_key')->nullable()->after('password');
            $table->json('prompt_ids')->nullable()->after('public_key');
        });

        // The Plugs Blueprint currently doesn't have a ->change() method for altering
        // existing columns (like making password nullable). We use a raw query for this.
        Schema::raw("ALTER TABLE `users` MODIFY COLUMN `password` VARCHAR(255) NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['public_key', 'prompt_ids']);
        });

        // Revert password to NOT NULL (assumes standard VARCHAR(255))
        Schema::raw("ALTER TABLE `users` MODIFY COLUMN `password` VARCHAR(255) NOT NULL");
    }
};
EOT;
    }
}
