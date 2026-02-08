<?php
$__extends = 'debug.layout';
/** @var array<string, mixed> $profiles */
?>
<?php
function getMethodClass($method)
{
    $method = strtoupper($method);

    return 'method-' . $method;
}

function getStatusBadge($code)
{
    if ($code >= 200 && $code < 300) {
        return 'status-success';
    }
    if ($code >= 300 && $code < 400) {
        return 'status-info';
    }
    if ($code >= 400 && $code < 500) {
        return 'status-warning';
    }

    return 'status-danger';
}
?>

    <div class="glass-panel animate-fade-up" style="animation-delay: 0.2s; overflow: hidden;">
        <div
            style="padding: 1.5rem 2rem; border-bottom: 1px solid var(--border-glass); display: flex; justify-content: space-between; align-items: center;">
            <h2 style="font-size: 1.25rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem;">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Recent Requests
            </h2>
            <div style="font-size: 0.85rem; color: var(--text-muted);">
                Showing last <?= count($profiles) ?> captures
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="plugs-table">
                <thead>
                    <tr>
                        <th>Method</th>
                        <th>Path</th>
                        <th>Status</th>
                        <th class="hide-mobile">Duration</th>
                        <th class="hide-mobile">Memory</th>
                        <th class="hide-tablet">Time</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($profiles)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 4rem 2rem;">
                                <div style="opacity: 0.5; margin-bottom: 1rem;">
                                    <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9.172 9.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                                        </path>
                                    </svg>
                                </div>
                                <p style="color: var(--text-secondary); font-weight: 500;">No profiles captured yet.</p>
                                <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.5rem;">Make some
                                    requests
                                    to your application to see logs here.</p>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($profiles as $p): ?>
                        <tr class="profile-row" onclick="window.location.href='/debug/performance/<?= $p['id'] ?>'">
                            <td>
                                <span class="method-label <?= getMethodClass($p['request']['method']) ?>">
                                    <?= $p['request']['method'] ?>
                                </span>
                            </td>
                            <td>
                                <div style="max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: 'JetBrains Mono', monospace; font-size: 0.9rem;"
                                    title="<?= $p['request']['uri'] ?>">
                                    <?= $p['request']['path'] ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge <?= getStatusBadge($p['request']['status_code']) ?>">
                                    <?= $p['request']['status_code'] ?>
                                </span>
                            </td>
                            <td class="hide-mobile">
                                <span
                                    style="font-weight: 600; color: <?= $p['duration'] > 100 ? 'var(--warning)' : 'var(--text-primary)' ?>;">
                                    <?= number_format($p['duration'], 2) ?> <span
                                        style="font-size: 0.75rem; opacity: 0.6; font-weight: 400;">ms</span>
                                </span>
                            </td>
                            <td class="hide-mobile" style="color: var(--text-secondary); font-size: 0.9rem;">
                                <?= $p['memory_peak_formatted'] ?>
                            </td>
                            <td class="hide-tablet">
                                <div style="font-size: 0.85rem; color: var(--text-primary);">
                                    <?= date('H:i:s', $p['timestamp']) ?>
                                </div>
                                <div style="font-size: 0.7rem; color: var(--text-muted);">
                                    <?= date('M d, Y', $p['timestamp']) ?>
                                </div>
                            </td>
                            <td style="text-align: right;">
                                <div class="btn-action">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 5l7 7-7 7">
                                        </path>
                                    </svg>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <style>
        .plugs-table {
            width: 100%;
            border-collapse: collapse;
        }

        .plugs-table th {
            text-align: left;
            padding: 1rem 1.5rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-muted);
            font-weight: 700;
            background: rgba(0, 0, 0, 0.1);
        }

        .plugs-table td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-glass);
        }

        .plugs-table tr:last-child td {
            border-bottom: none;
        }

        .method-label {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 700;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
        }

        .method-GET {
            color: var(--info);
            background: rgba(14, 165, 233, 0.1);
        }

        .method-POST {
            color: var(--success);
            background: rgba(16, 185, 129, 0.1);
        }

        .method-PUT {
            color: var(--warning);
            background: rgba(245, 158, 11, 0.1);
        }

        .method-DELETE {
            color: var(--danger);
            background: rgba(239, 68, 68, 0.1);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .status-badge::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .status-success {
            color: var(--success);
        }

        .status-success::before {
            background: var(--success);
            box-shadow: 0 0 8px var(--success);
        }

        .status-info {
            color: var(--info);
        }

        .status-info::before {
            background: var(--info);
        }

        .status-warning {
            color: var(--warning);
        }

        .status-warning::before {
            background: var(--warning);
        }

        .status-danger {
            color: var(--danger);
        }

        .status-danger::before {
            background: var(--danger);
            box-shadow: 0 0 8px var(--danger);
        }

        .btn-action {
            color: var(--text-muted);
            transition: all 0.2s;
        }

        .profile-row:hover .btn-action {
            color: var(--accent);
            transform: translateX(4px);
        }

        @media (max-width: 1024px) {
            .hide-tablet {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .hide-mobile {
                display: none;
            }
        }
    </style>