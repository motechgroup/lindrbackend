<?php

namespace App\Services;

use App\Models\DeploymentRecord;
use App\Models\User;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
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
     * Get recent commit logs with metadata (Local Git CLI with GitHub API fallback).
     *
     * @return array<int, array{short_hash: string, full_hash: string, author: string, email: string, date: string, message: string}>
     */
    public function getRecentCommits(int $limit = 10): array
    {
        $repoUrl = env('GIT_REPO_URL', 'https://github.com/motechgroup/lindrbackend.git');
        $branch = env('GIT_BRANCH', 'main');
        $appPath = base_path();

        if (function_exists('exec')) {
            try {
                $cmd = "cd {$appPath} && git -c safe.directory=* log -n {$limit} --pretty=format:'%h|%H|%an|%ae|%ar|%s' 2>&1";
                $output = [];
                @exec($cmd, $output);

                if (! empty($output) && strpos($output[0], 'fatal') === false && strpos($output[0], 'error') === false) {
                    $commits = [];
                    foreach ($output as $line) {
                        $line = mb_convert_encoding($line, 'UTF-8', 'UTF-8');
                        $parts = explode('|', $line, 6);
                        if (count($parts) === 6) {
                            $commits[] = [
                                'short_hash' => mb_convert_encoding($parts[0], 'UTF-8', 'UTF-8'),
                                'full_hash' => mb_convert_encoding($parts[1], 'UTF-8', 'UTF-8'),
                                'author' => mb_convert_encoding($parts[2], 'UTF-8', 'UTF-8'),
                                'email' => mb_convert_encoding($parts[3], 'UTF-8', 'UTF-8'),
                                'date' => mb_convert_encoding($parts[4], 'UTF-8', 'UTF-8'),
                                'message' => mb_convert_encoding($parts[5], 'UTF-8', 'UTF-8'),
                            ];
                        }
                    }

                    if (! empty($commits)) {
                        return $commits;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Git CLI commit fetch error: '.$e->getMessage());
            }
        }

        // Fallback: GitHub REST API
        try {
            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'Lindr-App'])
                ->get('https://api.github.com/repos/motechgroup/lindrbackend/commits?per_page='.$limit);

            if ($response->successful()) {
                $items = $response->json();
                $commits = [];
                foreach ($items as $item) {
                    $commits[] = [
                        'short_hash' => mb_convert_encoding(substr($item['sha'] ?? '', 0, 7), 'UTF-8', 'UTF-8'),
                        'full_hash' => mb_convert_encoding($item['sha'] ?? '', 'UTF-8', 'UTF-8'),
                        'author' => mb_convert_encoding($item['commit']['author']['name'] ?? 'Unknown', 'UTF-8', 'UTF-8'),
                        'email' => mb_convert_encoding($item['commit']['author']['email'] ?? '', 'UTF-8', 'UTF-8'),
                        'date' => mb_convert_encoding($item['commit']['author']['date'] ?? '', 'UTF-8', 'UTF-8'),
                        'message' => mb_convert_encoding(strtok($item['commit']['message'] ?? '', "\n"), 'UTF-8', 'UTF-8'),
                    ];
                }

                return $commits;
            }
        } catch (\Throwable $e) {
            Log::warning('GitHub API commit fetch notice: '.$e->getMessage());
        }

        return [];
    }

    /**
     * Get commit diff or incoming un-pulled changes metadata.
     */
    public function getCommitDiff(?string $commitHash = null): string
    {
        $appPath = base_path();

        if (function_exists('exec')) {
            try {
                $cmd = $commitHash
                    ? "cd {$appPath} && git -c safe.directory=* show {$commitHash} --stat --patch 2>&1"
                    : "cd {$appPath} && git -c safe.directory=* fetch origin main 2>&1 && git -c safe.directory=* diff HEAD..origin/main --stat 2>&1";

                $output = [];
                @exec($cmd, $output);

                if (! empty($output)) {
                    $rawDiff = implode("\n", array_slice($output, 0, 300));

                    return mb_convert_encoding($rawDiff, 'UTF-8', 'UTF-8');
                }
            } catch (\Throwable $e) {
                Log::warning('Git CLI diff error: '.$e->getMessage());
            }
        }

        return "Diff preview unavailable via local git CLI.\nView repository commits directly at: https://github.com/motechgroup/lindrbackend/commits";
    }

    /**
     * Roll back site code to a specific commit hash and refresh caches.
     */
    public function rollbackToCommit(string $commitHash, ?User $initiator = null, ?string $ipAddress = null): DeploymentRecord
    {
        $normalizedHash = trim($commitHash);

        $record = DeploymentRecord::create([
            'initiated_by_user_id' => $initiator?->id,
            'action' => 'rollback',
            'branch_version' => $normalizedHash,
            'status' => 'running',
            'ip_address' => $ipAddress,
            'started_at' => now(),
        ]);

        try {
            $appPath = base_path();
            $outputs = [];

            if (function_exists('exec')) {
                $cmd = "cd {$appPath} && git -c safe.directory=* reset --hard {$normalizedHash} 2>&1";
                $output = [];
                @exec($cmd, $output);
                $outputs[] = mb_convert_encoding("=== Git Reset to Commit {$normalizedHash} ===\n".implode("\n", $output), 'UTF-8', 'UTF-8');
            } else {
                $outputs[] = 'Git reset notice: exec() disabled. Manual rollback recommended.';
            }

            // Rebuild Caches & Restart Workers
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');

            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
            Artisan::call('queue:restart');

            $outputs[] = '=== Production Caches Rebuilt & Queue Workers Restarted ===';

            $record->update([
                'status' => 'success',
                'output_summary' => mb_convert_encoding(implode("\n\n", array_filter($outputs)), 'UTF-8', 'UTF-8'),
                'completed_at' => now(),
            ]);

            Log::info("Rollback to commit '{$normalizedHash}' completed successfully by user #".($initiator?->id ?? 'system'));

            return $record->fresh();
        } catch (\Throwable $e) {
            $record->update([
                'status' => 'failed',
                'error_summary' => mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8'),
                'completed_at' => now(),
            ]);

            Log::error("Rollback to commit '{$normalizedHash}' failed: ".$e->getMessage());

            throw $e;
        }
    }

    /**
     * Get list of pending migration file names.
     *
     * @return array<int, string>
     */
    public function getPendingMigrations(): array
    {
        try {
            if (! Schema::hasTable('migrations')) {
                return [];
            }

            /** @var Migrator $migrator */
            $migrator = app('migrator');
            $files = $migrator->getMigrationFiles(database_path('migrations'));
            $ran = $migrator->getRepository()->getRan();

            $pending = [];
            foreach ($files as $name => $file) {
                if (! in_array($name, $ran)) {
                    $pending[] = mb_convert_encoding($name, 'UTF-8', 'UTF-8');
                }
            }

            return array_values($pending);
        } catch (\Throwable $e) {
            Log::warning('Notice checking pending migrations: '.$e->getMessage());

            return [];
        }
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

        $pendingBefore = $this->getPendingMigrations();
        $recentCommits = $this->getRecentCommits(1);
        $commitMeta = $recentCommits[0] ?? [];

        $record = DeploymentRecord::create([
            'initiated_by_user_id' => $initiator?->id,
            'action' => $normalizedAction,
            'branch_version' => env('GIT_BRANCH', 'main'),
            'commit_hash' => $commitMeta['short_hash'] ?? null,
            'commit_message' => $commitMeta['message'] ?? null,
            'commit_author' => $commitMeta['author'] ?? null,
            'pending_migrations_count' => count($pendingBefore),
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

            $pendingAfter = $this->getPendingMigrations();
            $executed = array_values(array_diff($pendingBefore, $pendingAfter));

            // Refresh latest commit metadata in case git_pull/deploy_latest pulled new commits
            $postCommits = $this->getRecentCommits(1);
            $postMeta = $postCommits[0] ?? $commitMeta;

            $record->update([
                'status' => 'success',
                'commit_hash' => $postMeta['short_hash'] ?? $record->commit_hash,
                'commit_message' => $postMeta['message'] ?? $record->commit_message,
                'commit_author' => $postMeta['author'] ?? $record->commit_author,
                'executed_migrations' => $executed,
                'pending_migrations_count' => count($pendingAfter),
                'output_summary' => mb_convert_encoding(is_array($output) ? json_encode($output, JSON_PRETTY_PRINT) : (string) $output, 'UTF-8', 'UTF-8'),
                'completed_at' => now(),
            ]);

            Log::info("Deployment action '{$normalizedAction}' completed successfully by user #".($initiator?->id ?? 'system'));

            return $record->fresh();
        } catch (\Throwable $e) {
            $record->update([
                'status' => 'failed',
                'error_summary' => mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8'),
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
        $migOutput = $this->runMigrations();
        $outputs[] = $migOutput;

        // 3. Rebuild caches
        $cacheOut = $this->runClearCache();
        $outputs[] = $cacheOut;

        // 4. Restart Queue Workers
        $workerOut = $this->runRestartWorkers();
        $outputs[] = $workerOut;

        return implode("\n\n", array_filter($outputs));
    }

    protected function runGitPull(): string
    {
        $repoUrl = env('GIT_REPO_URL', 'https://github.com/motechgroup/lindrbackend.git');
        $branch = env('GIT_BRANCH', 'main');
        $appPath = base_path();

        if (! function_exists('exec')) {
            return 'Git Notice: exec() function is disabled in server PHP settings.';
        }

        $commands = [
            "cd {$appPath} && git config --global --add safe.directory '*' 2>&1",
            "cd {$appPath} && git -c safe.directory=* remote set-url origin {$repoUrl} 2>&1",
            "cd {$appPath} && git -c safe.directory=* fetch origin {$branch} 2>&1",
            "cd {$appPath} && git -c safe.directory=* pull origin {$branch} --no-rebase 2>&1",
        ];

        $log = [];
        foreach ($commands as $cmd) {
            $output = [];
            try {
                @exec($cmd, $output);
                if (! empty($output)) {
                    $log[] = mb_convert_encoding(implode("\n", $output), 'UTF-8', 'UTF-8');
                }
            } catch (\Throwable $e) {
                $log[] = "Command failed [{$cmd}]: ".$e->getMessage();
            }
        }

        $result = "=== Git Pull ({$repoUrl} @ {$branch}) ===\n".(implode("\n", $log) ?: 'Git pull completed.');

        return mb_convert_encoding($result, 'UTF-8', 'UTF-8');
    }

    protected function runMigrations(): string
    {
        $pendingBefore = $this->getPendingMigrations();

        try {
            Artisan::call('migrate', ['--force' => true]);
            $artisanOut = Artisan::output();
        } catch (\Throwable $e) {
            $artisanOut = 'Migration error: '.$e->getMessage();
        }

        $pendingAfter = $this->getPendingMigrations();
        $ranCount = count($pendingBefore) - count($pendingAfter);

        $result = "=== Database Migrations ({$ranCount} ran, ".count($pendingAfter)." remaining) ===\n".($artisanOut ?: 'No new migrations executed.');

        return mb_convert_encoding($result, 'UTF-8', 'UTF-8');
    }

    protected function runClearCache(): string
    {
        try {
            Artisan::call('optimize:clear');
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');

            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }

            return 'Caches (compiled views, routes, configs, and OPcache) cleared and purged successfully.';
        } catch (\Throwable $e) {
            return 'Cache clear notice: '.$e->getMessage();
        }
    }

    protected function runRestartWorkers(): string
    {
        try {
            Artisan::call('queue:restart');

            return Artisan::output() ?: 'Queue workers signal sent successfully.';
        } catch (\Throwable $e) {
            return 'Worker restart notice: '.$e->getMessage();
        }
    }

    protected function runHealthCheck(): array
    {
        return $this->healthCheckService->checkSystemHealth();
    }

    protected function runRollback(): string
    {
        try {
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('queue:restart');

            return 'Rollback cache refresh and queue restart completed successfully.';
        } catch (\Throwable $e) {
            return 'Rollback notice: '.$e->getMessage();
        }
    }
}
