<?php
$dbTypes = $stepData['config']['database_types'] ?? [];
$sessionDb = $stepData['session_data']['database'] ?? [];
?>
<div class="step-content">
    <h2 class="step-title">Database Configuration</h2>
    <p class="step-description">Configure your database connection. We'll test it before proceeding.</p>

    <form method="post" action="?step=2">
        <div class="form-group">
            <label for="db_driver" class="form-label">Database Type</label>
            <select name="db_driver" id="db_driver" class="form-select" required>
                <?php foreach ($dbTypes as $driver => $info): ?>
                    <option value="<?= $driver ?>" <?= ($sessionDb['driver'] ?? 'mysql') === $driver ? 'selected' : '' ?>>
                        <?= htmlspecialchars($info['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row" id="db-host-group">
            <div class="form-group">
                <label for="db_host" class="form-label">Host</label>
                <input type="text" name="db_host" id="db_host" class="form-input"
                    value="<?= htmlspecialchars($sessionDb['host'] ?? 'localhost') ?>" placeholder="localhost">
            </div>
            <div class="form-group" id="db-port-group">
                <label for="db_port" class="form-label">Port</label>
                <input type="number" name="db_port" id="db_port" class="form-input"
                    value="<?= htmlspecialchars($sessionDb['port'] ?? '3306') ?>" placeholder="3306">
            </div>
        </div>

        <div class="form-group" id="db-database-group">
            <label for="db_database" class="form-label">Database Name</label>
            <input type="text" name="db_database" id="db_database" class="form-input"
                value="<?= htmlspecialchars($sessionDb['database'] ?? '') ?>" placeholder="my_database" required>
            <div class="form-hint">The name of the database to use</div>
        </div>

        <div class="form-group" id="db-username-group">
            <label for="db_username" class="form-label">Username</label>
            <input type="text" name="db_username" id="db_username" class="form-input"
                value="<?= htmlspecialchars($sessionDb['username'] ?? 'root') ?>" placeholder="root">
        </div>

        <div class="form-group" id="db-password-group">
            <label for="db_password" class="form-label">Password</label>
            <input type="password" name="db_password" id="db_password" class="form-input"
                value="<?= htmlspecialchars($sessionDb['password'] ?? '') ?>" placeholder="Enter database password">
        </div>

        <button type="button" id="test-connection-btn" class="test-connection-btn">
            🔌 Test Connection
        </button>
        <div id="connection-status" class="connection-status"></div>

        <div class="btn-group">
            <a href="?step=1" class="btn btn-secondary">← Back</a>
            <button type="submit" class="btn btn-primary">
                Continue to App Settings →
            </button>
        </div>
    </form>
</div>