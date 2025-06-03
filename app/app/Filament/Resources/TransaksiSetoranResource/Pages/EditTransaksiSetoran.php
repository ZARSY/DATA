<?php

namespace App\Filament\Resources\TransaksiSetoranResource\Pages;

use App\Filament\Resources\TransaksiSetoranResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransaksiSetoran extends EditRecord
{
    protected static string $resource = TransaksiSetoranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
