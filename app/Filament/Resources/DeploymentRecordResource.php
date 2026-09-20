<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeploymentRecordResource\Pages\ListDeploymentRecords;
use App\Models\DeploymentRecord;
use App\Services\DeploymentService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use UnitEnum;

class DeploymentRecordResource extends Resource
{
    protected static ?string $model = DeploymentRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static UnitEnum|string|null $navigationGroup = 'System & Ops';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('action')->label('Action')->badge()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'running' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('commit_hash')
                    ->label('Commit SHA')
                    ->badge()
                    ->default('N/A')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('commit_message')
                    ->label('Commit Message')
                    ->limit(35)
                    ->default('N/A')
                    ->searchable(),
                TextColumn::make('executed_migrations')
                    ->label('Migrations Ran')
                    ->formatStateUsing(function ($state) {
                        if (empty($state) || ! is_array($state)) {
                            return '0';
                        }

                        return count($state).' ran';
                    })
                    ->badge()
                    ->color(fn ($state) => ! empty($state) && is_array($state) && count($state) > 0 ? 'success' : 'gray'),
                TextColumn::make('pending_migrations_count')
                    ->label('Pending Migrations')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray')
                    ->sortable(),
                TextColumn::make('initiator.name')->label('Initiator')->default('System/CLI'),
                TextColumn::make('branch_version')->label('Branch')->sortable(),
                TextColumn::make('started_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'running' => 'Running',
                        'success' => 'Success',
                        'failed' => 'Failed',
                    ]),
                SelectFilter::make('action')
                    ->options([
                        'deploy_latest' => 'Deploy Latest',
                        'git_pull' => 'Git Pull',
                        'run_migrations' => 'Run Migrations',
                        'clear_cache' => 'Clear Cache',
                        'restart_workers' => 'Restart Workers',
                        'health_check' => 'Health Check',
                        'rollback' => 'Rollback',
                    ]),
            ])
            ->headerActions([
                Action::make('pending_migrations_status')
                    ->label(function () {
                        try {
                            /** @var DeploymentService $service */
                            $service = app(DeploymentService::class);
                            $pending = $service->getPendingMigrations();
                            $count = count($pending);

                            return $count > 0 ? "⚠️ {$count} Pending Migration(s)" : '✅ DB Up-To-Date (0 Pending)';
                        } catch (\Throwable $e) {
                            return 'DB Migration Status';
                        }
                    })
                    ->color(function () {
                        try {
                            /** @var DeploymentService $service */
                            $service = app(DeploymentService::class);

                            return count($service->getPendingMigrations()) > 0 ? 'warning' : 'success';
                        } catch (\Throwable $e) {
                            return 'gray';
                        }
                    })
                    ->icon(function () {
                        try {
                            /** @var DeploymentService $service */
                            $service = app(DeploymentService::class);

                            return count($service->getPendingMigrations()) > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle;
                        } catch (\Throwable $e) {
                            return Heroicon::OutlinedCommandLine;
                        }
                    })
                    ->requiresConfirmation(function () {
                        try {
                            /** @var DeploymentService $service */
                            $service = app(DeploymentService::class);

                            return count($service->getPendingMigrations()) > 0;
                        } catch (\Throwable $e) {
                            return false;
                        }
                    })
                    ->modalHeading('Pending Database Migrations Status')
                    ->modalDescription(function () {
                        try {
                            /** @var DeploymentService $service */
                            $service = app(DeploymentService::class);
                            $pending = $service->getPendingMigrations();
                            $count = count($pending);

                            if ($count === 0) {
                                return 'Database schema is fully up-to-date! No pending migrations are waiting to be executed on the live server.';
                            }

                            return "Found {$count} pending migration file(s) that need to be run on the live server:\n\n• ".implode("\n• ", $pending)."\n\nClick 'Run Migrations Now' below to execute them safely.";
                        } catch (\Throwable $e) {
                            return 'Unable to fetch pending migrations: '.$e->getMessage();
                        }
                    })
                    ->modalSubmitActionLabel('Run Migrations Now')
                    ->action(function () {
                        try {
                            /** @var DeploymentService $service */
                            $service = app(DeploymentService::class);
                            $pending = $service->getPendingMigrations();
                            if (count($pending) === 0) {
                                Notification::make()->info()->title('No Pending Migrations')->body('Database is already up-to-date.')->send();

                                return;
                            }

                            $record = $service->executeAction('run_migrations', auth()->user());
                            Notification::make()
                                ->success()
                                ->title('Pending Migrations Executed')
                                ->body($record->output_summary)
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('Migration Notice')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                Action::make('trigger_deploy')
                    ->label('Deploy Latest Code')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Deploy Latest Production Code')
                    ->modalDescription(function () {
                        try {
                            /** @var DeploymentService $service */
                            $service = app(DeploymentService::class);
                            $pending = $service->getPendingMigrations();
                            $pendingStr = count($pending) > 0 ? "\n\n⚠️ Includes ".count($pending).' pending migration(s): '.implode(', ', array_slice($pending, 0, 3)) : '';

                            return 'This will pull the latest code from https://github.com/motechgroup/lindrbackend.git (branch main), execute pending database migrations, rebuild application caches, and restart background workers safely.'.$pendingStr;
                        } catch (\Throwable $e) {
                            return 'This will pull the latest code from https://github.com/motechgroup/lindrbackend.git (branch main), execute pending database migrations, rebuild application caches, and restart background workers safely.';
                        }
                    })
                    ->action(function () {
                        try {
                            /** @var DeploymentService $service */
                            $service = app(DeploymentService::class);
                            $record = $service->executeAction('deploy_latest', auth()->user());
                            Notification::make()
                                ->success()
                                ->title('Deployment Completed')
                                ->body("Deployment #{$record->id} finished successfully. Commit: {$record->commit_hash}")
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('Deployment Failed')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                Action::make('view_commits')
                    ->label('View Commits')
                    ->color('info')
                    ->modalHeading('Latest Repository Commits')
                    ->modalDescription('Recent commit history from local git log or GitHub REST API.')
                    ->modalContent(function () {
                        try {
                            /** @var DeploymentService $service */
                            $service = app(DeploymentService::class);
                            $commits = $service->getRecentCommits(10);

                            return view('filament.deployment.commits', ['commits' => $commits]);
                        } catch (\Throwable $e) {
                            return view('filament.deployment.diff', ['diff' => 'Unable to load commit history: '.$e->getMessage()]);
                        }
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Action::make('inspect_diff')
                    ->label('Inspect Diff')
                    ->color('warning')
                    ->modalHeading('Git Changes Preview (Diff)')
                    ->modalDescription('Incoming repository changes preview.')
                    ->modalContent(function () {
                        try {
                            /** @var DeploymentService $service */
                            $service = app(DeploymentService::class);
                            $diff = $service->getCommitDiff();

                            return view('filament.deployment.diff', ['diff' => $diff]);
                        } catch (\Throwable $e) {
                            return view('filament.deployment.diff', ['diff' => 'Unable to load git diff: '.$e->getMessage()]);
                        }
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Action::make('run_migrations')
                    ->label('Run Migrations Only')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Execute Pending Migrations')
                    ->modalDescription(function () {
                        try {
                            /** @var DeploymentService $service */
                            $service = app(DeploymentService::class);
                            $pending = $service->getPendingMigrations();
                            $count = count($pending);
                            if ($count === 0) {
                                return 'Database is up-to-date. No pending migrations to execute.';
                            }

                            return "Execute {$count} pending database migration(s):\n• ".implode("\n• ", $pending);
                        } catch (\Throwable $e) {
                            return 'Execute pending database migration(s) safely on the live server.';
                        }
                    })
                    ->action(function () {
                        try {
                            /** @var DeploymentService $service */
                            $service = app(DeploymentService::class);
                            $record = $service->executeAction('run_migrations', auth()->user());
                            Notification::make()->success()->title('Migrations Executed')->body($record->output_summary)->send();
                        } catch (\Throwable $e) {
                            Notification::make()->danger()->title('Migration Error')->body($e->getMessage())->send();
                        }
                    }),

                Action::make('clear_cache')
                    ->label('Rebuild Cache')
                    ->color('gray')
                    ->action(function () {
                        try {
                            /** @var DeploymentService $service */
                            $service = app(DeploymentService::class);
                            $service->executeAction('clear_cache', auth()->user());
                            Notification::make()->success()->title('Cache Rebuilt')->send();
                        } catch (\Throwable $e) {
                            Notification::make()->danger()->title('Cache Error')->body($e->getMessage())->send();
                        }
                    }),
            ])
            ->recordActions([
                Action::make('view_details')
                    ->label('Deployment Details')
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->color('primary')
                    ->modalHeading(fn (DeploymentRecord $record) => "Deployment Details - Record #{$record->id} ({$record->action})")
                    ->modalContent(function (DeploymentRecord $record) {
                        try {
                            $executedList = ! empty($record->executed_migrations)
                                ? implode("\n  • ", (array) $record->executed_migrations)
                                : '  • No migrations executed in this run';

                            $details = "==================================================\n";
                            $details .= "  DEPLOYMENT METADATA & GIT COMMIT DETAILS\n";
                            $details .= "==================================================\n";
                            $details .= 'Record ID:             #'.$record->id."\n";
                            $details .= 'Action Executed:       '.strtoupper($record->action)."\n";
                            $details .= 'Status:                '.strtoupper($record->status ?? 'UNKNOWN')."\n";
                            $details .= 'Branch/Version:        '.($record->branch_version ?? 'main')."\n";
                            $details .= 'Commit SHA:            '.($record->commit_hash ?? 'N/A')."\n";
                            $details .= 'Commit Message:        '.($record->commit_message ?? 'N/A')."\n";
                            $details .= 'Commit Author:         '.($record->commit_author ?? 'N/A')."\n";
                            $details .= 'Initiated By:          '.($record->initiator?->name ?? 'System/CLI')."\n";
                            $details .= 'IP Address:            '.($record->ip_address ?? 'N/A')."\n";
                            $details .= 'Started At:            '.($record->started_at?->toDateTimeString() ?? 'N/A')."\n";
                            $details .= 'Completed At:          '.($record->completed_at?->toDateTimeString() ?? 'N/A')."\n\n";

                            $details .= "==================================================\n";
                            $details .= "  DATABASE MIGRATIONS EXECUTED\n";
                            $details .= "==================================================\n";
                            $details .= "{$executedList}\n\n";

                            $details .= "==================================================\n";
                            $details .= "  EXECUTION LOG & OUTPUT SUMMARY\n";
                            $details .= "==================================================\n";
                            $details .= ($record->output_summary ?: $record->error_summary ?: 'No output logged.');

                            return view('filament.deployment.diff', ['diff' => $details]);
                        } catch (\Throwable $e) {
                            return view('filament.deployment.diff', ['diff' => 'Error building deployment details: '.$e->getMessage()]);
                        }
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Action::make('view_output')
                    ->label('Log Output')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('gray')
                    ->modalHeading(fn (DeploymentRecord $record) => "Log Output - Deployment #{$record->id} ({$record->action})")
                    ->modalContent(function (DeploymentRecord $record) {
                        try {
                            return view('filament.deployment.output', ['record' => $record]);
                        } catch (\Throwable $e) {
                            return view('filament.deployment.diff', ['diff' => 'Log output error: '.$e->getMessage()]);
                        }
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        try {
            if (Schema::hasTable('deployment_records')) {
                return parent::getEloquentQuery();
            }
        } catch (\Throwable $e) {
            // Prevent 500 error if table is being created or migrated
        }

        return parent::getEloquentQuery();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeploymentRecords::route('/'),
        ];
    }
}
