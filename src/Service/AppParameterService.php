<?php

namespace App\Service;

use App\Entity\AppParameter;
use App\Repository\AppParameterRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class AppParameterService
{
    private const TTL = 60;

    public function __construct(
        private readonly AppParameterRepository $repo,
        private readonly CacheInterface $cache,
    ) {
    }

    public function getInt(string $key, int $default = 0): int
    {
        return (int) ($this->load($key) ?? $default);
    }

    public function getBool(string $key, bool $default = false): bool
    {
        return (bool) ($this->load($key) ?? $default);
    }

    /** @return AppParameter[] */
    public function findAllOrdered(): array
    {
        return $this->repo->findBy([], ['paramKey' => 'ASC']);
    }

    public function invalidate(string $key): void
    {
        $this->cache->delete('app.param.'.$key);
    }

    // pas entité Doctrine
    private function load(string $key): int|float|bool|string|null
    {
        return $this->cache->get('app.param.'.$key, function (ItemInterface $item) use ($key): int|float|bool|string|null {
            $item->expiresAfter(self::TTL);

            return $this->repo->findOneBy(['paramKey' => $key])?->getCastedValue();
        });
    }
}
