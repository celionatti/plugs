<div class="step-content">
    <h2 class="step-title">Welcome to Plugs Framework</h2>
    <p class="step-description">Let's check if your server meets the requirements to run Plugs.</p>

    <!-- Requirements List -->
    <h3 style="font-size: 1.1rem; margin-bottom: 1rem; color: var(--text-secondary);">Server Requirements</h3>
    <ul class="requirements-list">
        <?php
        $allPassed = true;
        foreach ($requirements as $key => $req):
            if ($req['required'] && !$req['passed']) {
                $allPassed = false;
            }
            ?>
            <li class="requirement-item">
                <span class="requirement-name">
                    <?= htmlspecialchars($req['name']) ?>
                </span>
                <span
                    class="requirement-status <?= $req['passed'] ? 'passed' : ($req['required'] ? 'failed' : 'optional') ?>">
                    <?= $req['passed'] ? '✓ ' . htmlspecialchars($req['current']) : ($req['required'] ? '✗ Required' : '○ Optional') ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (!$allPassed): ?>
        <div class="alert alert-error">
            <span class="alert-icon">⚠️</span>
            <span>Some required extensions are missing. Please install them before continuing.</span>
        </div>
    <?php endif; ?>

    <!-- License Agreement -->
    <h3 style="font-size: 1.1rem; margin: 2rem 0 1rem; color: var(--text-secondary);">License Agreement</h3>
    <div class="license-box">
        <strong>Apache License 2.0</strong><br><br>

        Copyright
        <?= date('Y') ?> Plugs Framework (Celio Natti)<br><br>

        Licensed under the Apache License, Version 2.0 (the "License");
        you may not use this file except in compliance with the License.
        You may obtain a copy of the License at<br><br>

        http://www.apache.org/licenses/LICENSE-2.0<br><br>

        Unless required by applicable law or agreed to in writing, software
        distributed under the License is distributed on an "AS IS" BASIS,
        WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
        See the License for the specific language governing permissions and
        limitations under the License.<br><br>

        <strong>What this means:</strong><br>
        • You can use this framework for personal and commercial projects<br>
        • You can modify and distribute the framework<br>
        • You must include the original license and copyright notice<br>
        • The framework is provided "as is" without warranty
    </div>

    <form method="post" action="?step=1">
        <label class="checkbox-group">
            <input type="checkbox" name="accept_license" value="1" class="checkbox-input" required <?= !$allPassed ? 'disabled' : '' ?>>
            <span class="checkbox-label">
                I have read and agree to the <a href="https://www.apache.org/licenses/LICENSE-2.0"
                    target="_blank">Apache License 2.0</a>
            </span>
        </label>

        <div class="btn-group">
            <button type="submit" class="btn btn-primary btn-full" <?= !$allPassed ? 'disabled' : '' ?>>
                Continue to Database Setup →
            </button>
        </div>
    </form>
</div>