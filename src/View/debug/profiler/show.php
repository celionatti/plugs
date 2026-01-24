<?php $__extends = 'layout'; ?>
<?php /** @var array $profile */ ?>

<?php
function getMethodClass($method)
{
    return 'method-' . $method;
}
?>

<a href="/debug/performance" class="back-link">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
        stroke-linecap="round" stroke-linejoin="round">
        <path d="M19 12H5" />
        <path d="M12 19l-7-7 7-7" />
    </svg>
    Back to Requests
</a>

<div class="header" style="border: none; margin-bottom: 1rem;">
    <div>
        <h1 style="margin: 0; font-size: 2rem;">
            <span
                class="method <?= getMethodClass($profile['request']['method']) ?>"><?= $profile['request']['method'] ?></span>
            <?= $profile['request']['path'] ?>
        </h1>
        <div style="margin-top: 0.5rem; color: var(--text-secondary);">
            <?= $profile['datetime'] ?> • IP: <?= $profile['request']['ip'] ?>
        </div>
    </div>

    <div style="text-align: right;">
        <span class="badge badge-neutral" style="font-size: 1rem; padding: 0.5rem 1rem;">
            Status: <?= $profile['request']['status_code'] ?>
        </span>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-value" style="color: #60a5fa;"><?= $profile['duration'] ?> <span
                style="font-size: 1rem;">ms</span></div>
        <div class="stat-label">Total Duration</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: #a78bfa;"><?= $profile['memory_peak_formatted'] ?></div>
        <div class="stat-label">Peak Memory</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: #f59e0b;"><?= $profile['database']['total_queries'] ?? 0 ?></div>
        <div class="stat-label">Database Queries</div>
    </div>
</div>

<div class="card">
    <div class="nav-tabs">
        <div class="nav-item active" onclick="showTab('queries')">Database Queries</div>
        <div class="nav-item" onclick="showTab('params')">Request Parameters</div>
        <div class="nav-item" onclick="showTab('analysis')">Query Analysis</div>
    </div>

    <div id="queries" class="tab-content active">
        <?php if (empty($profile['database']['most_frequent'])): ?>
            <div style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                No queries executed.
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 60%;">Query Sample</th>
                        <th style="width: 10%;">Count</th>
                        <th style="width: 15%;">Time (ms)</th>
                        <th style="width: 15%;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Flatten the simplified stats for list view or use raw logs if I had them.
                    // Current Profiler uses Connection::getQueryAnalysisReport()
                    // which returns grouped stats.
                    ?>
                    <?php foreach ($profile['database']['most_frequent'] as $sql => $stats): ?>
                        <tr>
                            <td>
                                <code><?= htmlspecialchars(substr($sql, 0, 150)) . (strlen($sql) > 150 ? '...' : '') ?></code>
                            </td>
                            <td style="text-align: center;"><?= $stats['count'] ?></td>
                            <td><?= round($stats['total_time'] * 1000, 2) ?></td>
                            <td>
                                <?php if ($stats['max_time'] > 0.05): ?>
                                    <span class="badge badge-warning">Slow</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Fast</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div id="params" class="tab-content">
        <h3>Query Parameters (GET)</h3>
        <?php if (empty($profile['request']['params'])): ?>
            <p style="color: var(--text-secondary);">No query parameters.</p>
        <?php else: ?>
            <pre><?= json_encode($profile['request']['params'], JSON_PRETTY_PRINT) ?></pre>
        <?php endif; ?>
    </div>

    <div id="analysis" class="tab-content">
        <?php if (!empty($profile['database']['slow_queries'])): ?>
            <div
                style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                <h3 style="color: var(--danger); margin-top: 0;">Slow Queries Detected</h3>
                <ul style="margin: 0; padding-left: 1.5rem;">
                    <?php foreach ($profile['database']['slow_queries'] as $slow): ?>
                        <li>
                            <strong><?= round($slow['max_time'] * 1000, 2) ?>ms</strong>:
                            <code><?= htmlspecialchars($slow['query']) ?></code>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($profile['database']['n_plus_one_suspects'])): ?>
            <div
                style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2); padding: 1rem; border-radius: 8px;">
                <h3 style="color: var(--warning); margin-top: 0;">Potential N+1 Queries</h3>
                <p style="color: var(--text-secondary); margin-bottom: 1rem;">These queries were executed multiple times in
                    quick succession.</p>
                <ul style="margin: 0; padding-left: 1.5rem;">
                    <?php foreach ($profile['database']['n_plus_one_suspects'] as $suspect): ?>
                        <li>
                            Executed <strong><?= $suspect['count'] ?></strong> times:
                            <code><?= htmlspecialchars($suspect['query']) ?></code>
                            <br>
                            <small class="text-secondary">Location:
                                <?= implode(', ', array_map('basename', $suspect['locations'] ?? [])) ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (empty($profile['database']['slow_queries']) && empty($profile['database']['n_plus_one_suspects'])): ?>
            <div style="text-align: center; color: var(--success); padding: 2rem;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 1rem;">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                    <polyline points="22 4 12 14.01 9 11.01" />
                </svg>
                <p>No obvious performance issues detected!</p>
            </div>
        <?php endif; ?>
    </div>
</div>