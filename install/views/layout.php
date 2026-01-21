<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Plugs Framework - Step
        <?= $step ?>
    </title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/install.css">
</head>

<body>
    <div class="installer-container">
        <!-- Header -->
        <header class="installer-header">
            <div class="brand">
                <span class="brand-icon">⚡</span>
                <span class="brand-text">Plugs</span>
            </div>
            <div class="header-subtitle">Framework Installation</div>
        </header>

        <!-- Progress Steps -->
        <div class="progress-container">
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= ($step / 5) * 100 ?>%"></div>
            </div>
            <div class="steps">
                <div class="step <?= $step >= 1 ? 'active' : '' ?> <?= $step > 1 ? 'completed' : '' ?>">
                    <div class="step-number">
                        <?= $step > 1 ? '✓' : '1' ?>
                    </div>
                    <div class="step-label">Requirements</div>
                </div>
                <div class="step <?= $step >= 2 ? 'active' : '' ?> <?= $step > 2 ? 'completed' : '' ?>">
                    <div class="step-number">
                        <?= $step > 2 ? '✓' : '2' ?>
                    </div>
                    <div class="step-label">Database</div>
                </div>
                <div class="step <?= $step >= 3 ? 'active' : '' ?> <?= $step > 3 ? 'completed' : '' ?>">
                    <div class="step-number">
                        <?= $step > 3 ? '✓' : '3' ?>
                    </div>
                    <div class="step-label">Settings</div>
                </div>
                <div class="step <?= $step >= 4 ? 'active' : '' ?> <?= $step > 4 ? 'completed' : '' ?>">
                    <div class="step-number">
                        <?= $step > 4 ? '✓' : '4' ?>
                    </div>
                    <div class="step-label">Admin</div>
                </div>
                <div class="step <?= $step >= 5 ? 'active' : '' ?>">
                    <div class="step-number">5</div>
                    <div class="step-label">Finish</div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <main class="installer-main">
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <span class="alert-icon">⚠️</span>
                    <span>
                        <?= htmlspecialchars($error) ?>
                    </span>
                </div>
            <?php endif; ?>

            <?= $content ?>
        </main>

        <!-- Footer -->
        <footer class="installer-footer">
            <p>&copy;
                <?= date('Y') ?> Plugs Framework. Created by <a href="https://github.com/celionatti"
                    target="_blank">Celio Natti</a>
            </p>
        </footer>
    </div>

    <script src="assets/install.js"></script>
</body>

</html>