<?php
// ===========================
// app/Filament/Resources/LoadingSessionResource.php
// ===========================

namespace App\Filament\Resources;

use App\Filament\Resources\LoadingSessionResource\Pages;
use App\Filament\Resources\LoadingSessionResource\RelationManagers;
use App\Models\LoadingSession;
use App\Models\StackingArea;
use App\Models\Equipment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class LoadingSessionResource extends Resource
{
    protected static ?string $model = LoadingSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-play-circle';
    
    protected static ?string $navigationLabel = 'Loading Sessions';
    
    protected static ?string $modelLabel = 'Loading Session';
    
    protected static ?string $pluralModelLabel = 'Loading Sessions';
    
    protected static ?int $navigationSort = 2;
    
    protected static ?string $navigationGroup = 'Operations';

    public static function canCreate(): bool
    {
        return auth()->user()->hasAnyRole(['superadmin', 'mcr']);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasAnyRole(['superadmin', 'mcr', 'manager']);
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->hasAnyRole(['superadmin', 'mcr']);
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->hasRole('superadmin');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Session Information')
                    ->schema([
                        TextInput::make('session_code')
                            ->label('Session Code')
                            ->placeholder('Auto-generated')
                            ->disabled()
                            ->dehydrated(false),

                        Select::make('stacking_area_id')
                            ->label('Stacking Area')
                            ->relationship('stackingArea', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->code} - {$record->name}")
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                TextInput::make('code')
                                    ->label('Area Code')
                                    ->required()
                                    ->unique()
                                    ->placeholder('AREA-D1'),
                                TextInput::make('name')
                                    ->label('Area Name')
                                    ->required()
                                    ->placeholder('Area Stacking D1'),
                                Textarea::make('location')
                                    ->label('Location Description')
                                    ->rows(2),
                            ]),

                        Select::make('shift')
                            ->label('Shift')
                            ->options([
                                'A' => 'Shift A (07:00 - 15:00)',
                                'B' => 'Shift B (15:00 - 23:00)',
                                'C' => 'Shift C (23:00 - 07:00)',
                            ])
                            ->required()
                            ->default(function () {
                                $hour = now()->hour;
                                if ($hour >= 7 && $hour < 15) return 'A';
                                if ($hour >= 15 && $hour < 23) return 'B';
                                return 'C';
                            }),

                        Forms\Components\Hidden::make('user_id')
                            ->default(auth()->id()),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Schedule')
                    ->schema([
                        DateTimePicker::make('start_time')
                            ->label('Start Time')
                            ->required()
                            ->default(now())
                            ->seconds(false),

                        DateTimePicker::make('end_time')
                            ->label('End Time')
                            ->after('start_time')
                            ->seconds(false),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'active' => 'Active',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required()
                            ->default('active'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Summary')
                    ->schema([
                        TextInput::make('total_buckets')
                            ->label('Total Buckets')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('total_tonnage')
                            ->label('Total Tonnage (ton)')
                            ->numeric()
                            ->step(0.01)
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->placeholder('Session notes or observations')
                            ->rows(3),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('session_code')
                    ->label('Session Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'completed' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'ACTIVE',
                        'completed' => 'COMPLETED',
                        'cancelled' => 'CANCELLED',
                        default => strtoupper($state),
                    }),

                TextColumn::make('stackingArea.code')
                    ->label('Area')
                    ->searchable()
                    ->badge()
                    ->color('warning'),

                TextColumn::make('shift')
                    ->label('Shift')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'A' => 'success',
                        'B' => 'warning', 
                        'C' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('user.name')
                    ->label('Started By')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('start_time')
                    ->label('Started')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('duration_human')
                    ->label('Duration')
                    ->badge()
                    ->color(fn ($record): string => 
                        $record->status === 'active' ? 'warning' : 'info'
                    ),

                TextColumn::make('total_buckets')
                    ->label('Buckets')
                    ->numeric()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('total_tonnage')
                    ->label('Tonnage')
                    ->numeric(2)
                    ->suffix(' ton')
                    ->toggleable(),

                TextColumn::make('end_time')
                    ->label('Ended')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),

                SelectFilter::make('shift')
                    ->label('Shift')
                    ->options([
                        'A' => 'Shift A',
                        'B' => 'Shift B',
                        'C' => 'Shift C',
                    ]),

                SelectFilter::make('stacking_area_id')
                    ->label('Stacking Area')
                    ->relationship('stackingArea', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('today')
                    ->label('Today Only')
                    ->query(fn (Builder $query): Builder => $query->whereDate('start_time', today())),

                Tables\Filters\Filter::make('this_week')
                    ->label('This Week')
                    ->query(fn (Builder $query): Builder => $query->whereBetween('start_time', [
                        now()->startOfWeek(),
                        now()->endOfWeek()
                    ])),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // Quick Complete Session Action
                Action::make('completeSession')
                    ->label('Complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (LoadingSession $record): bool => $record->status === 'active')
                    ->requiresConfirmation()
                    ->modalHeading('Complete Loading Session')
                    ->modalDescription(fn (LoadingSession $record): string => 
                        "Are you sure you want to complete session {$record->session_code}?"
                    )
                    ->action(function (LoadingSession $record): void {
                        $record->update([
                            'status' => 'completed',
                            'end_time' => now(),
                        ]);

                        // Update equipment status to idle for this session
                        $record->equipmentStatusLogs()
                            ->where('status', 'working')
                            ->get()
                            ->each(function ($statusLog) {
                                $statusLog->equipment->statusLogs()->create([
                                    'status' => 'idle',
                                    'status_time' => now(),
                                    'notes' => 'Session completed',
                                ]);
                            });

                        Notification::make()
                            ->title('Session Completed')
                            ->body("Loading session {$record->session_code} has been completed successfully.")
                            ->success()
                            ->send();
                    }),

                // Quick Cancel Session Action
                Action::make('cancelSession')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (LoadingSession $record): bool => $record->status === 'active')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel Loading Session')
                    ->modalDescription(fn (LoadingSession $record): string => 
                        "Are you sure you want to cancel session {$record->session_code}?"
                    )
                    ->form([
                        Textarea::make('cancel_reason')
                            ->label('Cancellation Reason')
                            ->required()
                            ->placeholder('Please provide reason for cancellation'),
                    ])
                    ->action(function (LoadingSession $record, array $data): void {
                        $record->update([
                            'status' => 'cancelled',
                            'end_time' => now(),
                            'notes' => ($record->notes ? $record->notes . "\n\n" : '') 
                                     . "CANCELLED: " . $data['cancel_reason'],
                        ]);

                        Notification::make()
                            ->title('Session Cancelled')
                            ->body("Loading session {$record->session_code} has been cancelled.")
                            ->warning()
                            ->send();
                    }),

                // View Session Details
                Action::make('viewDetails')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (LoadingSession $record): string => 
                        static::getUrl('edit', ['record' => $record])
                    ),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('start_time', 'desc')
            ->striped()
            ->poll('30s') // Auto-refresh every 30 seconds for real-time monitoring
            ->emptyStateHeading('No Loading Sessions Found')
            ->emptyStateDescription('Start your first loading session to begin operations.')
            ->emptyStateIcon('heroicon-o-play-circle')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Start First Session')
                    ->icon('heroicon-o-play')
                    ->color('success'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BucketActivitiesRelationManager::class,
            RelationManagers\BreakdownsRelationManager::class,
            RelationManagers\EquipmentStatusLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoadingSessions::route('/'),
            'create' => Pages\CreateLoadingSession::route('/create'),
            'edit' => Pages\EditLoadingSession::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\QuickSessionStarter::class,
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'active')->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        $activeCount = static::getModel()::where('status', 'active')->count();
        return $activeCount > 0 ? 'success' : 'gray';
    }

    // Custom method to get active sessions for dashboard
    public static function getActiveSessions()
    {
        return static::getModel()::active()
            ->with(['stackingArea', 'user', 'bucketActivities'])
            ->orderBy('start_time', 'desc')
            ->get();
    }

    // Custom method to start new session with equipment assignment
    public static function startSessionWithEquipment(array $data, array $equipmentIds = [])
    {
        $session = static::getModel()::create($data);

        // Assign equipment to session and update their status
        foreach ($equipmentIds as $equipmentId) {
            $equipment = Equipment::find($equipmentId);
            if ($equipment) {
                $equipment->statusLogs()->create([
                    'status' => 'working',
                    'loading_session_id' => $session->id,
                    'status_time' => now(),
                    'notes' => "Assigned to session {$session->session_code}",
                ]);
            }
        }

        return $session;
    }
}