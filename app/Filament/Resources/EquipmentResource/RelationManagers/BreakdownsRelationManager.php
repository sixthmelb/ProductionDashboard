<?php

// ===========================
// app/Filament/Resources/EquipmentResource/RelationManagers/BreakdownsRelationManager.php
// ===========================

namespace App\Filament\Resources\EquipmentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BreakdownsRelationManager extends RelationManager
{
    protected static string $relationship = 'breakdowns';

    protected static ?string $recordTitleAttribute = 'description';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Breakdown Information')
                    ->schema([
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
                            ->label('Severity')
                            ->options([
                                'low' => 'Low',
                                'medium' => 'Medium',
                                'high' => 'High',
                                'critical' => 'Critical',
                            ])
                            ->required()
                            ->default('medium'),

                        Forms\Components\Select::make('loading_session_id')
                            ->label('Loading Session')
                            ->relationship('loadingSession', 'session_code')
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Timeline')
                    ->schema([
                        Forms\Components\DateTimePicker::make('start_time')
                            ->label('Start Time')
                            ->required()
                            ->default(now()),

                        Forms\Components\DateTimePicker::make('end_time')
                            ->label('End Time')
                            ->after('start_time'),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'ongoing' => 'Ongoing',
                                'repaired' => 'Repaired',
                                'pending_parts' => 'Pending Parts',
                            ])
                            ->required()
                            ->default('ongoing'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Details')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->required()
                            ->rows(4)
                            ->placeholder('Describe the breakdown issue...'),

                        Forms\Components\TextInput::make('repaired_by')
                            ->label('Repaired By')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('repair_cost')
                            ->label('Repair Cost')
                            ->numeric()
                            ->prefix('Rp'),

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

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->tooltip(fn ($record): string => $record->description),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Started')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('duration_human')
                    ->label('Duration')
                    ->badge()
                    ->color('warning'),

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

                Tables\Columns\TextColumn::make('repair_cost')
                    ->label('Cost')
                    ->money('IDR')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('repaired_by')
                    ->label('Repaired By')
                    ->toggleable(),
            ])
            ->filters([
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
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
            ->defaultSort('start_time', 'desc');
    }
}