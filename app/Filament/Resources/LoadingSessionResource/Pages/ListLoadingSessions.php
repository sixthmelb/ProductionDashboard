<?php

namespace App\Filament\Resources\LoadingSessionResource\Pages;


use App\Filament\Resources\LoadingSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Models\LoadingSession;
use Filament\Forms;
use Filament\Notifications\Notification;

class ListLoadingSessions extends ListRecords
{
    protected static string $resource = LoadingSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // START SESSION: Tombol utama untuk memulai session baru
            Actions\Action::make('startNewSession')
                ->label('🚀 Start New Session')
                ->icon('heroicon-o-play-circle')
                ->color('success')
                ->size('lg')
                ->visible(fn(): bool => auth()->user()->hasAnyRole(['superadmin', 'mcr']))
                ->form([
                    Forms\Components\Select::make('stacking_area_id')
                        ->label('Stacking Area')
                        ->relationship('stackingArea', 'name')
                        ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->code} - {$record->name}")
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\Select::make('shift')
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

                    Forms\Components\Textarea::make('notes')
                        ->label('Session Notes (Optional)')
                        ->placeholder('Any special instructions...')
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    // Generate session code
                    $sessionCode = 'LS-' . now()->format('Y-m-d') . '-' . str_pad(
                        LoadingSession::whereDate('start_time', today())->count() + 1,
                        3,
                        '0',
                        STR_PAD_LEFT
                    );

                    // Create loading session
                    $session = LoadingSession::create([
                        'session_code' => $sessionCode,
                        'stacking_area_id' => $data['stacking_area_id'],
                        'user_id' => auth()->id(),
                        'shift' => $data['shift'],
                        'start_time' => now(),
                        'status' => 'active',
                        'notes' => $data['notes'] ?? null,
                    ]);

                    Notification::make()
                        ->title('Session Started Successfully! 🎉')
                        ->body("Session {$session->session_code} has been started")
                        ->success()
                        ->duration(5000)
                        ->send();

                    // Redirect ke detail session
                    redirect()->to("/admin/loading-sessions/{$session->id}/edit");
                }),

            // CREATE: Tombol create biasa sebagai alternatif
            //Actions\CreateAction::make()
            //    ->visible(fn(): bool => auth()->user()->hasAnyRole(['superadmin', 'mcr'])),
        ];
    }
    
}

