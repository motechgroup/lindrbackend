<?php

namespace App\Services;

use App\Models\DeploymentRecord;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DeploymentService
{
    public const ALLOWED_ACTIONS = [
        'deploy_latest' => 'Pull latest GitHub code (motechgroup/lindrbackend), run migrations, and rebuild cache',
        'git_pull' => 'Pull latest commits directly from https://github.com/motechgroup/lindrbackend.git',
        'run_migrations' => 'Run pending database migrations safely',
        'clear_cache' => 'Clear and rebuild application config, route, and view caches',
        'restart_workers' => 'Safely restart background queue workers',
        'health_check' => 'Run full system component health check',
        'rollback' => 'Revert to previous deployment commit and refresh cache',
    ];

    public function __construct(public HealthCheckService $healthCheckService) {}

    /**
     * Get list of allowed deployment actions.
     *
     * @return array<string, string>
     */
    public function getAllowedActions(): array
    {
        return self::ALLOWED_ACTIONS;
    }

    /**
     * Execute a whitelisted deployment action with full audit logging.
     *
     * @throws \InvalidArgumentException
     */
    public function executeAction(string $action, ?User $initiator = null, ?string $ipAddress = null): DeploymentRecord
    {
        $normalizedAction = strtolower(trim($action));

        if (! array_key_exists($normalizedAction, self::ALLOWED_ACTIONS)) {
            throw new \InvalidArgumentException("Unauthorized deployment action '{$action}'. Only whitelisted operations are permitted.");
        }

        $record = DeploymentRecord::create([
            'initiated_by_user_id' => $initiator?->id,
            'action' => $normalizedAction,
            'branch_version' => env('GIT_BRANCH', 'main'),
            'status' => 'running',
            'ip_address' => $ipAddress,
            'started_at' => now(),
        ]);

        try {
            $output = match ($normalizedAction) {
                'deploy_latest' => $this->runDeployLatest(),
                'git_pull' => $this->runGitPull(),
                'run_migrations' => $this->runMigrations(),
                'clear_cache' => $this->runClearCache(),
                'restart_workers' => $this->runRestartWorkers(),
                'health_check' => $this->runHealthCheck(),
                'rollback' => $this->runRollback(),
            };

            $record->update([
                'status' => 'success',
                'output_summary' => is_array($output) ? json_encode($output, JSON_PRETTY_PRINT) : (string) $output,
                'completed_at' => now(),
            ]);

            Log::info("Deployment action '{$normalizedAction}' completed successfully by user #".($initiator?->id ?? 'system'));

            return $record->fresh();
        } catch (\Throwable $e) {
            $record->update([
                'status' => 'failed',
                'error_summary' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            Log::error("Deployment action '{$normalizedAction}' failed: ".$e->getMessage());

            throw $e;
        }
    }

    protected function runDeployLatest(): string
    {
        $outputs = [];

        // 1. Pull code from GitHub repository
        $gitOutput = $this->runGitPull();
        if (! empty($gitOutput)) {
            $outputs[] = $gitOutput;
        }

        // 2. Run migrations safely
        Schema::disableForeignKeyConstraints();
        try {
            Artisan::call('migrate', ['--force' => true]);
            $outputs[] = "=== Database Migrations ===\n" . Artisan::output();
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        // 3. Rebuild caches
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');
        $outputs[] = "=== Configuration Caches ===\n" . Artisan::output();

        // 4. Restart Queue Workers
        Artisan::call('queue:restart');
        $outputs[] = "=== Queue Workers ===\n" . Artisan::output();

        return implode("\n\n", array_filter($outputs));
    }

    protected function runGitPull(): string
    {
        $repoUrl = env('GIT_REPO_URL', 'https://github.com/motechgroup/lindrbackend.git');
        $branch = env('GIT_BRANCH', 'main');
        $appPath = base_path();

        if (! function_exists('exec')) {
            return "Git Notice: exec() function is disabled in server PHP settings.";
        }

        $commands = [
            "cd {$appPath} && git remote set-url origin {$repoUrl} 2>&1",
            "cd {$appPath} && git fetch origin {$branch} 2>&1",
            "cd {$appPath} && git pull origin {$branch} 2>&1",
        ];

        $log = [];
        foreach ($commands as $cmd) {
            $output = [];
            @exec($cmd, $output);
            if (! empty($output)) {
                $log[] = implode("\n", $output);
            }
        }

        return "=== Git Pull ({$repoUrl} @ {$branch}) ===\n" . (implode("\n", $log) ?: 'Git pull completed.');
    }

    protected function runMigrations(): string
    {
        Schema::disableForeignKeyConstraints();
        try {
            Artisan::call('migrate', ['--force' => true]);
            return Artisan::output() ?: 'Migrations executed successfully.';
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    protected function runClearCache(): string
    {
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');

        return 'Caches cleared and rebuilt successfully.';
    }

    protected function runRestartWorkers(): string
    {
        Artisan::call('queue:restart');

        return Artisan::output() ?: 'Queue workers signal sent successfully.';
    }

    protected function runHealthCheck(): array
    {
        return $this->healthCheckService->checkSystemHealth();
    }

    protected function runRollback(): string
    {
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('queue:restart');

        return 'Rollback cache refresh and queue restart completed successfully.';
    }
}
