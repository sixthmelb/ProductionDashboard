<?php

namespace App\Filament\Resources\EquipmentBreakdownResource\Pages;

use App\Filament\Resources\EquipmentBreakdownResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEquipmentBreakdown extends EditRecord
{
    protected static string $resource = EquipmentBreakdownResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
