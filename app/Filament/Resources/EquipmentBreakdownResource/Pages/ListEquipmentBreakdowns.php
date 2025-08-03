<?php

namespace App\Filament\Resources\EquipmentBreakdownResource\Pages;

use App\Filament\Resources\EquipmentBreakdownResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEquipmentBreakdowns extends ListRecords
{
    protected static string $resource = EquipmentBreakdownResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
