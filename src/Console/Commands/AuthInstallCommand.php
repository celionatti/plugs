<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;

class AuthInstallCommand extends Command
{
    protected string $description = 'Publish the authentication database migrations';

    public function handle(): int
    {
        $this->title('Authentication Scaffolding');

        $this->task('Publishing users migration', function () {
            $this->publishUsersMigration();
        });

        $this->task('Publishing password_reset_tokens migration', function () {
            $this->publishPasswordResetTokensMigration();
        });

        $this->task('Publishing personal_access_tokens migration', function () {
            $this->publishPersonalAccessTokensMigration();
        });

        $this->task('Publishing sessions migration', function () {
            $this->publishSessionsMigration();
        });

        $this->newLine();
        $this->box(
            "Auth scaffolding installed successfully!\n\n" .
            "Run 'php plugs migrate' to create the tables.",
            "✅ Success",
            "success"
        );

        return 0;
    }

    private function publishUsersMigration(): void
    {
        $migrationFile = 'create_users_table.php';
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

        $timestamp = date('Y_m_d_His');
        $filename = $timestamp . '_' . $migrationFile;
        $path = $basePath . '/' . $filename;

        $content = $this->getUsersMigrationContent();
        Filesystem::put($path, $content);

        $this->info(" Created migration: {$filename}");
        sleep(1); // Ensure unique timestamp for next migration
    }

    private function publishPasswordResetTokensMigration(): void
    {
        $migrationFile = 'create_password_reset_tokens_table.php';
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

        $timestamp = date('Y_m_d_His');
        $filename = $timestamp . '_' . $migrationFile;
        $path = $basePath . '/' . $filename;

        $content = $this->getPasswordResetTokensMigrationContent();
        Filesystem::put($path, $content);

        $this->info(" Created migration: {$filename}");
        sleep(1); // Ensure unique timestamp for next migration
    }

    private function publishPersonalAccessTokensMigration(): void
    {
        $migrationFile = 'create_personal_access_tokens_table.php';
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

        $timestamp = date('Y_m_d_His');
        $filename = $timestamp . '_' . $migrationFile;
        $path = $basePath . '/' . $filename;

        $content = $this->getPersonalAccessTokensMigrationContent();
        Filesystem::put($path, $content);

        $this->info(" Created migration: {$filename}");
    }

    private function getPersonalAccessTokensMigrationContent(): string
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
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type');
            $table->unsignedBigInteger('tokenable_id');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tokenable_type', 'tokenable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
EOT;
    }

    private function getUsersMigrationContent(): string
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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('remember_token', 100)->nullable();
            $table->string('avatar')->nullable();
            $table->text('bio')->nullable();
            $table->enum('role', ['user', 'admin', 'editor'])->default('user');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('role');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
EOT;
    }

    private function publishSessionsMigration(): void
    {
        $migrationFile = 'create_sessions_table.php';
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

        $timestamp = date('Y_m_d_His');
        $filename = $timestamp . '_' . $migrationFile;
        $path = $basePath . '/' . $filename;

        $content = $this->getSessionsMigrationContent();
        Filesystem::put($path, $content);

        $this->info(" Created migration: {$filename}");
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

    private function getPasswordResetTokensMigrationContent(): string
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
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('expires_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
EOT;
    }
}
