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
                TextColumn::make('initiator.name')->label('Initiator')->default('System/CLI')->sortable(),
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
                    ->modalDescription('This will run pending migrations, rebuild application caches, and restart background queue workers safely.')
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
                    ->color('secondary')
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
                Action::make('restart_workers')
                    ->label('Restart Queue Workers')
                    ->color('info')
                    ->action(function () {
                        /** @var DeploymentService $service */
                        $service = app(DeploymentService::class);
                        try {
                            $service->executeAction('restart_workers', auth()->user());
                            Notification::make()->success()->title('Queue Workers Signaled')->send();
                        } catch (\Exception $e) {
                            Notification::make()->danger()->title('Worker Error')->body($e->getMessage())->send();
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
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeploymentRecords::route('/'),
        ];
    }
}
