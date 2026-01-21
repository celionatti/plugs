/**
 * Plugs Framework Installer - JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {
    // Initialize all features
    initPasswordStrength();
    initDatabaseTypeChange();
    initTestConnection();
    initFormValidation();
});

/**
 * Password Strength Indicator
 */
function initPasswordStrength() {
    const passwordInput = document.getElementById('admin_password');
    const strengthBar = document.querySelector('.password-strength-fill');

    if (!passwordInput || !strengthBar) return;

    passwordInput.addEventListener('input', function () {
        const password = this.value;
        const strength = calculatePasswordStrength(password);

        strengthBar.className = 'password-strength-fill';

        if (password.length === 0) {
            strengthBar.style.width = '0';
        } else if (strength < 2) {
            strengthBar.classList.add('weak');
        } else if (strength < 3) {
            strengthBar.classList.add('fair');
        } else if (strength < 4) {
            strengthBar.classList.add('good');
        } else {
            strengthBar.classList.add('strong');
        }
    });
}

function calculatePasswordStrength(password) {
    let strength = 0;

    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    if (/\d/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;

    return strength;
}

/**
 * Database Type Change Handler
 */
function initDatabaseTypeChange() {
    const dbTypeSelect = document.getElementById('db_driver');
    const dbHostGroup = document.getElementById('db-host-group');
    const dbPortGroup = document.getElementById('db-port-group');
    const dbUsernameGroup = document.getElementById('db-username-group');
    const dbPasswordGroup = document.getElementById('db-password-group');
    const dbDatabaseGroup = document.getElementById('db-database-group');
    const portInput = document.getElementById('db_port');

    if (!dbTypeSelect) return;

    const defaultPorts = {
        'mysql': 3306,
        'pgsql': 5432,
        'sqlite': null
    };

    dbTypeSelect.addEventListener('change', function () {
        const driver = this.value;
        const isSqlite = driver === 'sqlite';

        // Toggle field visibility for SQLite
        if (dbHostGroup) dbHostGroup.style.display = isSqlite ? 'none' : '';
        if (dbPortGroup) dbPortGroup.style.display = isSqlite ? 'none' : '';
        if (dbUsernameGroup) dbUsernameGroup.style.display = isSqlite ? 'none' : '';
        if (dbPasswordGroup) dbPasswordGroup.style.display = isSqlite ? 'none' : '';

        // Update database label for SQLite
        if (dbDatabaseGroup) {
            const label = dbDatabaseGroup.querySelector('label');
            const hint = dbDatabaseGroup.querySelector('.form-hint');
            if (isSqlite && label) {
                label.textContent = 'Database File Path';
            } else if (label) {
                label.textContent = 'Database Name';
            }
            if (hint) {
                hint.textContent = isSqlite
                    ? 'Leave empty for default (storage/database.sqlite)'
                    : 'The name of the database to use';
            }
        }

        // Update port for database type
        if (portInput && defaultPorts[driver]) {
            portInput.value = defaultPorts[driver];
        }
    });
}

/**
 * Test Database Connection
 */
function initTestConnection() {
    const testBtn = document.getElementById('test-connection-btn');
    const statusEl = document.getElementById('connection-status');

    if (!testBtn) return;

    testBtn.addEventListener('click', async function (e) {
        e.preventDefault();

        const originalText = testBtn.innerHTML;
        testBtn.innerHTML = '<span class="spinner"></span> Testing...';
        testBtn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'test_connection');
        formData.append('db_driver', document.getElementById('db_driver')?.value || 'mysql');
        formData.append('db_host', document.getElementById('db_host')?.value || 'localhost');
        formData.append('db_port', document.getElementById('db_port')?.value || '3306');
        formData.append('db_database', document.getElementById('db_database')?.value || '');
        formData.append('db_username', document.getElementById('db_username')?.value || '');
        formData.append('db_password', document.getElementById('db_password')?.value || '');

        try {
            const response = await fetch('test-connection.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (statusEl) {
                statusEl.className = 'connection-status ' + (result.success ? 'success' : 'error');
                statusEl.innerHTML = result.success
                    ? '✓ Connection successful!'
                    : '✗ ' + (result.error || 'Connection failed');
            }
        } catch (error) {
            if (statusEl) {
                statusEl.className = 'connection-status error';
                statusEl.innerHTML = '✗ Connection test failed';
            }
        }

        testBtn.innerHTML = originalText;
        testBtn.disabled = false;
    });
}

/**
 * Form Validation
 */
function initFormValidation() {
    const forms = document.querySelectorAll('form');

    forms.forEach(form => {
        form.addEventListener('submit', function (e) {
            const submitBtn = form.querySelector('button[type="submit"]');

            if (submitBtn && !submitBtn.disabled) {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="spinner"></span> Processing...';
                submitBtn.disabled = true;

                // Re-enable after timeout (in case of error)
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 10000);
            }
        });
    });

    // Password confirmation validation
    const passwordConfirm = document.getElementById('admin_password_confirm');
    const password = document.getElementById('admin_password');

    if (passwordConfirm && password) {
        passwordConfirm.addEventListener('input', function () {
            if (this.value !== password.value) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        password.addEventListener('input', function () {
            if (passwordConfirm.value && passwordConfirm.value !== this.value) {
                passwordConfirm.setCustomValidity('Passwords do not match');
            } else {
                passwordConfirm.setCustomValidity('');
            }
        });
    }
}

/**
 * Copy to Clipboard
 */
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        // Show brief feedback
        const toast = document.createElement('div');
        toast.className = 'alert alert-success';
        toast.style.cssText = 'position: fixed; bottom: 20px; right: 20px; z-index: 1000; padding: 0.75rem 1rem;';
        toast.textContent = 'Copied to clipboard!';
        document.body.appendChild(toast);

        setTimeout(() => toast.remove(), 2000);
    });
}
