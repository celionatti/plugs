<?php
$appName = $stepData['session_data']['app']['name'] ?? 'Plugs Framework';
$appUrl = $stepData['session_data']['app']['url'] ?? '';
?>
<div class="step-content" style="text-align: center;">
    <div class="success-icon">
        ✓
    </div>

    <h2 class="success-title">Installation Complete!</h2>
    <p class="success-message">
        Congratulations!
        <?= htmlspecialchars($appName) ?> has been successfully installed.
    </p>

    <div class="warning-box">
        <span class="warning-icon">⚠️</span>
        <div class="warning-text">
            <strong>Security Notice:</strong> For security reasons, please delete the
            <code style="background: rgba(0,0,0,0.2); padding: 2px 6px; border-radius: 4px;">install</code>
            folder from your server immediately.
            <div class="code-block" style="margin-top: 0.5rem; text-align: left;">
                rm -rf
                <?= htmlspecialchars(dirname(INSTALL_PATH)) ?>/install
            </div>
        </div>
    </div>

    <div
        style="background: var(--bg-input); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; margin: 2rem 0; text-align: left;">
        <h3 style="font-size: 1rem; margin-bottom: 1rem; color: var(--text-secondary);">📋 Quick Start</h3>
        <ul style="list-style: none; color: var(--text-muted); font-size: 0.95rem; line-height: 2;">
            <li>✅ Application files created</li>
            <li>✅ Database tables initialized</li>
            <li>✅ Admin account created</li>
            <li>✅ Configuration files generated</li>
        </ul>
        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
            <p style="color: var(--text-dim); font-size: 0.9rem; margin-bottom: 0.5rem;">
                <strong>Next Steps:</strong>
            </p>
            <ol style="color: var(--text-muted); font-size: 0.9rem; padding-left: 1.25rem; line-height: 1.8;">
                <li>Delete the <code>install</code> folder</li>
                <li>Run <code>composer install</code> to install dependencies</li>
                <li>Visit your homepage and start building!</li>
            </ol>
        </div>
    </div>

    <div class="btn-group" style="justify-content: center;">
        <a href="<?= htmlspecialchars($appUrl) ?>/" class="btn btn-primary">
            🏠 Go to Homepage
        </a>
    </div>

    <p style="margin-top: 2rem; color: var(--text-dim); font-size: 0.9rem;">
        Need help? Check out the
        <a href="https://github.com/celionatti/plugs" target="_blank"
            style="color: var(--accent-primary);">documentation</a>.
    </p>
</div>