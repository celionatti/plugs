<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;

class SecurityInstallCommand extends Command
{
    protected string $description = 'Publish the Security Shield (rate limiting, bot detection) database migrations';

    public function handle(): int
    {
        $this->title('Security Shield Scaffolding');

        $this->task('Publishing security_attempts table migration', function () {
            $this->publishMigration('create_security_attempts_table.php', $this->getSecurityAttemptsMigrationContent());
        });

        $this->task('Publishing security_logs table migration', function () {
            $this->publishMigration('create_security_logs_table.php', $this->getSecurityLogsMigrationContent());
        });

        $this->task('Publishing whitelisted_ips table migration', function () {
            $this->publishMigration('create_whitelisted_ips_table.php', $this->getWhitelistedIpsMigrationContent());
        });

        $this->task('Publishing blacklisted_ips table migration', function () {
            $this->publishMigration('create_blacklisted_ips_table.php', $this->getBlacklistedIpsMigrationContent());
        });

        $this->task('Publishing blocked_fingerprints table migration', function () {
            $this->publishMigration('create_blocked_fingerprints_table.php', $this->getBlockedFingerprintsMigrationContent());
        });

        $this->newLine();
        $this->box(
            "Security Shield scaffolding installed successfully!\n\n" .
            "Run 'php plugs migrate' to create the security tables.",
            "✅ Success",
            "success"
        );

        return 0;
    }

    private function publishMigration(string $migrationFile, string $content): void
    {
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

        // Wait a tiny bit to ensure unique timestamps
        usleep(100000); // 100ms
        $timestamp = date('Y_m_d_His');
        $filename = $timestamp . '_' . $migrationFile;
        $path = $basePath . '/' . $filename;

        Filesystem::put($path, $content);

        $this->info(" Created migration: {$filename}");
    }

    private function getSecurityAttemptsMigrationContent(): string
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
        Schema::create('security_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('identifier', 255);
            $table->string('type', 50); // 'ip' or 'email'
            $table->string('endpoint', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('identifier');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_attempts');
    }
};
EOT;
    }

    private function getSecurityLogsMigrationContent(): string
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
        Schema::create('security_logs', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45);
            $table->string('email', 255)->nullable();
            $table->string('endpoint', 255)->nullable();
            $table->decimal('risk_score', 3, 2)->default(0.00);
            $table->string('decision', 50)->default('allowed');
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('ip');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_logs');
    }
};
EOT;
    }

    private function getWhitelistedIpsMigrationContent(): string
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
        Schema::create('whitelisted_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->unique();
            $table->boolean('active')->default(1);
            $table->timestamp('created_at')->useCurrent();

            $table->index('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whitelisted_ips');
    }
};
EOT;
    }

    private function getBlacklistedIpsMigrationContent(): string
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
        Schema::create('blacklisted_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->unique();
            $table->text('reason')->nullable();
            $table->boolean('active')->default(1);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('active');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blacklisted_ips');
    }
};
EOT;
    }

    private function getBlockedFingerprintsMigrationContent(): string
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
        Schema::create('blocked_fingerprints', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 255)->unique();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blocked_fingerprints');
    }
};
EOT;
    }
}
