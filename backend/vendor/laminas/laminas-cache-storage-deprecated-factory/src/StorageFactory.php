<?php

declare(strict_types=1);

namespace Laminas\Cache;

use Laminas\Cache\Service\StorageAdapterFactory;
use Laminas\Cache\Service\StorageAdapterFactoryInterface;
use Laminas\Cache\Service\StoragePluginFactory;
use Laminas\Cache\Service\StoragePluginFactoryInterface;
use Laminas\Cache\Storage\Adapter\AdapterOptions;
use Laminas\Cache\Storage\Adapter\Apcu;
use Laminas\Cache\Storage\Adapter\BlackHole;
use Laminas\Cache\Storage\Adapter\ExtMongoDb;
use Laminas\Cache\Storage\Adapter\Filesystem;
use Laminas\Cache\Storage\Adapter\Memcached;
use Laminas\Cache\Storage\Adapter\Memory;
use Laminas\Cache\Storage\Adapter\Redis;
use Laminas\Cache\Storage\Adapter\Session;
use Laminas\Cache\Storage\AdapterPluginManager;
use Laminas\Cache\Storage\Plugin\PluginOptions;
use Laminas\Cache\Storage\PluginAwareInterface;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\ArrayUtils;
use Traversable;
use Webmozart\Assert\Assert;

use function array_merge;
use function class_exists;
use function get_class;
use function is_array;
use function is_string;
use function iterator_to_array;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;

final class StorageFactory
{
    public const ADAPTER_IDENTIFIER_TO_DELEGATOR = [
        'apcu'            => Apcu\AdapterPluginManagerDelegatorFactory::class,
        'Apcu'            => Apcu\AdapterPluginManagerDelegatorFactory::class,
        'ApcU'            => Apcu\AdapterPluginManagerDelegatorFactory::class,
        'APCu'            => Apcu\AdapterPluginManagerDelegatorFactory::class,
        Apcu::class       => Apcu\AdapterPluginManagerDelegatorFactory::class,
        'black_hole'      => BlackHole\AdapterPluginManagerDelegatorFactory::class,
        BlackHole::class  => BlackHole\AdapterPluginManagerDelegatorFactory::class,
        'blackhole'       => BlackHole\AdapterPluginManagerDelegatorFactory::class,
        'blackHole'       => BlackHole\AdapterPluginManagerDelegatorFactory::class,
        'BlackHole'       => BlackHole\AdapterPluginManagerDelegatorFactory::class,
        'ext_mongo_db'    => ExtMongoDb\AdapterPluginManagerDelegatorFactory::class,
        'extmongodb'      => ExtMongoDb\AdapterPluginManagerDelegatorFactory::class,
        'extMongoDb'      => ExtMongoDb\AdapterPluginManagerDelegatorFactory::class,
        'extMongoDB'      => ExtMongoDb\AdapterPluginManagerDelegatorFactory::class,
        'ExtMongoDb'      => ExtMongoDb\AdapterPluginManagerDelegatorFactory::class,
        'ExtMongoDB'      => ExtMongoDb\AdapterPluginManagerDelegatorFactory::class,
        ExtMongoDb::class => ExtMongoDb\AdapterPluginManagerDelegatorFactory::class,
        'filesystem'      => Filesystem\AdapterPluginManagerDelegatorFactory::class,
        Filesystem::class => Filesystem\AdapterPluginManagerDelegatorFactory::class,
        'Filesystem'      => Filesystem\AdapterPluginManagerDelegatorFactory::class,
        'memcached'       => Memcached\AdapterPluginManagerDelegatorFactory::class,
        Memcached::class  => Memcached\AdapterPluginManagerDelegatorFactory::class,
        'Memcached'       => Memcached\AdapterPluginManagerDelegatorFactory::class,
        'memory'          => Memory\AdapterPluginManagerDelegatorFactory::class,
        Memory::class     => Memory\AdapterPluginManagerDelegatorFactory::class,
        'Memory'          => Memory\AdapterPluginManagerDelegatorFactory::class,
        'redis'           => Redis\AdapterPluginManagerDelegatorFactory::class,
        'Redis'           => Redis\AdapterPluginManagerDelegatorFactory::class,
        Redis::class      => Redis\AdapterPluginManagerDelegatorFactory::class,
        'session'         => Session\AdapterPluginManagerDelegatorFactory::class,
        'Session'         => Session\AdapterPluginManagerDelegatorFactory::class,
        Session::class    => Session\AdapterPluginManagerDelegatorFactory::class,
    ];

    /**
     * Plugin manager for loading adapters
     *
     * @var null|AdapterPluginManager
     */
    protected static $adapters;

    /**
     * Plugin manager for loading plugins
     *
     * @var null|Storage\PluginManager
     */
    protected static $plugins;

    /** @var StoragePluginFactoryInterface|null */
    private static $storagePluginFactory;

    /**
     * The storage factory
     * This can instantiate storage adapters and plugins.
     *
     * @param array|Traversable $cfg
     * @return StorageInterface
     * @throws Exception\InvalidArgumentException
     */
    public static function factory($cfg)
    {
        if ($cfg instanceof Traversable) {
            $cfg = ArrayUtils::iteratorToArray($cfg);
        }

        /** @psalm-suppress DocblockTypeContradiction */
        if (! is_array($cfg)) {
            throw new Exception\InvalidArgumentException(
                'The factory needs an associative array '
                . 'or a Traversable object as an argument'
            );
        }

        // instantiate the adapter
        if (! isset($cfg['adapter'])) {
            throw new Exception\InvalidArgumentException('Missing "adapter"');
        }
        /** @psalm-suppress MixedAssignment */
        $adapterName    = $cfg['adapter'];
        $adapterOptions = [];
        if (is_array($cfg['adapter'])) {
            if (! isset($cfg['adapter']['name'])) {
                throw new Exception\InvalidArgumentException('Missing "adapter.name"');
            }

            /** @psalm-suppress MixedAssignment */
            $adapterName = $cfg['adapter']['name'];
            /** @psalm-suppress MixedAssignment */
            $adapterOptions = $cfg['adapter']['options'] ?? [];
        }
        if (isset($cfg['options'])) {
            /** @psalm-suppress MixedArgument */
            $adapterOptions = array_merge($adapterOptions, $cfg['options']);
        }

        Assert::isMap($adapterOptions);
        $adapter = self::adapterFactory((string) $adapterName, $adapterOptions);

        // add plugins
        if (isset($cfg['plugins'])) {
            if (! $adapter instanceof PluginAwareInterface) {
                throw new Exception\RuntimeException(sprintf(
                    "The adapter '%s' doesn't implement '%s' and therefore can't handle plugins",
                    get_class($adapter),
                    PluginAwareInterface::class
                ));
            }

            if (! is_array($cfg['plugins'])) {
                throw new Exception\InvalidArgumentException(
                    'Plugins needs to be an array'
                );
            }

            foreach ($cfg['plugins'] as $k => $v) {
                $pluginPrio = 1; // default priority

                if (is_string($k)) {
                    if (! is_array($v)) {
                        throw new Exception\InvalidArgumentException(
                            "'plugins.{$k}' needs to be an array"
                        );
                    }
                    $pluginName    = $k;
                    $pluginOptions = $v;
                } elseif (is_array($v)) {
                    if (! isset($v['name'])) {
                        throw new Exception\InvalidArgumentException(
                            "Invalid plugins[{$k}] or missing plugins[{$k}].name"
                        );
                    }
                    $pluginName = (string) $v['name'];

                    if (isset($v['options'])) {
                        /** @var array $pluginOptions */
                        $pluginOptions = $v['options'];
                        Assert::isMap($pluginOptions);
                    } else {
                        $pluginOptions = [];
                    }

                    if (isset($v['priority'])) {
                        /** @var numeric $pluginPrio */
                        $pluginPrio = $v['priority'];
                    }
                } else {
                    Assert::string($v);
                    $pluginName    = $v;
                    $pluginOptions = [];
                }

                $plugin = self::pluginFactory($pluginName, $pluginOptions);
                if (! $adapter->hasPlugin($plugin)) {
                    $adapter->addPlugin($plugin, (int) $pluginPrio);
                }
            }
        }

        return $adapter;
    }

    /**
     * Instantiate a storage adapter
     *
     * @param string|StorageInterface                  $adapterName
     * @param array|Traversable|AdapterOptions $options
     * @throws Exception\RuntimeException
     */
    public static function adapterFactory($adapterName, $options = []): StorageInterface
    {
        if ($options instanceof Traversable) {
            $options = iterator_to_array($options);
        }

        if (is_string($adapterName)) {
            Assert::stringNotEmpty($adapterName);
            $adapterFactory = self::getAdapterFactory($adapterName);
            if ($options instanceof AdapterOptions) {
                $adapter = $adapterFactory->create($adapterName);
                $adapter->setOptions($options);

                return $adapter;
            }

            Assert::isMap($options);
            return $adapterFactory->create($adapterName, $options);
        }

        Assert::isInstanceOf($adapterName, StorageInterface::class);
        $adapter = $adapterName;

        if ($options) {
            $adapter->setOptions($options);
        }

        return $adapter;
    }

    /**
     * Get the adapter plugin manager
     */
    public static function getAdapterPluginManager(?string $potentialAdapterName = null): AdapterPluginManager
    {
        if (self::$adapters === null) {
            self::$adapters = new AdapterPluginManager(new ServiceManager());
        }

        if ($potentialAdapterName === null) {
            return self::$adapters;
        }

        $adapters = self::$adapters;

        // Config provider probably already applied
        if ($adapters->has($potentialAdapterName)) {
            return $adapters;
        }

        $delegatorClassName = self::ADAPTER_IDENTIFIER_TO_DELEGATOR[$potentialAdapterName] ?? null;
        if ($delegatorClassName === null || ! class_exists($delegatorClassName)) {
            return $adapters;
        }

        $delegator = new $delegatorClassName();

        // Ensure we do not trigger deprecation runtime error
        set_error_handler(static function (): bool {
            return true;
        });
        $container = $adapters->getServiceLocator();
        restore_error_handler();
        return $delegator(
            $container,
            $potentialAdapterName,
            static function () use ($adapters): AdapterPluginManager {
                return $adapters;
            }
        );
    }

    /**
     * Instantiate a storage plugin
     *
     * @param string|Storage\Plugin\PluginInterface          $pluginName
     * @param array|Traversable|PluginOptions $options
     * @throws Exception\RuntimeException
     */
    public static function pluginFactory($pluginName, $options = []): Storage\Plugin\PluginInterface
    {
        if ($options instanceof Traversable) {
            $options = iterator_to_array($options);
        }

        $pluginFactory = self::getPluginFactory();

        if (is_string($pluginName)) {
            Assert::stringNotEmpty($pluginName);

            if ($options instanceof PluginOptions) {
                $plugin = $pluginFactory->create($pluginName);
                $plugin->setOptions($options);
                return $plugin;
            }

            Assert::isMap($options);
            return $pluginFactory->create($pluginName, $options);
        }

        $plugin = $pluginName;

        if ($options) {
            if (! $options instanceof Storage\Plugin\PluginOptions) {
                $options = new PluginOptions($options);
            }
            $plugin->setOptions($options);
        }

        return $plugin;
    }

    public static function getPluginManager(): Storage\PluginManager
    {
        if (self::$plugins === null) {
            self::$plugins = new Storage\PluginManager(new ServiceManager());
        }

        return self::$plugins;
    }

    /**
     * Change the adapter plugin manager
     */
    public static function setAdapterPluginManager(AdapterPluginManager $adapters): void
    {
        self::resetAdapterPluginManager();
        self::$adapters = $adapters;
    }

    /**
     * Resets the internal adapter plugin manager
     */
    public static function resetAdapterPluginManager(): void
    {
        self::$adapters = null;
    }

    /**
     * Change the plugin manager
     */
    public static function setPluginManager(Storage\PluginManager $plugins): void
    {
        self::resetPluginManager();
        self::$plugins = $plugins;
    }

    /**
     * Resets the internal plugin manager
     */
    public static function resetPluginManager(): void
    {
        self::$plugins              = null;
        self::$storagePluginFactory = null;
    }

    private static function getPluginFactory(): StoragePluginFactoryInterface
    {
        if (self::$storagePluginFactory !== null) {
            return self::$storagePluginFactory;
        }

        $plugins = self::getPluginManager();
        return new StoragePluginFactory($plugins);
    }

    private static function getAdapterFactory(?string $adapterName = null): StorageAdapterFactoryInterface
    {
        $adapters = self::getAdapterPluginManager($adapterName);
        return new StorageAdapterFactory($adapters, self::getPluginFactory());
    }
}
