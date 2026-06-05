<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Operators;

use App\Filament\Operator\Resources\Operators\Pages\CreateOperator;
use App\Filament\Operator\Resources\Operators\Pages\EditOperator;
use App\Filament\Operator\Resources\Operators\Pages\ListOperators;
use App\Filament\Operator\Resources\Operators\Schemas\OperatorForm;
use App\Filament\Operator\Resources\Operators\Tables\OperatorsTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;

/**
 * Team management — operator staff for the current client. Backed by the shared
 * User model, scoped to type=operator within this tenant. Gated by team.manage
 * (owner + manager). Members are invited with a temporary password and forced
 * to set their own on first sign-in.
 */
class OperatorResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Team';

    protected static ?string $modelLabel = 'team member';

    protected static ?string $pluralModelLabel = 'team';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 90;

    public static function form(Schema $schema): Schema
    {
        return OperatorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OperatorsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('type', User::TYPE_OPERATOR)
            ->where('tenant_id', static::currentTenantId());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOperators::route('/'),
            'create' => CreateOperator::route('/create'),
            'edit' => EditOperator::route('/{record}/edit'),
        ];
    }

    /* ----- Authorization: gated by the team.manage permission ----- */

    public static function canViewAny(): bool
    {
        return static::canManageTeam();
    }

    public static function canCreate(): bool
    {
        return static::canManageTeam();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManageTeam();
    }

    public static function canView(Model $record): bool
    {
        return static::canManageTeam();
    }

    public static function canDelete(Model $record): bool
    {
        return false; // deactivate via status, never hard-delete a login
    }

    protected static function canManageTeam(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->tenant_id) {
            return false;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

        return $user->can('team.manage');
    }

    protected static function currentTenantId(): ?string
    {
        $user = auth()->user();

        return $user instanceof User ? $user->tenant_id : null;
    }
}
