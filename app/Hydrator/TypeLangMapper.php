<?php

declare(strict_types=1);

namespace App\Hydrator;

use Lsp\Contracts\Hydrator\ExtractorInterface;
use Lsp\Contracts\Hydrator\HydratorInterface;
use Lsp\Hydrator\Bridge\TypeLang\Exception\MappingException;
use Psr\SimpleCache\CacheInterface;
use TypeLang\Mapper\Mapper;
use TypeLang\Mapper\Mapping\Driver\AttributeDriver;
use TypeLang\Mapper\Mapping\Driver\DocBlockDriver;
use TypeLang\Mapper\Mapping\Driver\Psr16CachedDriver;
use TypeLang\Mapper\Mapping\Driver\ReflectionDriver;
use TypeLang\Mapper\Runtime\Configuration;

final class TypeLangMapper implements HydratorInterface, ExtractorInterface
{
    private readonly Mapper $mapper;

    public function __construct(?CacheInterface $cache = null)
    {
        $driver = new AttributeDriver(
            delegate: new DocBlockDriver(
                delegate: new ReflectionDriver(),
            ),
        );

        if ($cache !== null) {
            $driver = new Psr16CachedDriver(
                cache: $cache,
                delegate: $driver,
            );
        }

        $this->mapper = new Mapper(
            platform: new LanguageServerPlatform(driver: $driver),
            config: new Configuration(
                objectsAsArrays: true,
                detailedTypes: true,
                strictTypes: false,
            ),
        );
    }

    public function extract(mixed $data, ?string $type = null): mixed
    {
        try {
            return $this->mapper->normalize($data, $type);
        } catch (\Throwable $e) {
            throw new MappingException($e->getMessage(), previous: $e);
        }
    }

    public function hydrate(string $type, mixed $data): mixed
    {
        try {
            return $this->mapper->denormalize($data, $type);
        } catch (\Throwable $e) {
            throw new MappingException($e->getMessage(), previous: $e);
        }
    }
}
