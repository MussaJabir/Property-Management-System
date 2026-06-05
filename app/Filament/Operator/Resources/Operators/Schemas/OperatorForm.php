<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Operators\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;

class OperatorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(120),

            TextInput::make('email')
                ->email()
                ->required()
                ->maxLength(160)
                ->unique('users', 'email', ignoreRecord: true)
                ->disabledOn('edit')
                ->helperText('Used to sign in; an invite with a temporary password is emailed here. Cannot be changed later.'),

            Select::make('role')
                ->label('Role')
                ->options(self::roleOptions())
                ->required()
                ->native(false)
                ->disabled(fn (?Model $record): bool => self::isSelf($record))
                ->helperText('Controls what this member can do. Tune each role under Roles & Permissions.'),

            Select::make('status')
                ->options(['active' => 'Active', 'disabled' => 'Disabled'])
                ->default('active')
                ->required()
                ->native(false)
                ->visibleOn('edit')
                ->disabled(fn (?Model $record): bool => self::isSelf($record))
                ->helperText('Disabled members cannot sign in.'),
        ]);
    }

    /**
     * Role choices. Only an owner may grant the owner role, so a manager can't
     * mint owner-level accounts.
     *
     * @return array<string, string>
     */
    protected static function roleOptions(): array
    {
        $options = [
            'manager' => 'Manager',
            'accountant' => 'Accountant',
            'maintenance-staff' => 'Maintenance staff',
        ];

        $user = auth()->user();

        if ($user instanceof User && $user->tenant_id) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

            if ($user->hasRole('owner')) {
                $options = ['owner' => 'Owner'] + $options;
            }
        }

        return $options;
    }

    /** True when the edited record is the signed-in user (prevents self-lockout). */
    protected static function isSelf(?Model $record): bool
    {
        return $record !== null && (int) $record->getKey() === (int) auth()->id();
    }
}
