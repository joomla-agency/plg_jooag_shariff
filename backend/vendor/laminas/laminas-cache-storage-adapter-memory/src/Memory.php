<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter;

use Laminas\Cache\Exception;
use Laminas\Cache\Storage\AvailableSpaceCapableInterface;
use Laminas\Cache\Storage\Capabilities;
use Laminas\Cache\Storage\ClearByNamespaceInterface;
use Laminas\Cache\Storage\ClearByPrefixInterface;
use Laminas\Cache\Storage\ClearExpiredInterface;
use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\IterableInterface;
use Laminas\Cache\Storage\TaggableInterface;
use Laminas\Cache\Storage\TotalSpaceCapableInterface;
use stdClass;
use Traversable;

use function array_diff;
use function array_keys;
use function count;
use function memory_get_usage;
use function microtime;
use function strpos;

use const PHP_INT_MAX;

final class Memory extends AbstractAdapter implements
    AvailableSpaceCapableInterface,
    ClearByPrefixInterface,
    ClearByNamespaceInterface,
    ClearExpiredInterface,
    FlushableInterface,
    IterableInterface,
    TaggableInterface,
    TotalSpaceCapableInterface
{
    /**
     * Data Array
     *
     * Format:
     * array(
     *     <NAMESPACE> => array(
     *         <KEY> => array(
     *             0 => <VALUE>
     *             1 => <MICROTIME>
     *             ['tags' => <TAGS>]
     *         )
     *     )
     * )
     */
    private array $data = [];

    /**
     * Set options.
     *
     * @see    getOptions()
     *
     * @param array|Traversable|MemoryOptions $options
     * @return Memory
     */
    public function setOptions($options)
    {
        if (! $options instanceof MemoryOptions) {
            $options = new MemoryOptions($options);
        }

        return parent::setOptions($options);
    }

    /**
     * Get options.
     *
     * @see setOptions()
     *
     * @return MemoryOptions
     */
    public function getOptions()
    {
        if (! $this->options) {
            $this->setOptions(new MemoryOptions());
        }
        return $this->options;
    }

    /* TotalSpaceCapableInterface */

    /**
     * Get total space in bytes
     *
     * @return int
     */
    public function getTotalSpace()
    {
        return $this->getOptions()->getMemoryLimit();
    }

    /* AvailableSpaceCapableInterface */

    /**
     * Get available space in bytes
     *
     * @return int|float
     */
    public function getAvailableSpace()
    {
        $total = $this->getOptions()->getMemoryLimit();
        $avail = $total - (float) memory_get_usage(true);
        return $avail > 0 ? $avail : 0;
    }

    /* IterableInterface */

    /**
     * Get the storage iterator
     *
     * @return KeyListIterator
     */
    public function getIterator(): Traversable
    {
        $ns   = $this->getOptions()->getNamespace();
        $keys = [];

        if (isset($this->data[$ns])) {
            foreach ($this->data[$ns] as $key => &$tmp) {
                if ($this->internalHasItem($key)) {
                    $keys[] = $key;
                }
            }
        }

        return new KeyListIterator($this, $keys);
    }

    /* FlushableInterface */

    /**
     * Flush the whole storage
     *
     * @return bool
     */
    public function flush()
    {
        $this->data = [];
        return true;
    }

    /* ClearExpiredInterface */

    /**
     * Remove expired items
     *
     * @return bool
     */
    public function clearExpired()
    {
        $ttl = $this->getOptions()->getTtl();
        if ($ttl <= 0) {
            return true;
        }

        $ns = $this->getOptions()->getNamespace();
        if (! isset($this->data[$ns])) {
            return true;
        }

        $data = &$this->data[$ns];
        foreach ($data as $key => &$item) {
            if (microtime(true) >= $data[$key][1] + $ttl) {
                unset($data[$key]);
            }
        }

        return true;
    }

    /**
     * Remove items of given namespace
     *
     * @param string $namespace
     * @return bool
     */
    public function clearByNamespace($namespace)
    {
        $namespace = (string) $namespace;
        if ($namespace === '') {
            throw new Exception\InvalidArgumentException('No namespace given');
        }

        unset($this->data[$namespace]);
        return true;
    }

    /**
     * Remove items matching given prefix
     *
     * @param string $prefix
     * @return bool
     */
    public function clearByPrefix($prefix)
    {
        $prefix = (string) $prefix;
        if ($prefix === '') {
            throw new Exception\InvalidArgumentException('No prefix given');
        }

        $ns = $this->getOptions()->getNamespace();
        if (! isset($this->data[$ns])) {
            return true;
        }

        $data = &$this->data[$ns];
        foreach ($data as $key => &$item) {
            if (strpos($key, $prefix) === 0) {
                unset($data[$key]);
            }
        }

        return true;
    }

    /* TaggableInterface */

    /**
     * Set tags to an item by given key.
     * An empty array will remove all tags.
     *
     * @param string   $key
     * @param string[] $tags
     * @return bool
     */
    public function setTags($key, array $tags)
    {
        $ns = $this->getOptions()->getNamespace();
        if (! isset($this->data[$ns][$key])) {
            return false;
        }

        $this->data[$ns][$key]['tags'] = $tags;
        return true;
    }

    /**
     * Get tags of an item by given key
     *
     * @param string $key
     * @return string[]|FALSE
     */
    public function getTags($key)
    {
        $ns = $this->getOptions()->getNamespace();
        if (! isset($this->data[$ns][$key])) {
            return false;
        }

        return $this->data[$ns][$key]['tags'] ?? [];
    }

    /**
     * Remove items matching given tags.
     *
     * If $disjunction only one of the given tags must match
     * else all given tags must match.
     *
     * @param string[] $tags
     * @param  bool  $disjunction
     * @return bool
     */
    public function clearByTags(array $tags, $disjunction = false)
    {
        $ns = $this->getOptions()->getNamespace();
        if (! isset($this->data[$ns])) {
            return true;
        }

        $tagCount = count($tags);
        $data     = &$this->data[$ns];
        foreach ($data as $key => &$item) {
            if (isset($item['tags'])) {
                $diff = array_diff($tags, $item['tags']);
                if (($disjunction && count($diff) < $tagCount) || (! $disjunction && ! $diff)) {
                    unset($data[$key]);
                }
            }
        }

        return true;
    }

    /* reading */

    /**
     * Internal method to get an item.
     *
     * @param  string  $normalizedKey
     * @param  bool $success
     * @param  mixed   $casToken
     * @return mixed Data on success, null on failure
     * @throws Exception\ExceptionInterface
     */
    protected function internalGetItem(&$normalizedKey, &$success = null, &$casToken = null)
    {
        $options = $this->getOptions();
        $ns      = $options->getNamespace();
        $success = isset($this->data[$ns][$normalizedKey]);
        if ($success) {
            $data = &$this->data[$ns][$normalizedKey];
            $ttl  = $options->getTtl();
            if ($ttl && microtime(true) >= $data[1] + $ttl) {
                $success = false;
            }
        }

        if (! $success) {
            return;
        }

        $casToken = $data[0];
        return $data[0];
    }

    /**
     * Internal method to get multiple items.
     *
     * @param  array $normalizedKeys
     * @return array Associative array of keys and values
     * @throws Exception\ExceptionInterface
     */
    protected function internalGetItems(array &$normalizedKeys)
    {
        $options = $this->getOptions();
        $ns      = $options->getNamespace();
        if (! isset($this->data[$ns])) {
            return [];
        }

        $data = &$this->data[$ns];
        $ttl  = $options->getTtl();
        $now  = microtime(true);

        $result = [];
        foreach ($normalizedKeys as $normalizedKey) {
            if (isset($data[$normalizedKey])) {
                if (! $ttl || $now < $data[$normalizedKey][1] + $ttl) {
                    $result[$normalizedKey] = $data[$normalizedKey][0];
                }
            }
        }

        return $result;
    }

    /**
     * Internal method to test if an item exists.
     *
     * @param  string $normalizedKey
     * @return bool
     */
    protected function internalHasItem(&$normalizedKey)
    {
        $options = $this->getOptions();
        $ns      = $options->getNamespace();
        if (! isset($this->data[$ns][$normalizedKey])) {
            return false;
        }

        // check if expired
        $ttl = $options->getTtl();
        if ($ttl && microtime(true) >= $this->data[$ns][$normalizedKey][1] + $ttl) {
            return false;
        }

        return true;
    }

    /**
     * Internal method to test multiple items.
     *
     * @param array $normalizedKeys
     * @return array Array of found keys
     */
    protected function internalHasItems(array &$normalizedKeys)
    {
        $options = $this->getOptions();
        $ns      = $options->getNamespace();
        if (! isset($this->data[$ns])) {
            return [];
        }

        $data = &$this->data[$ns];
        $ttl  = $options->getTtl();
        $now  = microtime(true);

        $result = [];
        foreach ($normalizedKeys as $normalizedKey) {
            if (isset($data[$normalizedKey])) {
                if (! $ttl || $now < $data[$normalizedKey][1] + $ttl) {
                    $result[] = $normalizedKey;
                }
            }
        }

        return $result;
    }

    /**
     * Get metadata of an item.
     *
     * @param  string $normalizedKey
     * @return array|bool Metadata on success, false on failure
     * @throws Exception\ExceptionInterface
     * @triggers getMetadata.pre(PreEvent)
     * @triggers getMetadata.post(PostEvent)
     * @triggers getMetadata.exception(ExceptionEvent)
     */
    protected function internalGetMetadata(&$normalizedKey)
    {
        if (! $this->internalHasItem($normalizedKey)) {
            return false;
        }

        $ns = $this->getOptions()->getNamespace();
        return [
            'mtime' => $this->data[$ns][$normalizedKey][1],
        ];
    }

    /* writing */

    /**
     * Internal method to store an item.
     *
     * @param  string $normalizedKey
     * @param  mixed  $value
     * @return bool
     * @throws Exception\ExceptionInterface
     */
    protected function internalSetItem(&$normalizedKey, &$value)
    {
        $options = $this->getOptions();

        if (! $this->hasAvailableSpace()) {
            $memoryLimit = $options->getMemoryLimit();
            throw new Exception\OutOfSpaceException(
                "Memory usage exceeds limit ({$memoryLimit})."
            );
        }

        $ns                              = $options->getNamespace();
        $this->data[$ns][$normalizedKey] = [$value, microtime(true)];

        return true;
    }

    /**
     * Internal method to store multiple items.
     *
     * @param  array $normalizedKeyValuePairs
     * @return array Array of not stored keys
     * @throws Exception\ExceptionInterface
     */
    protected function internalSetItems(array &$normalizedKeyValuePairs)
    {
        $options = $this->getOptions();

        if (! $this->hasAvailableSpace()) {
            $memoryLimit = $options->getMemoryLimit();
            throw new Exception\OutOfSpaceException(
                "Memory usage exceeds limit ({$memoryLimit})."
            );
        }

        $ns = $options->getNamespace();
        if (! isset($this->data[$ns])) {
            $this->data[$ns] = [];
        }

        $data = &$this->data[$ns];
        $now  = microtime(true);
        foreach ($normalizedKeyValuePairs as $normalizedKey => $value) {
            $data[$normalizedKey] = [$value, $now];
        }

        return [];
    }

    /**
     * Add an item.
     *
     * @param  string $normalizedKey
     * @param  mixed  $value
     * @return bool
     * @throws Exception\ExceptionInterface
     */
    protected function internalAddItem(&$normalizedKey, &$value)
    {
        $options = $this->getOptions();

        if (! $this->hasAvailableSpace()) {
            $memoryLimit = $options->getMemoryLimit();
            throw new Exception\OutOfSpaceException(
                "Memory usage exceeds limit ({$memoryLimit})."
            );
        }

        $ns = $options->getNamespace();
        if (isset($this->data[$ns][$normalizedKey])) {
            return false;
        }

        $this->data[$ns][$normalizedKey] = [$value, microtime(true)];
        return true;
    }

    /**
     * Internal method to add multiple items.
     *
     * @param  array $normalizedKeyValuePairs
     * @return array Array of not stored keys
     * @throws Exception\ExceptionInterface
     */
    protected function internalAddItems(array &$normalizedKeyValuePairs)
    {
        $options = $this->getOptions();

        if (! $this->hasAvailableSpace()) {
            $memoryLimit = $options->getMemoryLimit();
            throw new Exception\OutOfSpaceException(
                "Memory usage exceeds limit ({$memoryLimit})."
            );
        }

        $ns = $options->getNamespace();
        if (! isset($this->data[$ns])) {
            $this->data[$ns] = [];
        }

        $result = [];
        $data   = &$this->data[$ns];
        $now    = microtime(true);
        foreach ($normalizedKeyValuePairs as $normalizedKey => $value) {
            if (isset($data[$normalizedKey])) {
                $result[] = $normalizedKey;
            } else {
                $data[$normalizedKey] = [$value, $now];
            }
        }

        return $result;
    }

    /**
     * Internal method to replace an existing item.
     *
     * @param  string $normalizedKey
     * @param  mixed  $value
     * @return bool
     * @throws Exception\ExceptionInterface
     */
    protected function internalReplaceItem(&$normalizedKey, &$value)
    {
        $ns = $this->getOptions()->getNamespace();
        if (! isset($this->data[$ns][$normalizedKey])) {
            return false;
        }
        $this->data[$ns][$normalizedKey] = [$value, microtime(true)];

        return true;
    }

    /**
     * Internal method to replace multiple existing items.
     *
     * @param  array $normalizedKeyValuePairs
     * @return array Array of not stored keys
     * @throws Exception\ExceptionInterface
     */
    protected function internalReplaceItems(array &$normalizedKeyValuePairs)
    {
        $ns = $this->getOptions()->getNamespace();
        if (! isset($this->data[$ns])) {
            return array_keys($normalizedKeyValuePairs);
        }

        $result = [];
        $data   = &$this->data[$ns];
        foreach ($normalizedKeyValuePairs as $normalizedKey => $value) {
            if (! isset($data[$normalizedKey])) {
                $result[] = $normalizedKey;
            } else {
                $data[$normalizedKey] = [$value, microtime(true)];
            }
        }

        return $result;
    }

    /**
     * Internal method to reset lifetime of an item
     *
     * @param  string $normalizedKey
     * @return bool
     * @throws Exception\ExceptionInterface
     */
    protected function internalTouchItem(&$normalizedKey)
    {
        $ns = $this->getOptions()->getNamespace();

        if (! isset($this->data[$ns][$normalizedKey])) {
            return false;
        }

        $this->data[$ns][$normalizedKey][1] = microtime(true);
        return true;
    }

    /**
     * Internal method to remove an item.
     *
     * @param  string $normalizedKey
     * @return bool
     * @throws Exception\ExceptionInterface
     */
    protected function internalRemoveItem(&$normalizedKey)
    {
        $ns = $this->getOptions()->getNamespace();
        if (! isset($this->data[$ns][$normalizedKey])) {
            return false;
        }

        unset($this->data[$ns][$normalizedKey]);

        // remove empty namespace
        if (! $this->data[$ns]) {
            unset($this->data[$ns]);
        }

        return true;
    }

    /**
     * Internal method to increment an item.
     *
     * @param  string $normalizedKey
     * @param  int    $value
     * @return int|bool The new value on success, false on failure
     * @throws Exception\ExceptionInterface
     */
    protected function internalIncrementItem(&$normalizedKey, &$value)
    {
        $ns = $this->getOptions()->getNamespace();
        if (isset($this->data[$ns][$normalizedKey])) {
            $data     = &$this->data[$ns][$normalizedKey];
            $data[0] += $value;
            $data[1]  = microtime(true);
            $newValue = $data[0];
        } else {
            // initial value
            $newValue                        = $value;
            $this->data[$ns][$normalizedKey] = [$newValue, microtime(true)];
        }

        return $newValue;
    }

    /**
     * Internal method to decrement an item.
     *
     * @param  string $normalizedKey
     * @param  int    $value
     * @return int|bool The new value on success, false on failure
     * @throws Exception\ExceptionInterface
     */
    protected function internalDecrementItem(&$normalizedKey, &$value)
    {
        $ns = $this->getOptions()->getNamespace();
        if (isset($this->data[$ns][$normalizedKey])) {
            $data     = &$this->data[$ns][$normalizedKey];
            $data[0] -= $value;
            $data[1]  = microtime(true);
            $newValue = $data[0];
        } else {
            // initial value
            $newValue                        = -$value;
            $this->data[$ns][$normalizedKey] = [$newValue, microtime(true)];
        }

        return $newValue;
    }

    /* status */

    /**
     * Internal method to get capabilities of this adapter
     *
     * @return Capabilities
     */
    protected function internalGetCapabilities()
    {
        if ($this->capabilities === null) {
            $this->capabilityMarker = new stdClass();
            $this->capabilities     = new Capabilities(
                $this,
                $this->capabilityMarker,
                [
                    'supportedDatatypes' => [
                        'NULL'     => true,
                        'boolean'  => true,
                        'integer'  => true,
                        'double'   => true,
                        'string'   => true,
                        'array'    => true,
                        'object'   => true,
                        'resource' => true,
                    ],
                    'supportedMetadata'  => ['mtime'],
                    'minTtl'             => 1,
                    'maxTtl'             => PHP_INT_MAX,
                    'staticTtl'          => false,
                    'ttlPrecision'       => 1,
                    'maxKeyLength'       => 0,
                    'namespaceIsPrefix'  => false,
                    'namespaceSeparator' => '',
                ]
            );
        }

        return $this->capabilities;
    }

    /* internal */

    /**
     * Has space available to store items?
     *
     * @return bool
     */
    protected function hasAvailableSpace()
    {
        $total = $this->getOptions()->getMemoryLimit();

        // check memory limit disabled
        if ($total <= 0) {
            return true;
        }

        $free = $total - (float) memory_get_usage(true);
        return $free > 0;
    }
}
