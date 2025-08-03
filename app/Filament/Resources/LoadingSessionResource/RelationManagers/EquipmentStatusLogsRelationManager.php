<?php
// ===========================
// app/Filament/Resources/LoadingSessionResource/RelationManagers/EquipmentStatusLogsRelationManager.php
// ===========================

namespace App\Filament\Resources\LoadingSessionResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use App\Models\Equipment;

class EquipmentStatusLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'equipmentStatusLogs';

    protected static ?string $recordTitleAttribute = 'status';

    protected static ?string $label = 'Equipment Status Log';

    protected static ?string $pluralLabel = 'Equipment Status Logs';

    protected static ?string $title = 'Equipment Status History During Session';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Status Information')
                    ->description('Log equipment status changes during this loading session')
                    ->schema([
                        Forms\Components\Select::make('equipment_id')
                            ->label('Equipment')
                            ->relationship('equipment', 'code')
                            ->getOptionLabelFromRecordUsing(fn ($record) => 
                                "{$record->code} - {$record->type_name} ({$record->brand} {$record->model})"
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Select the equipment for this status log'),

                        Forms\Components\Select::make('status')
                            ->label('Equipment Status')
                            ->options([
                                'idle' => 'Idle - Equipment ready but not working',
                                'working' => 'Working - Currently in operation',
                                'breakdown' => 'Breakdown - Equipment not operational',
                                'maintenance' => 'Maintenance - Scheduled maintenance',
                            ])
                            ->required()
                            ->helperText('Current operational status of the equipment'),

                        Forms\Components\DateTimePicker::make('status_time')
                            ->label('Status Change Time')
                            ->required()
                            ->default(now())
                            ->seconds(false)
                            ->helperText('When did this status change occur'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Operational Details')
                    ->description('Additional operational information (optional)')
                    ->schema([
                        Forms\Components\TextInput::make('operator_name')
                            ->label('Operator Name')
                            ->placeholder('Enter operator name')
                            ->maxLength(255)
                            ->helperText('Name of the equipment operator'),

                        Forms\Components\TextInput::make('location')
                            ->label('Current Location')
                            ->placeholder('Area code, GPS coordinates, or description')
                            ->maxLength(255)
                            ->helperText('Where is the equipment currently located'),

                        Forms\Components\TextInput::make('fuel_level')
                            ->label('Fuel Level (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->placeholder('0-100')
                            ->helperText('Current fuel level percentage'),

                        Forms\Components\TextInput::make('engine_hours')
                            ->label('Engine Hours')
                            ->numeric()
                            ->step(0.1)
                            ->minValue(0)
                            ->placeholder('0.0')
                            ->helperText('Total engine operating hours'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Additional Notes')
                    ->description('Any additional observations or information')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Status Notes')
                            ->placeholder('Any additional information about this status change, observations, or conditions...')
                            ->rows(3)
                            ->helperText('Optional: Record any special observations or conditions'),
                    ])
                    ->collapsed(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('status')
            ->columns([
                Tables\Columns\TextColumn::make('status_time')
                    ->label('Time')
                    ->dateTime('H:i:s')
                    ->sortable()
                    ->description(fn ($record): string => $record->status_time->diffForHumans()),

                Tables\Columns\TextColumn::make('equipment.code')
                    ->label('Equipment')
                    ->badge()
                    ->color('warning')
                    ->searchable()
                    ->description(fn ($record): ?string => 
                        $record->equipment ? "{$record->equipment->type_name} - {$record->equipment->brand}" : null
                    ),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'working' => 'success',
                        'idle' => 'warning',
                        'breakdown' => 'danger',
                        'maintenance' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'working' => 'Working',
                        'idle' => 'Idle',
                        'breakdown' => 'Breakdown',
                        'maintenance' => 'Maintenance',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('operator_name')
                    ->label('Operator')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('location')
                    ->label('Location')
                    ->searchable()
                    ->placeholder('-')
                    ->limit(30)
                    ->tooltip(fn ($record): ?string => $record->location)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('fuel_level')
                    ->label('Fuel Level')
                    ->badge()
                    ->color(fn (?float $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 70 => 'success',
                        $state >= 30 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (?float $state): string => 
                        $state !== null ? $state . '%' : '-'
                    )
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('engine_hours')
                    ->label('Engine Hours')
                    ->formatStateUsing(fn (?float $state): string => 
                        $state !== null ? number_format($state, 1) . 'h' : '-'
                    )
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(40)
                    ->tooltip(fn ($record): ?string => $record->notes)
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Logged At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('equipment_id')
                    ->label('Equipment')
                    ->relationship('equipment', 'code')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'idle' => 'Idle',
                        'working' => 'Working',
                        'breakdown' => 'Breakdown',
                        'maintenance' => 'Maintenance',
                    ]),

                Tables\Filters\Filter::make('today')
                    ->label('Today Only')
                    ->query(fn (Builder $query): Builder => $query->whereDate('status_time', today())),

                Tables\Filters\Filter::make('current_shift')
                    ->label('Current Shift')
                    ->query(function (Builder $query): Builder {
                        $hour = now()->hour;
                        if ($hour >= 7 && $hour < 15) {
                            // Shift A: 07:00-15:00
                            return $query->whereBetween('status_time', [
                                today()->setTime(7, 0),
                                today()->setTime(15, 0)
                            ]);
                        } elseif ($hour >= 15 && $hour < 23) {
                            // Shift B: 15:00-23:00
                            return $query->whereBetween('status_time', [
                                today()->setTime(15, 0),
                                today()->setTime(23, 0)
                            ]);
                        } else {
                            // Shift C: 23:00-07:00
                            return $query->where(function ($q) {
                                $q->whereBetween('status_time', [
                                    yesterday()->setTime(23, 0),
                                    today()->setTime(7, 0)
                                ]);
                            });
                        }
                    }),

                Tables\Filters\Filter::make('low_fuel')
                    ->label('Low Fuel (<30%)')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('fuel_level', '<', 30)->whereNotNull('fuel_level')
                    ),

                Tables\Filters\Filter::make('working_equipment')
                    ->label('Currently Working')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'working')),

                Tables\Filters\Filter::make('breakdown_equipment')
                    ->label('Breakdown Equipment')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'breakdown')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Log Equipment Status')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->modalHeading('Log Equipment Status Change')
                    ->modalDescription('Record a new equipment status change for this loading session')
                    ->successNotificationTitle('Status Logged')
                    ->after(function ($record) {
                        Notification::make()
                            ->title('Status Logged')
                            ->body("Equipment {$record->equipment->code} status changed to {$record->status}")
                            ->success()
                            ->send();
                    }),

                // Quick Status Update Actions
                Action::make('bulkStatusUpdate')
                    ->label('Bulk Status Update')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('equipment_ids')
                            ->label('Select Equipment')
                            ->multiple()
                            ->options(Equipment::active()->pluck('code', 'id'))
                            ->required()
                            ->searchable(),

                        Forms\Components\Select::make('status')
                            ->label('New Status')
                            ->options([
                                'idle' => 'Idle',
                                'working' => 'Working',
                                'breakdown' => 'Breakdown',
                                'maintenance' => 'Maintenance',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('operator_name')
                            ->label('Operator Name (Optional)'),

                        Forms\Components\TextInput::make('location')
                            ->label('Location (Optional)'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->placeholder('Reason for status change...'),
                    ])
                    ->action(function (array $data) {
                        $session = $this->getOwnerRecord();
                        
                        foreach ($data['equipment_ids'] as $equipmentId) {
                            $session->equipmentStatusLogs()->create([
                                'equipment_id' => $equipmentId,
                                'status' => $data['status'],
                                'operator_name' => $data['operator_name'] ?? null,
                                'location' => $data['location'] ?? null,
                                'status_time' => now(),
                                'notes' => $data['notes'] ?? null,
                            ]);
                        }

                        Notification::make()
                            ->title('Bulk Status Update')
                            ->body(count($data['equipment_ids']) . " equipment status updated to {$data['status']}")
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalHeading('Edit Status Log')
                    ->successNotificationTitle('Status Log Updated'),

                // Quick Actions for common status changes
                Action::make('setWorking')
                    ->label('Set Working')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn ($record): bool => $record->status !== 'working')
                    ->requiresConfirmation()
                    ->modalHeading('Set Equipment to Working')
                    ->modalDescription(fn ($record): string => 
                        "Set {$record->equipment->code} status to Working?"
                    )
                    ->action(function ($record): void {
                        $newLog = $record->replicate();
                        $newLog->status = 'working';
                        $newLog->status_time = now();
                        $newLog->notes = 'Status changed to working';
                        $newLog->save();

                        Notification::make()
                            ->title('Status Updated')
                            ->body("{$record->equipment->code} is now working")
                            ->success()
                            ->send();
                    }),

                Action::make('setIdle')
                    ->label('Set Idle')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->visible(fn ($record): bool => $record->status !== 'idle')
                    ->requiresConfirmation()
                    ->modalHeading('Set Equipment to Idle')
                    ->modalDescription(fn ($record): string => 
                        "Set {$record->equipment->code} status to Idle?"
                    )
                    ->action(function ($record): void {
                        $newLog = $record->replicate();
                        $newLog->status = 'idle';
                        $newLog->status_time = now();
                        $newLog->notes = 'Status changed to idle';
                        $newLog->save();

                        Notification::make()
                            ->title('Status Updated')
                            ->body("{$record->equipment->code} is now idle")
                            ->warning()
                            ->send();
                    }),

                Action::make('reportBreakdown')
                    ->label('Report Breakdown')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->visible(fn ($record): bool => $record->status !== 'breakdown')
                    ->form([
                        Forms\Components\Textarea::make('breakdown_description')
                            ->label('Breakdown Description')
                            ->required()
                            ->placeholder('Describe the breakdown issue...'),
                    ])
                    ->action(function ($record, array $data): void {
                        // Create breakdown status log
                        $newLog = $record->replicate();
                        $newLog->status = 'breakdown';
                        $newLog->status_time = now();
                        $newLog->notes = 'BREAKDOWN: ' . $data['breakdown_description'];
                        $newLog->save();

                        // Also create breakdown record
                        $record->equipment->breakdowns()->create([
                            'loading_session_id' => $record->loading_session_id,
                            'breakdown_type' => 'other',
                            'severity' => 'medium',
                            'description' => $data['breakdown_description'],
                            'start_time' => now(),
                            'status' => 'ongoing',
                            'reported_by' => auth()->id(),
                        ]);

                        Notification::make()
                            ->title('Breakdown Reported')
                            ->body("{$record->equipment->code} breakdown has been logged")
                            ->danger()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Status Log')
                    ->modalDescription('Are you sure you want to delete this status log entry?')
                    ->successNotificationTitle('Status Log Deleted'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Selected Status Logs')
                        ->modalDescription('Are you sure you want to delete the selected status log entries?')
                        ->successNotificationTitle('Status Logs Deleted'),
                ]),
            ])
            ->defaultSort('status_time', 'desc')
            ->emptyStateHeading('No Status Logs Recorded')
            ->emptyStateDescription('Start logging equipment status changes for this session.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Log First Status')
                    ->icon('heroicon-o-plus'),
            ])
            ->striped()
            ->paginated([10, 25, 50])
            ->poll('30s') // Auto-refresh every 30 seconds for real-time status monitoring
            ->groups([
                Tables\Grouping\Group::make('equipment.code')
                    ->label('Equipment')
                    ->collapsible(),
                
                Tables\Grouping\Group::make('status')
                    ->label('Status')
                    ->collapsible(),
            ]);
    }
}