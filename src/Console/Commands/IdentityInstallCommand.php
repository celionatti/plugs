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

        $this->task('Publishing identity_challenges table migration', function () {
            $this->publishMigration('create_identity_challenges_table.php', $this->getIdentityChallengesMigrationContent());
        });

        $this->task('Publishing device_tokens table migration', function () {
            $this->publishMigration('create_device_tokens_table.php', $this->getDeviceTokensMigrationContent());
        });

        $this->task('Publishing sessions table migration', function () {
            $this->publishMigration('create_sessions_table.php', $this->getSessionsMigrationContent());
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
        $basePath = base_path('database/migrations');

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

    private function publishMigration(string $migrationFile, string $content): void
    {
        $basePath = base_path('database/migrations');

        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }

        $files = glob($basePath . '/*_' . $migrationFile);
        if (!empty($files)) {
            $this->warning(" Migration [{$migrationFile}] already exists.");
            return;
        }

        sleep(1);
        $timestamp = date('Y_m_d_His');
        $filename = $timestamp . '_' . $migrationFile;
        $path = $basePath . '/' . $filename;

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

    private function getIdentityChallengesMigrationContent(): string
    {
        return <<<'EOT'
<?php

declare(strict_types=1);

use Plugs\Database\Migration;
use Plugs\Database\Blueprint;
use Plugs\Database\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('identity_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('nonce', 500);
            $table->boolean('used')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('identity_challenges');
    }
};
EOT;
    }

    private function getDeviceTokensMigrationContent(): string
    {
        return <<<'EOT'
<?php

declare(strict_types=1);

use Plugs\Database\Migration;
use Plugs\Database\Blueprint;
use Plugs\Database\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('token_hash')->unique();
            $table->string('device_name', 255)->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
EOT;
    }

    private function getSessionsMigrationContent(): string
    {
        return <<<'EOT'
<?php

declare(strict_types=1);

use Plugs\Database\Migration;
use Plugs\Database\Blueprint;
use Plugs\Database\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id', 255);
            $table->primary('id');
            $table->foreignId('user_id')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity');

            $table->index('user_id');
            $table->index('last_activity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
EOT;
    }
}
