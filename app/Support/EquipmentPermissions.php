<?php

namespace App\Support;

use App\Models\User;

final class EquipmentPermissions
{
    public const OPERATIONS = [
        'category.create' => 'equipment.category.create',
        'category.update' => 'equipment.category.update',
        'model.create' => 'equipment.model.create',
        'model.update' => 'equipment.model.update',
        'stock.add' => 'equipment.stock.add',
        'unit.condition' => 'equipment.unit.condition',
        'unit.maintenance_start' => 'equipment.unit.maintenance',
        'unit.maintenance_finish' => 'equipment.unit.maintenance',
        'unit.lost' => 'equipment.unit.loss',
        'unit.found' => 'equipment.unit.recover',
        'unit.retire' => 'equipment.unit.retire',
        'model.photo_add' => 'equipment.model.photo.add',
        'unit.photo_add' => 'equipment.unit.photo.add',
        'loan.checkout' => 'equipment.loan.checkout',
        'loan.extend' => 'equipment.loan.extend',
        'loan.void' => 'equipment.loan.void',
        'loan.loss' => 'equipment.loan.loss',
        'return.create' => 'equipment.loan.return',
    ];

    public static function catalog(): array
    {
        return array_values(array_unique([...array_values(self::OPERATIONS), 'equipment.inventory.view', 'equipment.category.view', 'equipment.photo.view', 'equipment.audit.view', 'equipment.loan.view', 'equipment.report.view', 'equipment.report.export']));
    }

    public static function allows(User $user, string $permission): bool
    {
        $ordinary = array_values(array_diff(self::catalog(), ['equipment.unit.retire', 'equipment.loan.void']));
        $mapping = [User::ROLE_SUPER_ADMIN => self::catalog(), User::ROLE_PRODUCTION => $ordinary];

        return $user->status && in_array($permission, $mapping[$user->role?->name] ?? [], true);
    }
}
