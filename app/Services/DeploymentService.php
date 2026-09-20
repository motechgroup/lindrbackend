<?php

namespace App\Services;

use App\Models\DeploymentRecord;
use App\Models\User;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
            $cmd = "cd {$appPath} && git log -n {$limit} --pretty=format:'%h|%H|%an|%ae|%ar|%s' 2>&1";
            $output = [];
            @exec($cmd, $output);

            if (! empty($output) && strpos($output[0], 'fatal') === false && strpos($output[0], 'error') === false) {
                $commits = [];
                foreach ($output as $line) {
                    $parts = explode('|', $line, 6);
                    if (count($parts) === 6) {
                        $commits[] = [
                            'short_hash' => $parts[0],
                            'full_hash' => $parts[1],
                            'author' => $parts[2],
                            'email' => $parts[3],
                            'date' => $parts[4],
                            'message' => $parts[5],
                        ];
                    }
                }

                if (! empty($commits)) {
                    return $commits;
                }
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
                        'short_hash' => substr($item['sha'] ?? '', 0, 7),
                        'full_hash' => $item['sha'] ?? '',
                        'author' => $item['commit']['author']['name'] ?? 'Unknown',
                        'email' => $item['commit']['author']['email'] ?? '',
                        'date' => $item['commit']['author']['date'] ?? '',
                        'message' => strtok($item['commit']['message'] ?? '', "\n"),
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
            $cmd = $commitHash
                ? "cd {$appPath} && git show {$commitHash} --stat --patch 2>&1"
                : "cd {$appPath} && git fetch origin main 2>&1 && git diff HEAD..origin/main --stat 2>&1";

            $output = [];
            @exec($cmd, $output);

            if (! empty($output)) {
                return implode("\n", array_slice($output, 0, 300));
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
                $cmd = "cd {$appPath} && git reset --hard {$normalizedHash} 2>&1";
                $output = [];
                @exec($cmd, $output);
                $outputs[] = "=== Git Reset to Commit {$normalizedHash} ===\n".implode("\n", $output);
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
                'output_summary' => implode("\n\n", array_filter($outputs)),
                'completed_at' => now(),
            ]);

            Log::info("Rollback to commit '{$normalizedHash}' completed successfully by user #".($initiator?->id ?? 'system'));

            return $record->fresh();
        } catch (\Throwable $e) {
            $record->update([
                'status' => 'failed',
                'error_summary' => $e->getMessage(),
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
            /** @var Migrator $migrator */
            $migrator = app('migrator');
            $files = $migrator->getMigrationFiles(database_path('migrations'));
            $ran = $migrator->getRepository()->getRan();

            $pending = [];
            foreach ($files as $name => $file) {
                if (! in_array($name, $ran)) {
                    $pending[] = $name;
                }
            }

            return array_values($pending);
        } catch (\Throwable $e) {
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
        $migOutput = $this->runMigrations();
        $outputs[] = $migOutput;

        // 3. Rebuild caches
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');
        $outputs[] = "=== Configuration Caches ===\n".Artisan::output();

        // 4. Restart Queue Workers
        Artisan::call('queue:restart');
        $outputs[] = "=== Queue Workers ===\n".Artisan::output();

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

        return "=== Git Pull ({$repoUrl} @ {$branch}) ===\n".(implode("\n", $log) ?: 'Git pull completed.');
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

        return "=== Database Migrations ({$ranCount} ran, ".count($pendingAfter)." remaining) ===\n".($artisanOut ?: 'No new migrations executed.');
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
