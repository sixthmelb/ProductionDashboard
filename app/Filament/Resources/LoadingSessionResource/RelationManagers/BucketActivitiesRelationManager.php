<?php
// ===========================
// app/Filament/Resources/LoadingSessionResource/RelationManagers/BucketActivitiesRelationManager.php
// ===========================

namespace App\Filament\Resources\LoadingSessionResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Equipment;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;

class BucketActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'bucketActivities';

    protected static ?string $recordTitleAttribute = 'bucket_count';

    protected static ?string $label = 'Bucket Activity';

    protected static ?string $pluralLabel = 'Bucket Activities';

    protected static ?string $title = 'Bucket Loading Activities';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Equipment Selection')
                    ->description('Select the equipment involved in this bucket loading activity')
                    ->schema([
                        Forms\Components\Select::make('excavator_id')
                            ->label('Excavator')
                            ->options(function () {
                                return Equipment::excavators()
                                    ->active()
                                    ->get()
                                    ->mapWithKeys(function ($equipment) {
                                        return [$equipment->id => "{$equipment->code} - {$equipment->brand} {$equipment->model} ({$equipment->capacity} m³)"];
                                    });
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Select the excavator performing the loading'),

                        Forms\Components\Select::make('dumptruck_id')
                            ->label('Dump Truck')
                            ->options(function () {
                                return Equipment::dumptrucks()
                                    ->active()
                                    ->get()
                                    ->mapWithKeys(function ($equipment) {
                                        return [$equipment->id => "{$equipment->code} - {$equipment->brand} {$equipment->model} ({$equipment->capacity} ton)"];
                                    });
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Select the dump truck being loaded'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Activity Details')
                    ->description('Record the loading activity details')
                    ->schema([
                        Forms\Components\TextInput::make('bucket_count')
                            ->label('Number of Buckets')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(50)
                            ->helperText('How many buckets were loaded into the dump truck')
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                // Auto-calculate estimated tonnage based on bucket count and excavator capacity
                                $excavatorId = $get('excavator_id');
                                if ($excavatorId && $state) {
                                    $excavator = Equipment::find($excavatorId);
                                    if ($excavator && $excavator->capacity) {
                                        // Rough estimation: bucket capacity * count * material density (assume 1.8 ton/m³ for ore)
                                        $estimatedTonnage = $excavator->capacity * $state * 1.8;
                                        $set('estimated_tonnage', round($estimatedTonnage, 2));
                                    }
                                }
                            }),

                        Forms\Components\TextInput::make('estimated_tonnage')
                            ->label('Estimated Tonnage')
                            ->numeric()
                            ->step(0.01)
                            ->suffix('ton')
                            ->helperText('Estimated weight of material loaded (auto-calculated or manual entry)'),

                        Forms\Components\DateTimePicker::make('activity_time')
                            ->label('Activity Time')
                            ->required()
                            ->default(now())
                            ->seconds(false)
                            ->helperText('When did this loading activity occur'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Additional Information')
                    ->description('Optional additional details about this activity')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Activity Notes')
                            ->placeholder('Any additional observations, issues, or remarks about this loading activity...')
                            ->rows(3)
                            ->helperText('Optional: Record any special observations or conditions'),
                    ])
                    ->collapsed(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('bucket_count')
            ->columns([
                Tables\Columns\TextColumn::make('activity_time')
                    ->label('Time')
                    ->dateTime('H:i:s')
                    ->sortable()
                    ->description(fn ($record): string => $record->activity_time->diffForHumans()),

                Tables\Columns\TextColumn::make('excavator.code')
                    ->label('Excavator')
                    ->badge()
                    ->color('info')
                    ->description(fn ($record): ?string => 
                        $record->excavator ? "{$record->excavator->brand} {$record->excavator->model}" : null
                    ),

                Tables\Columns\TextColumn::make('dumptruck.code')
                    ->label('Dump Truck')
                    ->badge()
                    ->color('warning')
                    ->description(fn ($record): ?string => 
                        $record->dumptruck ? "{$record->dumptruck->brand} {$record->dumptruck->model}" : null
                    ),

                Tables\Columns\TextColumn::make('bucket_count')
                    ->label('Buckets')
                    ->numeric()
                    ->badge()
                    ->color('primary')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('estimated_tonnage')
                    ->label('Tonnage')
                    ->numeric(2)
                    ->suffix(' ton')
                    ->badge()
                    ->color('success')
                    ->alignCenter()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('excavator.capacity')
                    ->label('Excavator Capacity')
                    ->formatStateUsing(fn ($record): string => 
                        $record->excavator ? $record->excavator->capacity . ' m³' : '-'
                    )
                    ->toggleable(),

                Tables\Columns\TextColumn::make('dumptruck.capacity')
                    ->label('Truck Capacity')
                    ->formatStateUsing(fn ($record): string => 
                        $record->dumptruck ? $record->dumptruck->capacity . ' ton' : '-'
                    )
                    ->toggleable(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(30)
                    ->tooltip(fn ($record): ?string => $record->notes)
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Recorded At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('excavator_id')
                    ->label('Excavator')
                    ->relationship('excavator', 'code')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('dumptruck_id')
                    ->label('Dump Truck')
                    ->relationship('dumptruck', 'code')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('today')
                    ->label('Today Only')
                    ->query(fn (Builder $query): Builder => $query->whereDate('activity_time', today())),

                Tables\Filters\Filter::make('this_shift')
                    ->label('This Shift')
                    ->query(function (Builder $query): Builder {
                        $hour = now()->hour;
                        if ($hour >= 7 && $hour < 15) {
                            // Shift A: 07:00-15:00
                            return $query->whereBetween('activity_time', [
                                today()->setTime(7, 0),
                                today()->setTime(15, 0)
                            ]);
                        } elseif ($hour >= 15 && $hour < 23) {
                            // Shift B: 15:00-23:00
                            return $query->whereBetween('activity_time', [
                                today()->setTime(15, 0),
                                today()->setTime(23, 0)
                            ]);
                        } else {
                            // Shift C: 23:00-07:00
                            return $query->where(function ($q) {
                                $q->whereBetween('activity_time', [
                                    yesterday()->setTime(23, 0),
                                    today()->setTime(7, 0)
                                ]);
                            });
                        }
                    }),

                Tables\Filters\Filter::make('high_volume')
                    ->label('High Volume (>10 buckets)')
                    ->query(fn (Builder $query): Builder => $query->where('bucket_count', '>', 10)),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Record Bucket Activity')
                    ->icon('heroicon-o-plus-circle')
                    ->modalHeading('Record New Bucket Loading Activity')
                    ->modalDescription('Record a new bucket loading activity for this session')
                    ->successNotificationTitle('Bucket Activity Recorded')
                    ->after(function ($record) {
                        // Send notification about new activity
                        Notification::make()
                            ->title('Activity Recorded')
                            ->body("Added {$record->bucket_count} buckets from {$record->excavator->code} to {$record->dumptruck->code}")
                            ->success()
                            ->send();
                    }),

                // Quick Add Action for rapid data entry
                Action::make('quickAdd')
                    ->label('Quick Add')
                    ->icon('heroicon-o-bolt')
                    ->color('warning')
                    ->form([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('excavator_id')
                                    ->label('Excavator')
                                    ->options(Equipment::excavators()->active()->pluck('code', 'id'))
                                    ->required(),
                                
                                Forms\Components\Select::make('dumptruck_id')
                                    ->label('Dump Truck')
                                    ->options(Equipment::dumptrucks()->active()->pluck('code', 'id'))
                                    ->required(),
                                
                                Forms\Components\TextInput::make('bucket_count')
                                    ->label('Buckets')
                                    ->numeric()
                                    ->default(1)
                                    ->required(),
                            ]),
                    ])
                    ->action(function (array $data) {
                        $activity = $this->getOwnerRecord()->bucketActivities()->create([
                            'excavator_id' => $data['excavator_id'],
                            'dumptruck_id' => $data['dumptruck_id'],
                            'bucket_count' => $data['bucket_count'],
                            'activity_time' => now(),
                        ]);

                        Notification::make()
                            ->title('Quick Activity Added')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalHeading('Edit Bucket Activity')
                    ->successNotificationTitle('Activity Updated'),

                Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->action(function ($record) {
                        $newActivity = $record->replicate();
                        $newActivity->activity_time = now();
                        $newActivity->save();

                        Notification::make()
                            ->title('Activity Duplicated')
                            ->body('Created duplicate activity with current timestamp')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Bucket Activity')
                    ->modalDescription('Are you sure you want to delete this bucket activity? This will update the session totals.')
                    ->successNotificationTitle('Activity Deleted'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Selected Activities')
                        ->modalDescription('Are you sure you want to delete the selected bucket activities? This will update the session totals.')
                        ->successNotificationTitle('Activities Deleted'),
                ]),
            ])
            ->defaultSort('activity_time', 'desc')
            ->emptyStateHeading('No Bucket Activities Recorded')
            ->emptyStateDescription('Start recording bucket loading activities for this session.')
            ->emptyStateIcon('heroicon-o-truck')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Record First Activity')
                    ->icon('heroicon-o-plus'),
            ])
            ->striped()
            ->paginated([10, 25, 50])
            ->poll('30s'); // Auto-refresh every 30 seconds
    }
}