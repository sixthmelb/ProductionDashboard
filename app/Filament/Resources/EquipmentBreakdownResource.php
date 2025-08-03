<?php
// ===========================
// app/Filament/Resources/EquipmentBreakdownResource.php - Updated with Smart Status Management
// ===========================

namespace App\Filament\Resources;

use App\Filament\Resources\EquipmentBreakdownResource\Pages;
use App\Models\EquipmentBreakdown;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;

class EquipmentBreakdownResource extends Resource
{
    protected static ?string $model = EquipmentBreakdown::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    
    protected static ?string $navigationLabel = 'Equipment Breakdowns';
    
    protected static ?int $navigationSort = 3;
    
    protected static ?string $navigationGroup = 'Operations';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Breakdown Information')
                    ->schema([
                        Forms\Components\Select::make('equipment_id')
                            ->label('Equipment')
                            ->relationship('equipment', 'code')
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->code} - {$record->type_name}")
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('loading_session_id')
                            ->label('Loading Session')
                            ->relationship('loadingSession', 'session_code')
                            ->searchable()
                            ->preload()
                            ->placeholder('Select if breakdown occurred during session'),

                        Forms\Components\Select::make('breakdown_type')
                            ->label('Breakdown Type')
                            ->options([
                                'mechanical' => 'Mechanical',
                                'electrical' => 'Electrical',
                                'hydraulic' => 'Hydraulic',
                                'engine' => 'Engine',
                                'tire' => 'Tire',
                                'other' => 'Other',
                            ])
                            ->required(),

                        Forms\Components\Select::make('severity')
                            ->label('Severity Level')
                            ->options([
                                'low' => 'Low - Minor issue',
                                'medium' => 'Medium - Moderate issue',
                                'high' => 'High - Major issue',
                                'critical' => 'Critical - Immediate attention',
                            ])
                            ->required()
                            ->default('medium'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Timeline')
                    ->schema([
                        Forms\Components\DateTimePicker::make('start_time')
                            ->label('Breakdown Start Time')
                            ->required()
                            ->default(now())
                            ->seconds(false),

                        Forms\Components\DateTimePicker::make('end_time')
                            ->label('Repair Completion Time')
                            ->after('start_time')
                            ->seconds(false),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'ongoing' => 'Ongoing',
                                'repaired' => 'Repaired',
                                'pending_parts' => 'Pending Parts',
                            ])
                            ->required()
                            ->default('ongoing')
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state === 'repaired' && !$set('end_time')) {
                                    $set('end_time', now());
                                }
                            }),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Details')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Breakdown Description')
                            ->required()
                            ->rows(4)
                            ->placeholder('Describe the breakdown issue, symptoms, and initial assessment...'),

                        Forms\Components\TextInput::make('repaired_by')
                            ->label('Repaired By')
                            ->placeholder('Technician or repair team name')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('repair_cost')
                            ->label('Repair Cost (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->placeholder('0')
                            ->default(0),

                        Forms\Components\Hidden::make('reported_by')
                            ->default(auth()->id()),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('equipment.code')
                    ->label('Equipment')
                    ->searchable()
                    ->sortable()
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
                    ->limit(50)
                    ->tooltip(fn ($record): string => $record->description),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Started')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('duration_human')
                    ->label('Duration')
                    ->badge()
                    ->color('warning')
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

                Tables\Columns\TextColumn::make('loadingSession.session_code')
                    ->label('Session')
                    ->badge()
                    ->color('info')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('equipment_id')
                    ->label('Equipment')
                    ->relationship('equipment', 'code'),

                Tables\Filters\SelectFilter::make('breakdown_type')
                    ->label('Type')
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

                Tables\Filters\Filter::make('today')
                    ->label('Today Only')
                    ->query(fn (Builder $query): Builder => $query->whereDate('start_time', today())),

                Tables\Filters\Filter::make('this_week')
                    ->label('This Week')
                    ->query(fn (Builder $query): Builder => $query->whereBetween('start_time', [
                        now()->startOfWeek(),
                        now()->endOfWeek()
                    ])),

                Tables\Filters\Filter::make('active_breakdowns')
                    ->label('Active Breakdowns')
                    ->query(fn (Builder $query): Builder => $query->whereIn('status', ['ongoing', 'pending_parts'])),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // Enhanced Mark Repaired Action with Equipment Status Update
                Action::make('markRepaired')
                    ->label('Mark Repaired')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('success')
                    ->visible(fn (EquipmentBreakdown $record): bool => $record->status !== 'repaired')
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
                            ->prefix('Rp')
                            ->default(0),
                        
                        Forms\Components\Textarea::make('repair_notes')
                            ->label('Repair Details')
                            ->placeholder('What was done to fix the issue...'),

                        Forms\Components\Toggle::make('set_to_working')
                            ->label('Set Equipment to Working Status')
                            ->helperText('If enabled, equipment will be set to working after repair completion')
                            ->default(false),
                    ])
                    ->action(function (EquipmentBreakdown $record, array $data): void {
                        $record->update([
                            'status' => 'repaired',
                            'end_time' => $data['end_time'],
                            'repaired_by' => $data['repaired_by'],
                            'repair_cost' => $data['repair_cost'] ?? 0,
                            'description' => $record->description . 
                                "\n\n--- REPAIR COMPLETED ---\n" . 
                                "Repaired by: {$data['repaired_by']}\n" .
                                "Completed: {$data['end_time']}\n" .
                                "Cost: Rp " . number_format($data['repair_cost'] ?? 0) . "\n" .
                                "Details: " . ($data['repair_notes'] ?? 'No additional details'),
                        ]);

                        // Check if user wants to set equipment to working
                        if ($data['set_to_working'] && $record->equipment->canWork()) {
                            $record->equipment->statusLogs()->create([
                                'status' => 'working',
                                'loading_session_id' => $record->loading_session_id,
                                'status_time' => $data['end_time'],
                                'notes' => "Equipment repaired and ready for operation. Breakdown resolved: {$record->breakdown_type}",
                            ]);
                        }

                        Notification::make()
                            ->title('Breakdown Resolved')
                            ->body("Equipment {$record->equipment->code} has been repaired and is operational")
                            ->success()
                            ->send();
                    }),

                // Reopen Breakdown Action
                Action::make('reopenBreakdown')
                    ->label('Reopen')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (EquipmentBreakdown $record): bool => $record->status === 'repaired')
                    ->form([
                        Forms\Components\Select::make('new_status')
                            ->label('New Status')
                            ->options([
                                'ongoing' => 'Ongoing - Issue persists',
                                'pending_parts' => 'Pending Parts - Need spare parts',
                            ])
                            ->required(),
                        
                        Forms\Components\Textarea::make('reopen_reason')
                            ->label('Reason for Reopening')
                            ->required()
                            ->placeholder('Explain why this breakdown is being reopened...'),
                    ])
                    ->action(function (EquipmentBreakdown $record, array $data): void {
                        $record->update([
                            'status' => $data['new_status'],
                            'end_time' => null,
                            'description' => $record->description . 
                                "\n\n--- BREAKDOWN REOPENED ---\n" . 
                                "Reopened: " . now() . "\n" .
                                "New Status: {$data['new_status']}\n" .
                                "Reason: " . $data['reopen_reason'],
                        ]);

                        Notification::make()
                            ->title('Breakdown Reopened')
                            ->body("Equipment {$record->equipment->code} breakdown has been reopened")
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    // Bulk Mark Repaired Action
                    Tables\Actions\BulkAction::make('bulkMarkRepaired')
                        ->label('Mark Selected as Repaired')
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->color('success')
                        ->form([
                            Forms\Components\TextInput::make('repaired_by')
                                ->label('Repaired By')
                                ->required()
                                ->placeholder('Technician name'),
                            
                            Forms\Components\DateTimePicker::make('end_time')
                                ->label('Repair Completion Time')
                                ->required()
                                ->default(now()),
                            
                            Forms\Components\Textarea::make('notes')
                                ->label('Repair Notes')
                                ->placeholder('Bulk repair details...'),
                        ])
                        ->action(function (array $data, $records): void {
                            foreach ($records as $record) {
                                if ($record->status !== 'repaired') {
                                    $record->update([
                                        'status' => 'repaired',
                                        'end_time' => $data['end_time'],
                                        'repaired_by' => $data['repaired_by'],
                                        'description' => $record->description . 
                                            "\n\n--- BULK REPAIR COMPLETED ---\n" . 
                                            "Repaired by: {$data['repaired_by']}\n" .
                                            "Completed: {$data['end_time']}\n" .
                                            "Notes: " . ($data['notes'] ?? 'Bulk repair operation'),
                                    ]);
                                }
                            }

                            Notification::make()
                                ->title('Bulk Repair Completed')
                                ->body(count($records) . ' breakdowns marked as repaired')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('start_time', 'desc')
            ->poll('60s') // Auto-refresh every minute
            ->emptyStateHeading('No Equipment Breakdowns')
            ->emptyStateDescription('Great! No breakdowns have been reported.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEquipmentBreakdowns::route('/'),
            'create' => Pages\CreateEquipmentBreakdown::route('/create'),
            'edit' => Pages\EditEquipmentBreakdown::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $ongoingCount = static::getModel()::where('status', 'ongoing')->count();
        return $ongoingCount > 0 ? (string) $ongoingCount : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        $ongoingCount = static::getModel()::where('status', 'ongoing')->count();
        return $ongoingCount > 0 ? 'danger' : null;
    }
}