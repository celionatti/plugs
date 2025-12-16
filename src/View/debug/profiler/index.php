<?php $__extends = 'layout'; ?>

<?php
function getMethodClass($method)
{
    return 'method-' . $method;
}

function getStatusBadge($code)
{
    if ($code >= 200 && $code < 300)
        return 'badge-success';
    if ($code >= 300 && $code < 400)
        return 'badge-neutral';
    if ($code >= 400 && $code < 500)
        return 'badge-warning';
    return 'badge-danger';
}
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Recent Requests</h2>
    </div>
    <div style="overflow-x: auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Path</th>
                    <th>Status</th>
                    <th>Duration</th>
                    <th>Memory</th>
                    <th>Time</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($profiles)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-secondary);">No profiles captured yet.
                            Make some requests to your app!</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($profiles as $p): ?>
                    <tr>
                        <td>
                            <span class="method <?= getMethodClass($p['request']['method']) ?>">
                                <?= $p['request']['method'] ?>
                            </span>
                        </td>
                        <td>
                            <span title="<?= $p['request']['uri'] ?>">
                                <?= $p['request']['path'] ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= getStatusBadge($p['request']['status_code']) ?>">
                                <?= $p['request']['status_code'] ?>
                            </span>
                        </td>
                        <td><?= $p['duration'] ?> ms</td>
                        <td><?= $p['memory_peak_formatted'] ?></td>
                        <td style="color: var(--text-secondary); font-size: 0.85em;">
                            <?= date('H:i:s', $p['timestamp']) ?>
                            <div style="font-size: 0.8em; opacity: 0.7;"><?= date('Y-m-d', $p['timestamp']) ?></div>
                        </td>
                        <td style="text-align: right;">
                            <a href="/debug/performance/<?= $p['id'] ?>" class="btn btn-primary"
                                style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>