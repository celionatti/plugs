<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Traits\RegistersModules;

class AuthInstallCommand extends Command
{
    use RegistersModules;
    protected string $description = 'Publish the authentication scaffolding and migrations';

    protected function defineOptions(): array
    {
        return [
            '--force' => 'Overwrite existing User model if it exists',
            '--no-migrate' => 'Skip running migrations',
        ];
    }

    public function handle(): int
    {
        $this->title('Authentication Scaffolding');

        $this->task('Publishing users migration', function () {
            $this->publishUsersMigration();
        });

        $this->task('Publishing password_reset_tokens migration', function () {
            $this->publishPasswordResetTokensMigration();
        });

        $this->task('Publishing email_verification_tokens migration', function () {
            $this->publishEmailVerificationTokensMigration();
        });

        $this->task('Publishing personal_access_tokens migration', function () {
            $this->publishPersonalAccessTokensMigration();
        });

        $this->task('Publishing sessions migration', function () {
            $this->publishSessionsMigration();
        });

        $this->task('Publishing User model', function () {
            $this->publishUserModel();
        });

        $this->task('Publishing GuestMiddleware', function () {
            $this->publishGuestMiddleware();
        });

        if (!$this->hasOption('no-migrate')) {
            $this->task('Running database migrations', function () {
                $this->call('migrate');
                return true;
            });
        }

        $this->newLine();
        $this->note("The Authentication module has been automatically registered in config/modules.php.");

        // Register the module in the config file
        $this->registerModuleInConfig('Auth');

        return 0;
    }

    private function publishUserModel(): void
    {
        $stubFile = __DIR__ . '/../Stubs/Auth/Models/User.stub';
        $targetDir = getcwd() . '/modules/Auth/Models';
        $destination = $targetDir . '/User.php';

        Filesystem::ensureDir($targetDir);

        if (Filesystem::exists($destination) && !$this->hasOption('force')) {
            $this->warning(" Model [User.php] already exists.");
            return;
        }

        if (Filesystem::exists($stubFile)) {
            $content = Filesystem::get($stubFile);
            Filesystem::put($destination, $content);
            $this->info(" Created model: User.php");
        }
    }

    private function publishGuestMiddleware(): void
    {
        $stubFile = __DIR__ . '/../Stubs/Auth/Middleware/GuestMiddleware.stub';
        $targetDir = getcwd() . '/modules/Auth/Middleware';
        $destination = $targetDir . '/GuestMiddleware.php';

        Filesystem::ensureDir($targetDir);

        if (Filesystem::exists($destination) && !$this->hasOption('force')) {
            $this->warning(" Middleware [GuestMiddleware.php] already exists.");
            return;
        }

        if (Filesystem::exists($stubFile)) {
            $content = Filesystem::get($stubFile);
            Filesystem::put($destination, $content);
            $this->info(" Created middleware: GuestMiddleware.php");
        }
    }

    private function publishUsersMigration(): void
    {
        $migrationFile = 'auth_scaffold_users_table.php';
        $basePath = getcwd() . '/modules/Auth/Migrations';

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
        $basePath = getcwd() . '/modules/Auth/Migrations';

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
        $basePath = getcwd() . '/modules/Auth/Migrations';

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
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'email_verified_at')) {
                    $table->timestamp('email_verified_at')->nullable();
                }
                if (!Schema::hasColumn('users', 'password')) {
                    $table->string('password');
                }
                if (!Schema::hasColumn('users', 'remember_token')) {
                    $table->string('remember_token', 100)->nullable();
                }
                if (!Schema::hasColumn('users', 'google_id')) {
                    $table->string('google_id')->nullable()->after('remember_token');
                    $table->index('google_id');
                }
                if (!Schema::hasColumn('users', 'avatar')) {
                    $table->string('avatar')->nullable();
                }
                if (!Schema::hasColumn('users', 'bio')) {
                    $table->text('bio')->nullable();
                }
                if (!Schema::hasColumn('users', 'role')) {
                    $table->enum('role', ['user', 'admin', 'editor'])->default('user');
                    $table->index('role');
                }
                if (!Schema::hasColumn('users', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                    $table->index('is_active');
                }
                if (!Schema::hasColumn('users', 'last_login_at')) {
                    $table->timestamp('last_login_at')->nullable();
                }
                if (!Schema::hasColumn('users', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        } else {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('remember_token', 100)->nullable();
                $table->string('google_id')->nullable();
                $table->string('avatar')->nullable();
                $table->text('bio')->nullable();
                $table->enum('role', ['user', 'admin', 'editor'])->default('user');
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_login_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('role');
                $table->index('is_active');
                $table->index('google_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // For safety, we drop the specific columns added if the table already existed, 
        // to prevent wiping the base users table. However, since we can't easily know 
        // if we created or modified it in down(), we do not drop the users table by default 
        // if this was an update. If it's a completely generated table, safe to drop.
        // As a compromise, we just leave the table.
        // Schema::dropIfExists('users'); 
    }
};
EOT;
    }

    private function publishSessionsMigration(): void
    {
        $migrationFile = 'create_sessions_table.php';
        $basePath = getcwd() . '/modules/Auth/Migrations';

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

    private function publishEmailVerificationTokensMigration(): void
    {
        $migrationFile = 'create_email_verification_tokens_table.php';
        $basePath = getcwd() . '/modules/Auth/Migrations';

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

        $content = $this->getEmailVerificationTokensMigrationContent();
        Filesystem::put($path, $content);

        $this->info(" Created migration: {$filename}");
        sleep(1);
    }

    private function getEmailVerificationTokensMigrationContent(): string
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
        Schema::create('email_verification_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('token', 10);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('expires_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_verification_tokens');
    }
};
EOT;
    }
}
