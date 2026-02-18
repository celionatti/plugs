<?php $__extends = 'debug.layout'; ?>
<?php /** @var array $profile */ ?>

<?php
function getMethodClass($method)
{
    return 'plugs-dbg-method-' . strtoupper($method);
}
?>

<div class="plugs-dbg-animate-fade-up">
    <a href="/debug/performance" class="plugs-dbg-btn plugs-dbg-btn-glass" style="margin-bottom: 2rem;">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18">
            </path>
        </svg>
        Back to Requests
    </a>

    <div class="plugs-dbg-glass-panel" style="padding: 2.5rem; margin-bottom: 2rem;">
        <div
            style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1.5rem;">
            <div style="flex: 1; min-width: 300px;">
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                    <span class="plugs-dbg-method-label <?= getMethodClass($profile['request']['method']) ?>"
                        style="font-size: 1rem; padding: 0.4rem 0.8rem;">
                        <?= $profile['request']['method'] ?>
                    </span>
                    <span style="font-size: 0.9rem; color: var(--text-muted);"><?= $profile['datetime'] ?></span>
                </div>
                <h1
                    style="font-size: 2rem; letter-spacing: -0.02em; word-break: break-all; margin-bottom: 0.5rem; font-family: 'JetBrains Mono', monospace;">
                    <?= $profile['request']['path'] ?>
                </h1>
                <div style="display: flex; gap: 1.5rem; color: var(--text-secondary); font-size: 0.9rem;">
                    <span>IP: <code style="color: var(--info);"><?= $profile['request']['ip'] ?></code></span>
                    <?php if (!empty($profile['request']['route'])): ?>
                        <span>Route: <code style="color: var(--accent);"><?= $profile['request']['route'] ?></code></span>
                    <?php endif; ?>
                </div>
            </div>

            <div style="text-align: right;">
                <div class="plugs-dbg-status-badge <?= $profile['request']['status_code'] < 400 ? 'plugs-dbg-status-success' : 'plugs-dbg-status-danger' ?>"
                    style="font-size: 1.5rem; padding: 0.5rem 1.5rem; border: 1px solid var(--border-glass); border-radius: 12px; background: rgba(0,0,0,0.2);">
                    <?= $profile['request']['status_code'] ?>
                </div>
            </div>
        </div>
    </div>

    <div class="plugs-dbg-stat-grid"
        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <div class="plugs-dbg-glass-panel plugs-dbg-stat-card-premium">
            <div class="plugs-dbg-stat-icon" style="background: rgba(168, 85, 247, 0.1); color: var(--accent);">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div>
                <div class="plugs-dbg-stat-val"><?= number_format($profile['duration'], 2) ?> <small>ms</small></div>
                <div class="plugs-dbg-stat-lab">Total Duration</div>
            </div>
        </div>

        <div class="plugs-dbg-glass-panel plugs-dbg-stat-card-premium">
            <div class="plugs-dbg-stat-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--secondary);">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                    </path>
                </svg>
            </div>
            <div>
                <div class="plugs-dbg-stat-val"><?= $profile['memory_peak_formatted'] ?></div>
                <div class="plugs-dbg-stat-lab">Peak Memory</div>
            </div>
        </div>

        <div class="plugs-dbg-glass-panel plugs-dbg-stat-card-premium">
            <div class="plugs-dbg-stat-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4">
                    </path>
                </svg>
            </div>
            <div>
                <div class="plugs-dbg-stat-val"><?= $profile['database']['total_queries'] ?? 0 ?></div>
                <div class="plugs-dbg-stat-lab">DB Queries</div>
            </div>
        </div>
    </div>

    <div class="plugs-dbg-glass-panel" style="overflow: hidden;">
        <div class="plugs-dbg-tabs-header">
            <button class="plugs-dbg-tab-btn active" data-tab="queries">
                <svg class="plugs-dbg-tab-icon" width="18" height="18" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4">
                    </path>
                </svg>
                DATABASE_QUERIES_SPAN
                <span class="plugs-dbg-tab-badge"><?= count($profile['database']['most_frequent'] ?? []) ?></span>
            </button>
            <button class="plugs-dbg-tab-btn" data-tab="params">
                <svg class="plugs-dbg-tab-icon" width="18" height="18" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                Request Params
            </button>
            <button class="plugs-dbg-tab-btn" data-tab="analysis">
                <svg class="plugs-dbg-tab-icon" width="18" height="18" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                    </path>
                </svg>
                Performance Insight
            </button>
            <button class="plugs-dbg-tab-btn" data-tab="config-tab">
                <svg class="plugs-dbg-tab-icon" width="18" height="18" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                    </path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                System Config
            </button>
        </div>

        <div id="queries" class="plugs-dbg-tab-content active">
            <?php if (empty($profile['database']['most_frequent'])): ?>
                <div style="padding: 5rem 2rem; text-align: center; opacity: 0.6;">
                    <p>No database queries were executed during this request.</p>
                </div>
            <?php else: ?>
                <table class="plugs-dbg-table">
                    <thead>
                        <tr>
                            <th style="width: 60%">Query Pattern</th>
                            <th style="text-align: center;">Hits</th>
                            <th>Total Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($profile['database']['most_frequent'] as $sql => $stats): ?>
                            <tr>
                                <td>
                                    <div class="plugs-dbg-code-pattern" style="position: relative;">
                                        <code
                                            id="sql-<?= md5($sql) ?>"><?= htmlspecialchars(substr($sql, 0, 160)) . (strlen($sql) > 160 ? '...' : '') ?></code>
                                        <?php if (strlen($sql) > 160): ?>
                                            <button data-action="show-sql" data-id="<?= md5($sql) ?>"
                                                data-sql="<?= htmlspecialchars($sql, ENT_QUOTES) ?>" class="plugs-dbg-btn-view-full"
                                                title="View Full Query">
                                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="text-align: center;"><span class="plugs-dbg-hit-count"><?= $stats['count'] ?></span>
                                </td>
                                <td><span style="font-weight: 600;"><?= number_format($stats['total_time'] * 1000, 2) ?></span>
                                    <small>ms</small>
                                </td>
                                <td>
                                    <?php if ($stats['max_time'] > 0.05): ?>
                                        <span class="plugs-dbg-badge"
                                            style="background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2);">Slow</span>
                                    <?php else: ?>
                                        <span class="plugs-dbg-badge"
                                            style="background: rgba(16, 185, 129, 0.1); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.2);">Optimal</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div id="params" class="plugs-dbg-tab-content" style="padding: 2rem;">
            <div class="plugs-dbg-param-section">
                <h3
                    style="font-size: 1rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">
                    GET Parameters</h3>
                <?php if (empty($profile['request']['params'])): ?>
                    <p style="color: var(--text-muted); font-style: italic;">No parameters.</p>
                <?php else: ?>
                    <pre
                        class="plugs-dbg-json-preview"><?= json_encode($profile['request']['params'], JSON_PRETTY_PRINT) ?></pre>
                <?php endif; ?>
            </div>

            <div class="plugs-dbg-param-section" style="margin-top: 2.5rem;">
                <h3
                    style="font-size: 1rem; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">
                    POST Body</h3>
                <?php if (empty($profile['request']['body'])): ?>
                    <p style="color: var(--text-muted); font-style: italic;">Empty body.</p>
                <?php else: ?>
                    <pre
                        class="plugs-dbg-json-preview"><?= json_encode($profile['request']['body'], JSON_PRETTY_PRINT) ?></pre>
                <?php endif; ?>
            </div>
        </div>

        <div id="analysis" class="plugs-dbg-tab-content" style="padding: 2.5rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                <?php if (!empty($profile['database']['slow_queries'])): ?>
                    <div class="plugs-dbg-analysis-card danger">
                        <h3>Slow Queries</h3>
                        <ul>
                            <?php foreach ($profile['database']['slow_queries'] as $slow): ?>
                                <li>
                                    <div style="color: var(--danger); font-weight: 700; margin-bottom: 0.3rem;">
                                        <?= number_format($slow['max_time'] * 1000, 2) ?>ms
                                    </div>
                                    <code><?= htmlspecialchars($slow['query']) ?></code>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($profile['database']['n_plus_one_suspects'])): ?>
                    <div class="plugs-dbg-analysis-card warning">
                        <h3>Potential N+1 Detection</h3>
                        <ul>
                            <?php foreach ($profile['database']['n_plus_one_suspects'] as $suspect): ?>
                                <li>
                                    <div style="color: var(--warning); font-weight: 700; margin-bottom: 0.3rem;">Repeated
                                        <?= $suspect['count'] ?> times
                                    </div>
                                    <code><?= htmlspecialchars($suspect['query']) ?></code>
                                    <div style="margin-top: 0.5rem; font-size: 0.75rem; color: var(--text-muted);">
                                        Caught in: <?= implode(', ', array_map('basename', $suspect['locations'] ?? [])) ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (empty($profile['database']['slow_queries']) && empty($profile['database']['n_plus_one_suspects'])): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 3rem;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">🚀</div>
                        <h2>No performance bottlenecks detected.</h2>
                        <p style="color: var(--text-muted);">This request performed well within the expected limits.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="config-tab" class="plugs-dbg-tab-content" style="padding: 2rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 2rem;">
                <div class="plugs-dbg-glass-panel" style="padding: 1.5rem; background: rgba(0,0,0,0.2);">
                    <h3
                        style="font-size: 1rem; margin-bottom: 1.5rem; color: var(--accent); display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Environment Info
                    </h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr style="border-bottom: 1px solid var(--border-glass);">
                            <td style="padding: 0.75rem 0; color: var(--text-muted); font-size: 0.9rem;">Application
                                Name</td>
                            <td style="padding: 0.75rem 0; text-align: right; font-weight: 600;">
                                <?= config('app.name', 'Plugs App') ?>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-glass);">
                            <td style="padding: 0.75rem 0; color: var(--text-muted); font-size: 0.9rem;">Environment
                            </td>
                            <td style="padding: 0.75rem 0; text-align: right;">
                                <span class="plugs-dbg-badge"
                                    style="background: rgba(168, 85, 247, 0.1); color: var(--accent);"><?= config('app.env', 'local') ?></span>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-glass);">
                            <td style="padding: 0.75rem 0; color: var(--text-muted); font-size: 0.9rem;">Debug Mode</td>
                            <td style="padding: 0.75rem 0; text-align: right;">
                                <span class="plugs-dbg-badge"
                                    style="<?= config('app.debug') ? 'background: rgba(16, 185, 129, 0.1); color: var(--success);' : 'background: rgba(239, 68, 68, 0.1); color: var(--danger);' ?>">
                                    <?= config('app.debug') ? 'Enabled' : 'Disabled' ?>
                                </span>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-glass);">
                            <td style="padding: 0.75rem 0; color: var(--text-muted); font-size: 0.9rem;">Timezone</td>
                            <td
                                style="padding: 0.75rem 0; text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 0.85rem;">
                                <?= config('app.timezone', 'UTC') ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 0.75rem 0; color: var(--text-muted); font-size: 0.9rem;">URL</td>
                            <td style="padding: 0.75rem 0; text-align: right; color: var(--info); font-size: 0.85rem;">
                                <?= config('app.url') ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="plugs-dbg-glass-panel" style="padding: 1.5rem; background: rgba(0,0,0,0.2);">
                    <h3
                        style="font-size: 1rem; margin-bottom: 1.5rem; color: var(--secondary); display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01">
                            </path>
                        </svg>
                        Server Stack
                    </h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr style="border-bottom: 1px solid var(--border-glass);">
                            <td style="padding: 0.75rem 0; color: var(--text-muted); font-size: 0.9rem;">PHP Version
                            </td>
                            <td
                                style="padding: 0.75rem 0; text-align: right; font-family: 'JetBrains Mono', monospace;">
                                <?= PHP_VERSION ?>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-glass);">
                            <td style="padding: 0.75rem 0; color: var(--text-muted); font-size: 0.9rem;">SAPI</td>
                            <td style="padding: 0.75rem 0; text-align: right;"><?= PHP_SAPI ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-glass);">
                            <td style="padding: 0.75rem 0; color: var(--text-muted); font-size: 0.9rem;">OS</td>
                            <td style="padding: 0.75rem 0; text-align: right;"><?= PHP_OS ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 0.75rem 0; color: var(--text-muted); font-size: 0.9rem;">Memory Limit
                            </td>
                            <td
                                style="padding: 0.75rem 0; text-align: right; font-family: 'JetBrains Mono', monospace;">
                                <?= ini_get('memory_limit') ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- SQL Full View Modal -->
    <div class="plugs-dbg-modal-overlay" id="plugs-sql-modal">
        <div class="plugs-dbg-glass-panel plugs-dbg-modal-content">
            <div class="plugs-dbg-modal-header">
                <h3 style="margin: 0; color: var(--accent);">Full SQL Query Pattern</h3>
                <button class="plugs-dbg-modal-close" onclick="closeSqlModal()">&times;</button>
            </div>
            <div class="plugs-dbg-modal-body">
                <pre class="plugs-dbg-json-preview"
                    style="background: rgba(0,0,0,0.4);"><code id="modal-sql-content"></code></pre>
            </div>
            <div class="plugs-dbg-modal-footer" style="gap: 1rem;">
                <button class="plugs-dbg-btn plugs-dbg-btn-glass" onclick="copySqlFromModal()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        style="margin-right: 0.4rem;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                    </svg>
                    Copy SQL
                </button>
                <button class="plugs-dbg-btn plugs-dbg-btn-glass" onclick="closeSqlModal()">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    function closeSqlModal() {
        document.getElementById('plugs-sql-modal').classList.remove('active');
    }

    function copySqlFromModal() {
        const sql = document.getElementById('modal-sql-content').textContent;
        navigator.clipboard.writeText(sql).then(() => {
            const btn = event.currentTarget;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span style="color: var(--success)">✓ Copied!</span>';
            setTimeout(() => btn.innerHTML = originalText, 2000);
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-action="show-sql"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const sql = btn.getAttribute('data-sql');
                document.getElementById('modal-sql-content').textContent = sql;
                document.getElementById('plugs-sql-modal').classList.add('active');
                e.preventDefault();
            });
        });

        // Close modal on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeSqlModal();
        });

        // Close modal on click outside
        document.getElementById('plugs-sql-modal').addEventListener('click', (e) => {
            if (e.target.id === 'plugs-sql-modal') closeSqlModal();
        });
    });
</script>

<style>
    .plugs-dbg-btn-view-full {
        position: absolute;
        right: 0.5rem;
        top: 50%;
        transform: translateY(-50%);
        background: var(--bg-deep);
        border: 1px solid var(--border-glass);
        color: var(--text-muted);
        padding: 0.3rem;
        border-radius: 4px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .plugs-dbg-btn-view-full:hover {
        color: var(--accent);
        border-color: var(--accent);
        background: rgba(168, 85, 247, 0.1);
    }

    /* Modal Styling */
    .plugs-dbg-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(8px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        padding: 2rem;
    }

    .plugs-dbg-modal-overlay.active {
        display: flex;
    }

    .plugs-dbg-modal-content {
        max-width: 800px;
        width: 100%;
        max-height: 80vh;
        display: flex;
        flex-direction: column;
        padding: 0 !important;
        overflow: hidden;
    }

    .plugs-dbg-modal-header {
        padding: 1.5rem 2rem;
        border-bottom: 1px solid var(--border-glass);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .plugs-dbg-modal-close {
        background: none;
        border: none;
        color: var(--text-muted);
        font-size: 1.5rem;
        cursor: pointer;
    }

    .plugs-dbg-modal-body {
        padding: 2rem;
        overflow-y: auto;
        flex: 1;
    }

    .plugs-dbg-modal-footer {
        padding: 1rem 2rem;
        border-top: 1px solid var(--border-glass);
        display: flex;
        justify-content: flex-end;
    }

    .plugs-dbg-stat-card-premium {
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1.25rem;
    }

    .plugs-dbg-stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .plugs-dbg-stat-val {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--text-primary);
        line-height: 1;
    }

    .plugs-dbg-stat-val small {
        font-size: 0.8rem;
        color: var(--text-muted);
        font-weight: 400;
    }

    .plugs-dbg-stat-lab {
        font-size: 0.75rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 600;
        margin-top: 0.2rem;
    }

    .plugs-dbg-tab-badge {
        background: rgba(255, 255, 255, 0.1);
        color: var(--text-primary);
        font-size: 0.7rem;
        padding: 0.1rem 0.4rem;
        border-radius: 4px;
        margin-left: 0.5rem;
    }

    .plugs-dbg-code-pattern {
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.85rem;
        color: var(--text-secondary);
        background: rgba(0, 0, 0, 0.3);
        padding: 0.5rem;
        border-radius: 6px;
    }

    .plugs-dbg-hit-count {
        background: var(--accent);
        color: white;
        padding: 0.2rem 0.6rem;
        border-radius: 99px;
        font-weight: 800;
        font-size: 0.8rem;
    }

    .plugs-dbg-json-preview {
        background: rgba(0, 0, 0, 0.4);
        padding: 1.5rem;
        border-radius: 12px;
        border: 1px solid var(--border-glass);
        color: #cbd5e1;
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.85rem;
        overflow-x: auto;
    }

    .plugs-dbg-analysis-card {
        background: rgba(0, 0, 0, 0.2);
        border-radius: 16px;
        padding: 1.5rem;
        border: 1px solid var(--border-glass);
    }

    .plugs-dbg-analysis-card.danger {
        border-left: 4px solid var(--danger);
    }

    .plugs-dbg-analysis-card.warning {
        border-left: 4px solid var(--warning);
    }

    .plugs-dbg-analysis-card h3 {
        font-size: 1.1rem;
        margin-bottom: 1.5rem;
    }

    .plugs-dbg-analysis-card ul {
        list-style: none;
    }

    .plugs-dbg-analysis-card li {
        margin-bottom: 1.5rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid var(--border-glass);
    }

    .plugs-dbg-analysis-card li:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .plugs-dbg-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-weight: 700;
        font-size: 0.85rem;
    }

    .plugs-dbg-status-badge::before {
        content: '';
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }

    .plugs-dbg-status-success {
        color: var(--success);
    }

    .plugs-dbg-status-success::before {
        background: var(--success);
        box-shadow: 0 0 8px var(--success);
    }

    .plugs-dbg-status-danger {
        color: var(--danger);
    }

    .plugs-dbg-status-danger::before {
        background: var(--danger);
        box-shadow: 0 0 8px var(--danger);
    }

    .plugs-dbg-method-label {
        font-family: 'JetBrains Mono', monospace;
        font-weight: 700;
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
    }

    .plugs-dbg-method-GET {
        color: var(--info);
        background: rgba(14, 165, 233, 0.1);
    }

    .plugs-dbg-method-POST {
        color: var(--success);
        background: rgba(16, 185, 129, 0.1);
    }

    .plugs-dbg-method-PUT {
        color: var(--warning);
        background: rgba(245, 158, 11, 0.1);
    }

    .plugs-dbg-method-DELETE {
        color: var(--danger);
        background: rgba(239, 68, 68, 0.1);
    }

    .plugs-dbg-animate-fade-up {
        animation: plugsDbgFadeUp 0.6s var(--ease-out-expo) both;
    }
</style>