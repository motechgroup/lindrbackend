<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeploymentRecordResource\Pages\ListDeploymentRecords;
use App\Models\DeploymentRecord;
use App\Services\DeploymentService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
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
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('initiator.name')->label('Initiator')->default('System/CLI'),
                TextColumn::make('branch_version')->label('Branch/Version')->sortable(),
                TextColumn::make('ip_address')->label('IP')->searchable(),
                TextColumn::make('started_at')->dateTime()->sortable(),
                TextColumn::make('completed_at')->dateTime()->sortable(),
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
                Action::make('trigger_deploy')
                    ->label('Deploy Latest Code')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Deploy Latest Production Code')
                    ->modalDescription('This will pull the latest code from https://github.com/motechgroup/lindrbackend.git (branch main), run pending database migrations, rebuild application caches, and restart background workers safely.')
                    ->action(function () {
                        /** @var DeploymentService $service */
                        $service = app(DeploymentService::class);
                        try {
                            $record = $service->executeAction('deploy_latest', auth()->user());
                            Notification::make()
                                ->success()
                                ->title('Deployment Completed')
                                ->body("Deployment #{$record->id} finished successfully.")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Deployment Failed')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                Action::make('view_commits')
                    ->label('View Commits & Metadata')
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
                    ->label('Inspect Changes (Diff)')
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

                Action::make('rollback_code')
                    ->label('Rollback Site')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Rollback Site Code to Commit')
                    ->modalDescription('Specify the commit hash to hard reset the site code to. This will reset codebase state and rebuild production caches.')
                    ->form([
                        TextInput::make('commit_hash')
                            ->label('Commit Hash / SHA')
                            ->placeholder('e.g. 7f8a9b0 or full SHA')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        /** @var DeploymentService $service */
                        $service = app(DeploymentService::class);
                        try {
                            $record = $service->rollbackToCommit($data['commit_hash'], auth()->user(), request()->ip());
                            Notification::make()
                                ->success()
                                ->title('Rollback Executed')
                                ->body("Site rolled back to commit {$data['commit_hash']}. Record #{$record->id}.")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Rollback Failed')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                Action::make('git_pull')
                    ->label('Git Pull Only')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Pull Code from GitHub')
                    ->modalDescription('Pulls latest commits from https://github.com/motechgroup/lindrbackend.git without clearing cache or running migrations.')
                    ->action(function () {
                        /** @var DeploymentService $service */
                        $service = app(DeploymentService::class);
                        try {
                            $record = $service->executeAction('git_pull', auth()->user());
                            Notification::make()
                                ->success()
                                ->title('Git Pull Completed')
                                ->body($record->output_summary)
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Git Pull Failed')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                Action::make('run_migrations')
                    ->label('Run Migrations')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function () {
                        /** @var DeploymentService $service */
                        $service = app(DeploymentService::class);
                        try {
                            $record = $service->executeAction('run_migrations', auth()->user());
                            Notification::make()->success()->title('Migrations Executed')->send();
                        } catch (\Exception $e) {
                            Notification::make()->danger()->title('Migration Error')->body($e->getMessage())->send();
                        }
                    }),

                Action::make('clear_cache')
                    ->label('Rebuild Cache')
                    ->color('gray')
                    ->action(function () {
                        /** @var DeploymentService $service */
                        $service = app(DeploymentService::class);
                        try {
                            $service->executeAction('clear_cache', auth()->user());
                            Notification::make()->success()->title('Cache Rebuilt')->send();
                        } catch (\Exception $e) {
                            Notification::make()->danger()->title('Cache Error')->body($e->getMessage())->send();
                        }
                    }),

                Action::make('health_check')
                    ->label('Run Health Check')
                    ->color('success')
                    ->action(function () {
                        /** @var DeploymentService $service */
                        $service = app(DeploymentService::class);
                        try {
                            $record = $service->executeAction('health_check', auth()->user());
                            Notification::make()->success()->title('Health Check Completed')->body($record->output_summary)->send();
                        } catch (\Exception $e) {
                            Notification::make()->danger()->title('Health Check Error')->body($e->getMessage())->send();
                        }
                    }),
            ])
            ->recordActions([
                Action::make('view_output')
                    ->label('Log Output')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->modalHeading(fn (DeploymentRecord $record) => "Execution Output - Deployment #{$record->id} ({$record->action})")
                    ->modalDescription('Execution output log details.')
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

    public static function getPages(): array
    {
        return [
            'index' => ListDeploymentRecords::route('/'),
        ];
    }
}
