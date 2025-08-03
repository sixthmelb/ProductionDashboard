<?php
// ===========================
// app/Filament/Resources/EquipmentResource.php - Fixed Card Structure
// ===========================

namespace App\Filament\Resources;

use App\Filament\Resources\EquipmentResource\Pages;
use App\Filament\Resources\EquipmentResource\RelationManagers;
use App\Models\Equipment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class EquipmentResource extends Resource
{
    protected static ?string $model = Equipment::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    
    protected static ?string $navigationLabel = 'Equipment';
    
    protected static ?string $modelLabel = 'Equipment';
    
    protected static ?string $pluralModelLabel = 'Equipment';
    
    protected static ?int $navigationSort = 1;
    
    protected static ?string $navigationGroup = 'Operations';

    // Role-based access methods
    public static function canCreate(): bool
    {
        return auth()->user()->can('create_equipment');
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->can('update_equipment');
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->can('delete_equipment');
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()->can('delete_any_equipment');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Equipment Information')
                    ->schema([
                        TextInput::make('code')
                            ->label('Equipment Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->placeholder('DT-001, EX-001')
                            ->maxLength(50),

                        Select::make('type')
                            ->label('Equipment Type')
                            ->required()
                            ->options([
                                'dumptruck' => 'Dump Truck',
                                'excavator' => 'Excavator',
                            ])
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                // Auto-generate code prefix
                                if ($state === 'dumptruck') {
                                    $set('code', 'DT-');
                                } elseif ($state === 'excavator') {
                                    $set('code', 'EX-');
                                }
                            }),

                        TextInput::make('brand')
                            ->label('Brand')
                            ->placeholder('Caterpillar, Komatsu')
                            ->maxLength(100),

                        TextInput::make('model')
                            ->label('Model')
                            ->placeholder('777D, PC800')
                            ->maxLength(100),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Specifications')
                    ->schema([
                        TextInput::make('capacity')
                            ->label('Capacity')
                            ->numeric()
                            ->step(0.1)
                            ->suffix(fn (callable $get) => 
                                $get('type') === 'dumptruck' ? 'ton' : 'm³'
                            )
                            ->placeholder('50.00'),

                        TextInput::make('year_manufacture')
                            ->label('Year of Manufacture')
                            ->numeric()
                            ->minValue(1990)
                            ->maxValue(date('Y') + 1)
                            ->placeholder('2020'),

                        Select::make('status')
                            ->label('Status')
                            ->required()
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'maintenance' => 'Under Maintenance',
                            ])
                            ->default('active'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notes')
                            ->placeholder('Additional notes about this equipment')
                            ->rows(3),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Equipment Code
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),
                
                // Equipment Type
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'dumptruck' => 'warning',
                        'excavator' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'dumptruck' => 'Dump Truck',
                        'excavator' => 'Excavator',
                        default => $state,
                    }),
                
                // Brand & Model
                TextColumn::make('brand_model')
                    ->label('Brand/Model')
                    ->getStateUsing(function ($record): string {
                        if (!$record) return '-';
                        return trim("{$record->brand} {$record->model}") ?: '-';
                    })
                    ->searchable(['brand', 'model']),                // Capacity
                TextColumn::make('capacity')
                    ->label('Capacity')
                    ->getStateUsing(function ($record): string {
                        if (!$record || !$record->capacity) return '-';
                        return $record->capacity . ' ' . ($record->type === 'dumptruck' ? 'ton' : 'm³');
                    })
                    ->sortable(),
                
                // Year
                TextColumn::make('year_manufacture')
                    ->label('Year')
                    ->sortable()
                    ->alignCenter(),
                
                // Current Status
                TextColumn::make('current_status')
                    ->label('Current Status')
                    ->badge()
                    ->color(fn ($record): string => match ($record->current_status ?? 'unknown') {
                        'working' => 'success',
                        'idle' => 'warning',
                        'breakdown' => 'danger',
                        'maintenance' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($record): string => match ($record->current_status ?? 'unknown') {
                        'working' => 'Working',
                        'idle' => 'Idle',
                        'breakdown' => 'Breakdown',
                        'maintenance' => 'Maintenance',
                        default => 'Unknown',
                    }),
                
                // Status with Icon
                TextColumn::make('status')
                    ->label('Equipment Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        'maintenance' => 'warning',
                        default => 'gray',
                    }),
                
                // Breakdown Info (if any)
                TextColumn::make('breakdown_info')
                    ->label('Breakdown Info')
                    ->getStateUsing(function ($record): string {
                        if (!$record) return '-';
                        if ($record->current_status === 'breakdown' && $record->breakdown_reason) {
                            return Str::limit($record->breakdown_reason, 30);
                        }
                        return '-';
                    })
                    ->color('danger')
                    ->visible(fn ($record): bool => $record && $record->current_status === 'breakdown'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Equipment Type')
                    ->options([
                        'dumptruck' => 'Dump Truck',
                        'excavator' => 'Excavator',
                    ]),

                SelectFilter::make('current_status')
                    ->label('Current Status')
                    ->options([
                        'working' => 'Working',
                        'idle' => 'Idle',
                        'breakdown' => 'Breakdown',
                        'maintenance' => 'Maintenance',
                    ]),

                Tables\Filters\Filter::make('has_breakdown')
                    ->label('Has Active Breakdown')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereHas('breakdowns', function ($q) {
                            $q->whereIn('status', ['ongoing', 'pending_parts']);
                        })
                    ),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn(): bool => auth()->user()->can('create_equipment')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->tooltip('Edit Equipment')
                    ->visible(fn(): bool => auth()->user()->can('update_equipment')),
                
                Action::make('updateStatus')
                    ->label('')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->iconButton()
                    ->tooltip('Update Status')
                    ->visible(fn(): bool => auth()->user()->can('update_equipment'))
                    ->form([
                        Select::make('status')
                            ->label('New Status')
                            ->options(function ($record) {
                                $options = [
                                    'idle' => 'Idle',
                                    'maintenance' => 'Maintenance',
                                ];
                                
                                if ($record && $record->canWork()) {
                                    $options['working'] = 'Working';
                                }
                                
                                return $options;
                            })
                            ->required()
                            ->helperText(function ($record) {
                                if ($record && !$record->canWork()) {
                                    return '⚠️ Equipment has active breakdown: ' . Str::limit($record->breakdown_reason ?? 'Unknown issue', 50);
                                }
                                return 'Select new equipment status';
                            }),
                        
                        TextInput::make('operator_name')
                            ->label('Operator Name'),
                        
                        TextInput::make('location')
                            ->label('Current Location'),
                        
                        TextInput::make('fuel_level')
                            ->label('Fuel Level (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100),
                        
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2),
                    ])
                    ->action(function (Equipment $record, array $data): void {
                        if ($data['status'] === 'working' && !$record->canWork()) {
                            Notification::make()
                                ->title('Cannot Set to Working')
                                ->body('Equipment has ongoing breakdown.')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        $record->statusLogs()->create([
                            'status' => $data['status'],
                            'operator_name' => $data['operator_name'] ?? null,
                            'location' => $data['location'] ?? null,
                            'fuel_level' => $data['fuel_level'] ?? null,
                            'notes' => $data['notes'] ?? null,
                            'status_time' => now(),
                        ]);
                        
                        Notification::make()
                            ->title('Status Updated')
                            ->body("Equipment {$record->code} status updated")
                            ->success()
                            ->send();
                    }),

                Action::make('reportBreakdown')
                    ->label('')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->iconButton()
                    ->tooltip('Report Breakdown')
                    ->visible(fn ($record): bool => 
                        $record && $record->canWork() && auth()->user()->can('create_equipment::breakdown')
                    )
                    ->form([
                        Select::make('breakdown_type')
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

                        Select::make('severity')
                            ->label('Severity')
                            ->options([
                                'low' => 'Low',
                                'medium' => 'Medium',
                                'high' => 'High',
                                'critical' => 'Critical',
                            ])
                            ->required()
                            ->default('medium'),

                        Textarea::make('description')
                            ->label('Description')
                            ->required()
                            ->placeholder('Describe the breakdown issue...'),
                    ])
                    ->action(function (Equipment $record, array $data): void {
                        $record->breakdowns()->create([
                            'breakdown_type' => $data['breakdown_type'],
                            'severity' => $data['severity'],
                            'description' => $data['description'],
                            'start_time' => now(),
                            'status' => 'ongoing',
                            'reported_by' => auth()->id(),
                        ]);

                        Notification::make()
                            ->title('Breakdown Reported')
                            ->body("Breakdown reported for {$record->code}")
                            ->warning()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn(): bool => auth()->user()->can('delete_equipment')),
                ])
                ->visible(fn(): bool => auth()->user()->can('delete_equipment')),
            ])
            ->defaultSort('code')
            ->striped()
            ->paginated([12, 24, 48])
            ->poll('30s')
            ->deferLoading()
            ->emptyStateHeading('No Equipment Found')
            ->emptyStateDescription('Start by adding your first piece of equipment.')
            ->emptyStateIcon('heroicon-o-truck');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\StatusLogsRelationManager::class,
            RelationManagers\BreakdownsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEquipment::route('/'),
            'create' => Pages\CreateEquipment::route('/create'),
            'edit' => Pages\EditEquipment::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                // Add any global scopes here
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return static::getModel()::count() > 10 ? 'warning' : 'primary';
    }
}