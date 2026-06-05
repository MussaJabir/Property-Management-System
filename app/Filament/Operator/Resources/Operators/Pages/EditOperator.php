<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Operators\Pages;

use App\Filament\Operator\Resources\Operators\OperatorResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;

class EditOperator extends EditRecord
{
    protected static string $resource = OperatorResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $record */
        $record = $this->record;

        app(PermissionRegistrar::class)->setPermissionsTeamId((string) $record->tenant_id);
        $data['role'] = $record->roles->pluck('name')->first();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        $isSelf = (int) $record->getKey() === (int) auth()->id();

        $record->update([
            'name' => $data['name'],
            // Self-edit can't change own status (form disables it); guard anyway.
            'status' => $isSelf ? $record->status : ($data['status'] ?? $record->status),
        ]);

        if (! $isSelf && ! empty($data['role'])) {
            app(PermissionRegistrar::class)->setPermissionsTeamId((string) $record->tenant_id);
            $record->syncRoles([$data['role']]);
        }

        return $record;
    }
}
