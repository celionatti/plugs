<?php

declare(strict_types=1);

namespace Plugs\Http\Controllers;

use Plugs\Base\Controller\Controller;
use Plugs\Support\Enums\HttpStatus;
use Psr\Http\Message\ResponseInterface;

class HealthController extends Controller
{
    /**
     * Check the health of the application and its dependencies.
     */
    public function __invoke(): ResponseInterface
    {
        $status = [
            'status' => 'up',
            'timestamp' => date('c'),
            'environment' => config('app.env', 'production'),
            'checks' => [
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'storage' => $this->checkStorage(),
            ],
        ];

        $statusCode = HttpStatus::OK;

        foreach ($status['checks'] as $check) {
            if ($check['status'] !== 'ok') {
                $status['status'] = 'error';
                $statusCode = HttpStatus::SERVICE_UNAVAILABLE;

                break;
            }
        }

        return $this->json($status, $statusCode->value);
    }

    private function checkDatabase(): array
    {
        try {
            // Check if DB is bound in container
            if ($this->db) {
                $this->db->query('SELECT 1');

                return ['status' => 'ok'];
            }

            return ['status' => 'skipped', 'message' => 'Database not configured'];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'message' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            $cache = app('cache');
            if ($cache) {
                $cache->set('_health_check', true, 10);
                $cache->get('_health_check');

                return ['status' => 'ok'];
            }

            return ['status' => 'skipped', 'message' => 'Cache not configured'];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'message' => $e->getMessage()];
        }
    }

    private function checkStorage(): array
    {
        $storagePath = defined('STORAGE_PATH') ? STORAGE_PATH : (function_exists('storage_path') ? storage_path() : dirname(__DIR__, 2) . '/storage/');
        if (is_writable($storagePath)) {
            return ['status' => 'ok'];
        }

        return ['status' => 'fail', 'message' => 'Storage directory is not writable'];
    }
}
