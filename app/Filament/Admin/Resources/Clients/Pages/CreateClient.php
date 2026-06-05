<?php

namespace App\Filament\Admin\Resources\Clients\Pages;

use App\Filament\Admin\Resources\Clients\ClientResource;
use App\Models\Client;
use App\Services\Admin\OperatorProvisioner;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;

    /**
     * Cached owner fields pulled off the form payload before the model is
     * saved. ClientForm marks them as dehydrated(false), so they never reach
     * the Client model — we keep a copy here and consume it in afterCreate.
     *
     * @var array<string, ?string>
     */
    protected array $ownerData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->ownerData = [
            'name' => $data['owner_name'] ?? null,
            'email' => $data['owner_email'] ?? null,
            'phone' => $data['owner_phone'] ?? null,
        ];

        unset($data['owner_name'], $data['owner_email'], $data['owner_phone']);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Client $client */
        $client = $this->record;

        $name = trim((string) ($this->ownerData['name'] ?? ''));
        $email = trim((string) ($this->ownerData['email'] ?? ''));

        if ($name === '' || $email === '') {
            return;
        }

        $user = app(OperatorProvisioner::class)
            ->provision($client, $name, $email, 'owner', $this->ownerData['phone'] ?? null);

        if ($user) {
            Notification::make()
                ->title('Owner account created')
                ->body('Activation link sent to '.$user->email.'.')
                ->success()
                ->send();
        }
    }
}
