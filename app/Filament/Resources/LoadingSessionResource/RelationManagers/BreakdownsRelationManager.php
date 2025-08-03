<?php
// ===========================
// app/Filament/Resources/LoadingSessionResource/RelationManagers/BreakdownsRelationManager.php
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

class BreakdownsRelationManager extends RelationManager
{
    protected static string $relationship = 'breakdowns';

    protected static ?string $recordTitleAttribute = 'description';

    protected static ?string $label = 'Equipment Breakdown';

    protected static ?string $pluralLabel = 'Equipment Breakdowns';

    protected static ?string $title = 'Equipment Breakdowns During Session';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Breakdown Information')
                    ->description('Record equipment breakdown details that occurred during this loading session')
                    ->schema([
                        Forms\Components\Select::make('equipment_id')
                            ->label('Affected Equipment')
                            ->relationship('equipment', 'code')
                            ->getOptionLabelFromRecordUsing(fn ($record) => 
                                "{$record->code} - {$record->type_name} ({$record->brand} {$record->model})"
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Select the equipment that experienced the breakdown'),

                        Forms\Components\Select::make('breakdown_type')
                            ->label('Breakdown Category')
                            ->options([
                                'mechanical' => 'Mechanical - Moving parts, gears, belts',
                                'electrical' => 'Electrical - Wiring, sensors, controls',
                                'hydraulic' => 'Hydraulic - Pumps, cylinders, hoses',
                                'engine' => 'Engine - Motor, cooling, fuel system',
                                'tire' => 'Tire - Puncture, wear, damage',
                                'other' => 'Other - Specify in description',
                            ])
                            ->required()
                            ->helperText('Select the primary category of breakdown'),

                        Forms\Components\Select::make('severity')
                            ->label('Severity Level')
                            ->options([
                                'low' => 'Low - Minor issue, minimal impact',
                                'medium' => 'Medium - Moderate issue, some downtime',
                                'high' => 'High - Major issue, significant downtime',
                                'critical' => 'Critical - Equipment completely inoperable',
                            ])
                            ->required()
                            ->default('medium')
                            ->helperText('Assess the severity and impact of the breakdown'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Timeline & Status')
                    ->description('Track the breakdown timeline and current status')
                    ->schema([
                        Forms\Components\DateTimePicker::make('start_time')
                            ->label('Breakdown Start Time')
                            ->required()
                            ->default(now())
                            ->seconds(false)
                            ->helperText('When did the breakdown first occur'),

                        Forms\Components\DateTimePicker::make('end_time')
                            ->label('Repair Completion Time')
                            ->after('start_time')
                            ->seconds(false)
                            ->helperText('When was the equipment fully operational again (leave empty if ongoing)'),

                        Forms\Components\Select::make('status')
                            ->label('Current Status')
                            ->options([
                                'ongoing' => 'Ongoing - Still broken down',
                                'repaired' => 'Repaired - Equipment operational',
                                'pending_parts' => 'Pending Parts - Waiting for spare parts',
                            ])
                            ->required()
                            ->default('ongoing')
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if ($state === 'repaired' && !$get('end_time')) {
                                    $set('end_time', now());
                                }
                            })
                            ->helperText('Current status of the breakdown resolution'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Breakdown Details')
                    ->description('Detailed description and repair information')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Breakdown Description')
                            ->required()
                            ->rows(4)
                            ->placeholder('Provide detailed description of the breakdown: symptoms, what happened, initial assessment, root cause if known...')
                            ->helperText('Be as detailed as possible for maintenance records'),

                        Forms\Components\TextInput::make('repaired_by')
                            ->label('Repaired/Serviced By')
                            ->placeholder('Technician name, maintenance team, or contractor')
                            ->maxLength(255)
                            ->helperText('Who performed or is performing the repair'),

                        Forms\Components\TextInput::make('repair_cost')
                            ->label('Estimated/Actual Repair Cost')
                            ->numeric()
                            ->prefix('Rp')
                            ->placeholder('0')
                            ->helperText('Cost of parts, labor, contractor fees, etc.'),

                        Forms\Components\Hidden::make('reported_by')
                            ->default(auth()->id()),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('equipment.code')
                    ->label('Equipment')
                    ->searchable()
                    ->badge()
                    ->color('warning')
                    ->description(fn ($record): ?string => 
                        $record->equipment ? $record->equipment->type_name : null
                    ),

                Tables\Columns\TextColumn::make('breakdown_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'mechanical' => 'warning',
                        'electrical' => 'info',
                        'hydraulic' => 'primary',
                        'engine' => 'danger',
                        'tire' => 'success',
                        'other' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'mechanical' => 'Mechanical',
                        'electrical' => 'Electrical',
                        'hydraulic' => 'Hydraulic',
                        'engine' => 'Engine',
                        'tire' => 'Tire',
                        'other' => 'Other',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('severity')
                    ->label('Severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'low' => 'success',
                        'medium' => 'warning',
                        'high' => 'danger',
                        'critical' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'critical' => 'Critical',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ongoing' => 'danger',
                        'repaired' => 'success',
                        'pending_parts' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ongoing' => 'Ongoing',
                        'repaired' => 'Repaired',
                        'pending_parts' => 'Pending Parts',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(40)
                    ->tooltip(fn ($record): string => $record->description)
                    ->wrap(),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Started')
                    ->dateTime('d/m H:i')
                    ->sortable()
                    ->description(fn ($record): string => $record->start_time->diffForHumans()),

                Tables\Columns\TextColumn::make('duration_human')
                    ->label('Duration')
                    ->badge()
                    ->color(fn ($record): string => 
                        $record->status === 'ongoing' ? 'danger' : 'warning'
                    )
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('repair_cost')
                    ->label('Cost')
                    ->money('IDR')
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('repaired_by')
                    ->label('Repaired By')
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reportedBy.name')
                    ->label('Reported By')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('equipment_id')
                    ->label('Equipment')
                    ->relationship('equipment', 'code')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('breakdown_type')
                    ->label('Breakdown Type')
                    ->options([
                        'mechanical' => 'Mechanical',
                        'electrical' => 'Electrical',
                        'hydraulic' => 'Hydraulic',
                        'engine' => 'Engine',
                        'tire' => 'Tire',
                        'other' => 'Other',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'ongoing' => 'Ongoing',
                        'repaired' => 'Repaired',
                        'pending_parts' => 'Pending Parts',
                    ]),

                Tables\Filters\SelectFilter::make('severity')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'critical' => 'Critical',
                    ]),

                Tables\Filters\Filter::make('ongoing_only')
                    ->label('Ongoing Only')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'ongoing')),

                Tables\Filters\Filter::make('critical_high')
                    ->label('Critical & High Priority')
                    ->query(fn (Builder $query): Builder => $query->whereIn('severity', ['critical', 'high'])),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Report Breakdown')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->modalHeading('Report Equipment Breakdown')
                    ->modalDescription('Report a new equipment breakdown that occurred during this loading session')
                    ->successNotificationTitle('Breakdown Reported')
                    ->after(function ($record) {
                        // Update equipment status to breakdown
                        $record->equipment->statusLogs()->create([
                            'status' => 'breakdown',
                            'loading_session_id' => $record->loading_session_id,
                            'status_time' => $record->start_time,
                            'notes' => "Breakdown: {$record->breakdown_type} - {$record->description}",
                        ]);

                        Notification::make()
                            ->title('Breakdown Reported')
                            ->body("Equipment {$record->equipment->code} breakdown has been logged")
                            ->warning()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalHeading('Edit Breakdown Report')
                    ->successNotificationTitle('Breakdown Updated'),

                // Quick Repair Action
                Action::make('markRepaired')
                    ->label('Mark Repaired')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('success')
                    ->visible(fn ($record): bool => $record->status !== 'repaired')
                    ->form([
                        Forms\Components\DateTimePicker::make('end_time')
                            ->label('Repair Completion Time')
                            ->required()
                            ->default(now()),
                        
                        Forms\Components\TextInput::make('repaired_by')
                            ->label('Repaired By')
                            ->required()
                            ->placeholder('Technician name'),
                        
                        Forms\Components\TextInput::make('repair_cost')
                            ->label('Total Repair Cost (Rp)')
                            ->numeric()
                            ->prefix('Rp'),
                        
                        Forms\Components\Textarea::make('repair_notes')
                            ->label('Repair Details')
                            ->placeholder('What was done to fix the issue...'),
                    ])
                    ->action(function ($record, array $data): void {
                        $record->update([
                            'status' => 'repaired',
                            'end_time' => $data['end_time'],
                            'repaired_by' => $data['repaired_by'],
                            'repair_cost' => $data['repair_cost'] ?? 0,
                            'description' => $record->description . 
                                "\n\n--- REPAIR COMPLETED ---\n" . 
                                "Repaired by: {$data['repaired_by']}\n" .
                                "Completed: {$data['end_time']}\n" .
                                "Details: " . ($data['repair_notes'] ?? 'No additional details'),
                        ]);

                        // Update equipment status back to working or idle
                        $record->equipment->statusLogs()->create([
                            'status' => 'idle',
                            'loading_session_id' => $record->loading_session_id,
                            'status_time' => $data['end_time'],
                            'notes' => "Repaired from {$record->breakdown_type} breakdown",
                        ]);

                        Notification::make()
                            ->title('Breakdown Resolved')
                            ->body("Equipment {$record->equipment->code} has been repaired and is operational")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Breakdown Report')
                    ->modalDescription('Are you sure you want to delete this breakdown report? This action cannot be undone.')
                    ->successNotificationTitle('Breakdown Report Deleted'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Selected Breakdown Reports')
                        ->modalDescription('Are you sure you want to delete the selected breakdown reports?')
                        ->successNotificationTitle('Breakdown Reports Deleted'),
                ]),
            ])
            ->defaultSort('start_time', 'desc')
            ->emptyStateHeading('No Equipment Breakdowns')
            ->emptyStateDescription('Fortunately, no equipment breakdowns have been reported during this session.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->striped()
            ->paginated([10, 25, 50])
            ->poll('60s'); // Auto-refresh every 60 seconds for breakdown monitoring
    }
}