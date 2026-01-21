<?php
$timezones = $stepData['config']['timezones'] ?? [];
$sessionApp = $stepData['session_data']['app'] ?? [];

// Try to detect the current URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$defaultUrl = $protocol . '://' . $host;
// Remove /install from URL if present
$defaultUrl = preg_replace('#/install/?$#', '', $defaultUrl);
?>
<div class="step-content">
    <h2 class="step-title">Application Settings</h2>
    <p class="step-description">Configure your application's basic settings.</p>

    <form method="post" action="?step=3">
        <div class="form-group">
            <label for="app_name" class="form-label">Application Name</label>
            <input type="text" name="app_name" id="app_name" class="form-input"
                value="<?= htmlspecialchars($sessionApp['name'] ?? 'My Plugs App') ?>" placeholder="My Plugs App"
                required>
            <div class="form-hint">This name will appear in the browser title and emails</div>
        </div>

        <div class="form-group">
            <label for="app_url" class="form-label">Application URL</label>
            <input type="url" name="app_url" id="app_url" class="form-input"
                value="<?= htmlspecialchars($sessionApp['url'] ?? $defaultUrl) ?>" placeholder="https://example.com"
                required>
            <div class="form-hint">The full URL where your application will be accessed (without trailing slash)</div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="app_env" class="form-label">Environment</label>
                <select name="app_env" id="app_env" class="form-select" required>
                    <option value="local" <?= ($sessionApp['env'] ?? 'local') === 'local' ? 'selected' : '' ?>>
                        Local (Development)
                    </option>
                    <option value="staging" <?= ($sessionApp['env'] ?? '') === 'staging' ? 'selected' : '' ?>>
                        Staging (Testing)
                    </option>
                    <option value="production" <?= ($sessionApp['env'] ?? '') === 'production' ? 'selected' : '' ?>>
                        Production (Live)
                    </option>
                </select>
                <div class="form-hint">Local shows detailed errors; production hides them</div>
            </div>

            <div class="form-group">
                <label for="app_timezone" class="form-label">Timezone</label>
                <select name="app_timezone" id="app_timezone" class="form-select" required>
                    <?php foreach ($timezones as $tz => $label): ?>
                        <option value="<?= $tz ?>" <?= ($sessionApp['timezone'] ?? 'UTC') === $tz ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="alert alert-success" style="margin-top: 1.5rem;">
            <span class="alert-icon">🔐</span>
            <span>A secure application key will be generated automatically.</span>
        </div>

        <div class="btn-group">
            <a href="?step=2" class="btn btn-secondary">← Back</a>
            <button type="submit" class="btn btn-primary">
                Continue to Admin Account →
            </button>
        </div>
    </form>
</div>