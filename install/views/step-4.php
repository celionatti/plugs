<?php
$sessionAdmin = $stepData['session_data']['admin'] ?? [];
?>
<div class="step-content">
    <h2 class="step-title">Create Admin Account</h2>
    <p class="step-description">Set up your administrator account to manage your application.</p>

    <form method="post" action="?step=4">
        <div class="form-group">
            <label for="admin_name" class="form-label">Full Name</label>
            <input type="text" name="admin_name" id="admin_name" class="form-input"
                value="<?= htmlspecialchars($sessionAdmin['name'] ?? '') ?>" placeholder="John Doe" required>
        </div>

        <div class="form-group">
            <label for="admin_email" class="form-label">Email Address</label>
            <input type="email" name="admin_email" id="admin_email" class="form-input"
                value="<?= htmlspecialchars($sessionAdmin['email'] ?? '') ?>" placeholder="admin@example.com" required>
            <div class="form-hint">This will be used to log in to the admin panel</div>
        </div>

        <div class="form-group">
            <label for="admin_password" class="form-label">Password</label>
            <input type="password" name="admin_password" id="admin_password" class="form-input"
                placeholder="Enter a strong password" minlength="8" required>
            <div class="password-strength">
                <div class="password-strength-fill"></div>
            </div>
            <div class="form-hint">Minimum 8 characters. Use a mix of letters, numbers, and symbols.</div>
        </div>

        <div class="form-group">
            <label for="admin_password_confirm" class="form-label">Confirm Password</label>
            <input type="password" name="admin_password_confirm" id="admin_password_confirm" class="form-input"
                placeholder="Confirm your password" required>
        </div>

        <div class="alert alert-warning" style="margin-top: 1.5rem;">
            <span class="alert-icon">💡</span>
            <span><strong>Tip:</strong> Save these credentials securely. You'll need them to access your
                application.</span>
        </div>

        <div class="btn-group">
            <a href="?step=3" class="btn btn-secondary">← Back</a>
            <button type="submit" class="btn btn-primary">
                Install Now →
            </button>
        </div>
    </form>
</div>