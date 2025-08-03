<?php
// ===========================
// app/Filament/Resources/StackingAreaResource.php
// ===========================

namespace App\Filament\Resources;

use App\Filament\Resources\StackingAreaResource\Pages;
use App\Models\StackingArea;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StackingAreaResource extends Resource
{
    protected static ?string $model = StackingArea::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    
    protected static ?string $navigationLabel = 'Stacking Areas';
    
    protected static ?int $navigationSort = 4;
    
    protected static ?string $navigationGroup = 'Operations';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Area Information')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Area Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->placeholder('AREA-A1')
                            ->maxLength(50),

                        Forms\Components\TextInput::make('name')
                            ->label('Area Name')
                            ->required()
                            ->placeholder('Area Stacking A1')
                            ->maxLength(255),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only active areas can be used for loading sessions'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Location Details')
                    ->schema([
                        Forms\Components\Textarea::make('location')
                            ->label('Location Description')
                            ->placeholder('Describe the area location')
                            ->rows(3),

                        Forms\Components\TextInput::make('latitude')
                            ->label('Latitude')
                            ->numeric()
                            ->placeholder('-7.123456'),

                        Forms\Components\TextInput::make('longitude')
                            ->label('Longitude')
                            ->numeric()
                            ->placeholder('112.456789'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('coordinates')
                    ->label('Coordinates')
                    ->getStateUsing(function ($record) {
                        if ($record->latitude && $record->longitude) {
                            return "{$record->latitude}, {$record->longitude}";
                        }
                        return '-';
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('loading_sessions_count')
                    ->label('Total Sessions')
                    ->badge()
                    ->color('primary')
                    ->getStateUsing(function ($record) {
                        return $record->loadingSessions()->count();
                    }),

                Tables\Columns\TextColumn::make('location')
                    ->label('Location')
                    ->limit(50)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All areas')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                Tables\Filters\Filter::make('has_coordinates')
                    ->label('Has Coordinates')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereNotNull('latitude')->whereNotNull('longitude')
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('code')
            ->emptyStateHeading('No Stacking Areas Found')
            ->emptyStateDescription('Create your first stacking area.')
            ->emptyStateIcon('heroicon-o-map-pin');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStackingAreas::route('/'),
            'create' => Pages\CreateStackingArea::route('/create'),
            'edit' => Pages\EditStackingArea::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_active', true)->count();
    }
}
