<?php

declare(strict_types = 1)
;

namespace Modules\Admin\Controllers;

use App\Services\LogService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * LogController
 * 
 * Handles log viewing and management in the Admin panel.
 */
class LogController
{
    protected LogService $logService;

    public function __construct(LogService $logService)
    {
        $this->logService = $logService;
    }

    /**
     * Display the log viewer UI.
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        return response(view('admin::logs.index', [
            'title' => 'System Logs'
        ]));
    }

    /**
     * Fetch logs as JSON for the real-time UI.
     */
    public function fetch(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 100;

        $logs = $this->logService->getLatestLogs($limit);

        return response([
            'status' => 'success',
            'data' => $logs
        ]);
    }

    /**
     * Clear the log file.
     */
    public function clear(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->logService->clearLogs()) {
            return redirect('/admin/logs?success=Logs cleared successfully');
        }

        return redirect('/admin/logs?error=Failed to clear logs');
    }
}
