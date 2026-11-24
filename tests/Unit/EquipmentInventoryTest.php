<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use App\Services\Equipment\InventoryPhotos;
use App\Services\Equipment\InventoryRules;
use App\Support\EquipmentPermissions;
use Illuminate\Container\Container;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EquipmentInventoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $container = new Container;
        $container->instance('validator', new Factory(new Translator(new ArrayLoader, 'en'), $container));
        Facade::setFacadeApplication($container);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    public static function transitions(): array
    {
        return [
            ['active', 'unit.maintenance_start', 'good', false, 'maintenance'],
            ['maintenance', 'unit.maintenance_finish', 'good', false, 'active'],
            ['maintenance', 'unit.maintenance_finish', 'minor_damage', false, null],
            ['active', 'unit.lost', 'good', false, 'lost'],
            ['lost', 'unit.found', 'good', false, 'maintenance'],
            ['lost', 'unit.retire', 'good', false, 'retired'],
            ['retired', 'unit.found', 'good', false, null],
            ['retired', 'unit.retire', 'good', false, null],
            ['active', 'unit.retire', 'good', true, null],
            ['lost', 'unit.found', 'good', true, null],
            ['active', 'unit.maintenance_start', 'good', true, null],
            ['active', 'unit.lost', 'good', true, null],
        ];
    }

    #[DataProvider('transitions')]
    public function test_lifecycle(string $current, string $action, string $condition, bool $borrowed, ?string $expected): void
    {
        if ($expected === null) {
            $this->expectException(ValidationException::class);
        }
        $this->assertSame($expected, InventoryRules::transition($current, $action, $condition, $borrowed));
    }

    public function test_numbering_never_wraps(): void
    {
        $this->assertSame([999999], InventoryRules::reserve(999999, 1));
        $this->assertSame('CAM-1000', InventoryRules::assetCode('CAM', 1000));
        $this->expectException(ValidationException::class);
        InventoryRules::reserve(999999, 2);
    }

    public static function permissions(): array
    {
        return [
            [User::ROLE_SUPER_ADMIN, true, 'equipment.unit.retire', true],
            [User::ROLE_PRODUCTION, true, 'equipment.stock.add', true],
            [User::ROLE_PRODUCTION, true, 'equipment.unit.retire', false],
            [User::ROLE_NEWS, true, 'equipment.inventory.view', false],
            [User::ROLE_TVCLUB, true, 'equipment.inventory.view', false],
            [User::ROLE_SUPER_ADMIN, false, 'equipment.stock.add', false],
            [User::ROLE_SUPER_ADMIN, true, 'equipment.unknown', false],
            ['Unknown', true, 'equipment.inventory.view', false],
        ];
    }

    #[DataProvider('permissions')]
    public function test_permission_mapping(string $name, bool $active, string $permission, bool $expected): void
    {
        $user = new User(['status' => $active]);
        $user->setRelation('role', new Role(['name' => $name]));
        $this->assertSame($expected, EquipmentPermissions::allows($user, $permission));
    }

    public function test_image_reencoding_strips_trailing_payload(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'eq-photo-');
        $image = imagecreatetruecolor(64, 64);
        imagepng($image, $path);
        imagedestroy($image);
        file_put_contents($path, '<?php BAD_PAYLOAD ?>', FILE_APPEND);
        try {
            $photo = (new InventoryPhotos)->prepare(new UploadedFile($path, 'photo.png', 'image/png', null, true));
            $this->assertStringNotContainsString('BAD_PAYLOAD', $photo['bytes']);
            $this->assertSame(hash('sha256', $photo['bytes']), $photo['sha256']);
            $this->assertSame(strlen($photo['bytes']), $photo['file_size']);
        } finally {
            unlink($path);
        }
    }

    public function test_fake_image_is_rejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'eq-bad-');
        file_put_contents($path, 'not an image');
        try {
            $this->expectException(ValidationException::class);
            (new InventoryPhotos)->prepare(new UploadedFile($path, 'fake.jpg', 'image/jpeg', null, true));
        } finally {
            unlink($path);
        }
    }
}
