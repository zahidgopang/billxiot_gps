<?php

namespace Tests\Unit;

use App\Services\Mobile\MobileAppVersionService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MobileAppVersionServiceTest extends TestCase
{
    private MobileAppVersionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MobileAppVersionService;
        config([
            'mobile.min_app_version' => '1.1.0',
            'mobile.min_build_number' => 16,
        ]);
    }

    #[DataProvider('versionMatrix')]
    public function test_version_support_matrix(?string $version, mixed $build, bool $expected): void
    {
        $this->assertSame($expected, $this->service->isSupported($version, $build));
    }

    public static function versionMatrix(): array
    {
        return [
            'missing version' => [null, 16, false],
            'older semver' => ['1.0.13', 99, false],
            'same semver older build' => ['1.1.0', 15, false],
            'same semver same build' => ['1.1.0', 16, true],
            'same semver newer build' => ['1.1.0', 17, true],
            'newer semver' => ['1.2.0', 1, true],
        ];
    }
}
