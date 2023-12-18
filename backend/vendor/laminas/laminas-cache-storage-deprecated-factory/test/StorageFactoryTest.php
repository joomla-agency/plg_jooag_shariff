<?php

declare(strict_types=1);

namespace Laminas\Cache;

use Laminas\Cache\Storage\Adapter\ExtMongoDb;
use Laminas\Cache\Storage\Plugin\Serializer;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Serializer\Adapter\Json;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function bin2hex;
use function random_bytes;

final class StorageFactoryTest extends TestCase
{
    /**
     * @return iterable<non-empty-string,array{0:non-empty-string}>
     */
    public function storageIdentifierNames(): iterable
    {
        foreach (array_keys(StorageFactory::ADAPTER_IDENTIFIER_TO_DELEGATOR) as $identifier) {
            yield $identifier => [$identifier];
        }
    }

    /**
     * @dataProvider storageIdentifierNames
     */
    public function testCanInstantiateAdapterViaAdapterFactoryWithoutOptions(string $identifier): void
    {
        $adapter = StorageFactory::adapterFactory($identifier);
        self::assertInstanceOf(StorageInterface::class, $adapter);
    }

    public function testCanInstantiateAdapterViaAdapterFactoryWithOptions(): void
    {
        $options = [
            'resourceId' => bin2hex(random_bytes(10)),
        ];

        $adapter = StorageFactory::adapterFactory(ExtMongoDb::class, $options);
        self::assertInstanceOf(ExtMongoDb::class, $adapter);
        $adapterOptions = $adapter->getOptions();
        self::assertEquals($options['resourceId'], $adapterOptions->getResourceId());
    }

    public function testCanInstantiatePluginViaPluginFactory(): void
    {
        $plugin = StorageFactory::pluginFactory(Serializer::class, [
            'serializer' => 'json',
        ]);

        $options = $plugin->getOptions();
        self::assertInstanceOf(Json::class, $options->getSerializer());
    }
}
