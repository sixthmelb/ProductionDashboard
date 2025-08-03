<?php
// ===========================
// app/Filament/Widgets/QuickSessionStarter.php
// ===========================

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use App\Models\LoadingSession;
use App\Models\StackingArea;
use App\Models\Equipment;

class QuickSessionStarter extends Widget implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    protected static string $view = 'filament.widgets.quick-session-starter';

    protected int | string | array $columnSpan = 'full';

    public function startSessionAction(): Action
    {
        return Action::make('startSession')
            ->label('🚀 Start New Loading Session')
            ->color('success')
            ->size('lg')
            ->form([
                Select::make('stacking_area_id')
                    ->label('Stacking Area')
                    ->options(StackingArea::active()->pluck('name', 'id'))
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->code} - {$record->name}")
                    ->required()
                    ->searchable()
                    ->helperText('Select the area where loading will take place'),

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
                    })
                    ->helperText('Current shift will be auto-selected'),

                Select::make('equipment_ids')
                    ->label('Assign Equipment (Optional)')
                    ->multiple()
                    ->options(Equipment::active()->get()->mapWithKeys(function ($equipment) {
                        return [$equipment->id => "{$equipment->code} - {$equipment->type_name} ({$equipment->brand})"];
                    }))
                    ->searchable()
                    ->helperText('Select equipment to assign to this session'),

                Textarea::make('notes')
                    ->label('Session Notes')
                    ->placeholder('Any special instructions or observations for this session...')
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

                // Assign equipment if selected
                if (!empty($data['equipment_ids'])) {
                    foreach ($data['equipment_ids'] as $equipmentId) {
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
                }

                Notification::make()
                    ->title('Loading Session Started! 🎉')
                    ->body("Session {$session->session_code} has been started successfully")
                    ->success()
                    ->duration(5000)
                    ->send();

                // Redirect to session detail
                redirect()->to("/admin/loading-sessions/{$session->id}/edit");
            });
    }

    public function getActiveSessionsCount(): int
    {
        return LoadingSession::where('status', 'active')->count();
    }

    public function getTodaySessionsCount(): int
    {
        return LoadingSession::whereDate('start_time', today())->count();
    }

    public function getWorkingEquipmentCount(): int
    {
        return Equipment::whereHas('statusLogs', function ($query) {
            $query->where('status', 'working')
                  ->whereRaw('id = (SELECT MAX(id) FROM equipment_status_log WHERE equipment_id = equipment.id)');
        })->count();
    }
}

