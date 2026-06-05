<?php

declare(strict_types=1);

namespace App\Filament\Operator\Pages;

use App\Authorization\OperatorPermissions;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Owner-only page to customise what each role can do for this client. Edits the
 * client's own role→permission rows; the operator policies read these live, so
 * changes take effect immediately. The owner role is always full and not shown.
 */
class RolePermissions extends Page implements HasForms
{
    use InteractsWithForms;

    /** Roles the owner can customise (owner is always full access). */
    public const EDITABLE_ROLES = ['manager', 'accountant', 'maintenance-staff'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Roles & Permissions';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 95;

    protected string $view = 'filament.operator.pages.role-permissions';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->tenant_id) {
            return false;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

        return $user->hasRole('owner');
    }

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->tenant_id, 404);

        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

        $state = [];
        foreach (self::EDITABLE_ROLES as $roleName) {
            $role = $this->role($roleName, $user->tenant_id);
            $state[$roleName] = $role ? $role->permissions->pluck('name')->all() : [];
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        $sections = [];

        foreach (self::EDITABLE_ROLES as $roleName) {
            $sections[] = Section::make(self::roleLabel($roleName))
                ->schema([
                    CheckboxList::make($roleName)
                        ->label('')
                        ->options(self::permissionOptions())
                        ->columns(2)
                        ->bulkToggleable(),
                ])
                ->collapsible();
        }

        return $schema->statePath('data')->components($sections);
    }

    public function save(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->tenant_id, 404);

        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

        $data = $this->form->getState();
        $valid = OperatorPermissions::all();

        foreach (self::EDITABLE_ROLES as $roleName) {
            $role = $this->role($roleName, $user->tenant_id);

            if ($role) {
                $selected = array_values(array_intersect($data[$roleName] ?? [], $valid));
                $role->syncPermissions($selected);
            }
        }

        Notification::make()
            ->title('Permissions updated')
            ->success()
            ->send();
    }

    protected function role(string $name, string $tenantId): ?Role
    {
        /** @var Role|null $role */
        $role = Role::query()
            ->where('name', $name)
            ->where('guard_name', 'web')
            ->where('tenant_id', $tenantId)
            ->first();

        return $role;
    }

    /** @return array<string, string> */
    protected static function permissionOptions(): array
    {
        $labels = [];

        foreach (OperatorPermissions::all() as $permission) {
            $labels[$permission] = self::permissionLabel($permission);
        }

        return $labels;
    }

    protected static function permissionLabel(string $permission): string
    {
        [$domain, $action] = array_pad(explode('.', $permission), 2, '');

        $domainLabel = match ($domain) {
            'inventory' => 'Properties & Units',
            'tenancy' => 'Renters & Leases',
            'billing' => 'Invoices & Payments',
            'expenses' => 'Expenses',
            'maintenance' => 'Maintenance',
            'cms' => 'Website (CMS)',
            'reports' => 'Reports',
            'team' => 'Team',
            default => ucfirst($domain),
        };

        return $domainLabel.' · '.ucfirst($action);
    }

    protected static function roleLabel(string $role): string
    {
        return match ($role) {
            'manager' => 'Manager',
            'accountant' => 'Accountant',
            'maintenance-staff' => 'Maintenance staff',
            default => ucfirst($role),
        };
    }
}
