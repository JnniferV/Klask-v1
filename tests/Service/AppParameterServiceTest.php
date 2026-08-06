<?php

namespace App\Tests\Service;

use App\Entity\AppParameter;
use App\Repository\AppParameterRepository;
use App\Service\AppParameterService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class AppParameterServiceTest extends TestCase
{
    private function service(?AppParameter $stored, ?CacheInterface $cache = null): AppParameterService
    {
        $repository = $this->createMock(AppParameterRepository::class);
        $repository->method('findOneBy')->willReturn($stored);

        return new AppParameterService($repository, $cache ?? $this->cache());
    }

    private function cache(): CacheInterface
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturnCallback(
            fn(string $key, callable $callback) => $callback($this->createMock(ItemInterface::class))
        );

        return $cache;
    }

    private function parameter(string $value, string $type): AppParameter
    {
        return (new AppParameter())->setParamKey('SEUIL')->setParamValue($value)->setParamType($type);
    }

    public function testUnEntierEnBaseEstRetourneType(): void
    {
        $this->assertSame(3, $this->service($this->parameter('3', 'integer'))->getInt('SEUIL'));
    }

    public function testLaValeurParDefautSertQuandLeParametreEstAbsent(): void
    {
        $this->assertSame(42, $this->service(null)->getInt('SEUIL', 42));
    }

    public function testUnBooleenEnBaseEstRetourneType(): void
    {
        $this->assertTrue($this->service($this->parameter('true', 'boolean'))->getBool('SEUIL'));
    }

    public function testUnBooleenAbsentRetombeSurLaValeurParDefaut(): void
    {
        $this->assertTrue($this->service(null)->getBool('SEUIL', true));
    }

    public function testLInvalidationViseLaCleDuParametre(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('delete')->with('app.param.SEUIL');

        $this->service(null, $cache)->invalidate('SEUIL');
    }
}
