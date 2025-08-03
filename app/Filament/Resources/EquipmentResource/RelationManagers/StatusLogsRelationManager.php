<?php

// ===========================
// app/Filament/Resources/EquipmentResource/RelationManagers/StatusLogsRelationManager.php
// ===========================

namespace App\Filament\Resources\EquipmentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StatusLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'statusLogs';

    protected static ?string $recordTitleAttribute = 'status';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'idle' => 'Idle',
                        'working' => 'Working',
                        'breakdown' => 'Breakdown',
                        'maintenance' => 'Maintenance',
                    ])
                    ->required(),

                Forms\Components\Select::make('loading_session_id')
                    ->label('Loading Session')
                    ->relationship('loadingSession', 'session_code')
                    ->searchable()
                    ->preload(),

                Forms\Components\TextInput::make('operator_name')
                    ->label('Operator Name')
                    ->maxLength(255),

                Forms\Components\TextInput::make('location')
                    ->label('Location')
                    ->maxLength(255),

                Forms\Components\TextInput::make('fuel_level')
                    ->label('Fuel Level (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100),

                Forms\Components\TextInput::make('engine_hours')
                    ->label('Engine Hours')
                    ->numeric()
                    ->minValue(0),

                Forms\Components\DateTimePicker::make('status_time')
                    ->label('Status Time')
                    ->required()
                    ->default(now()),

                Forms\Components\Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('status')
            ->columns([
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

                Tables\Columns\TextColumn::make('loadingSession.session_code')
                    ->label('Session')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('operator_name')
                    ->label('Operator')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('location')
                    ->label('Location')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('fuel_level')
                    ->label('Fuel %')
                    ->badge()
                    ->color(fn (?float $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 70 => 'success',
                        $state >= 30 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (?float $state): string => 
                        $state ? $state . '%' : '-'
                    ),

                Tables\Columns\TextColumn::make('engine_hours')
                    ->label('Engine Hours')
                    ->formatStateUsing(fn (?float $state): string => 
                        $state ? number_format($state, 1) . 'h' : '-'
                    )
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status_time')
                    ->label('Time')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(50)
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'idle' => 'Idle',
                        'working' => 'Working',
                        'breakdown' => 'Breakdown',
                        'maintenance' => 'Maintenance',
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
            ->defaultSort('status_time', 'desc');
    }
}