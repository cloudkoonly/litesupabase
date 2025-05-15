<?php

namespace Litesupabase\Library;

use InvalidArgumentException;
use Litesupabase\Library\Cache\FileCacheDriver;
use Litesupabase\Library\Cache\RedisCacheDriver;

class Cache {
    private $driver;

    public function __construct(array $config) {
        $driverName = $config['driver'] ?? 'file';

        switch ($driverName) {
            case 'file':
                $this->driver = new FileCacheDriver($config['file'] ?? []);
                break;
            case 'redis':
                $this->driver = new RedisCacheDriver($config['redis'] ?? []);
                break;
            default:
                throw new InvalidArgumentException("Unsupported cache driver: $driverName");
        }
    }

    public function get(string $key, mixed $default = null): mixed {
        $value = $this->driver->get($key);
        return $value !== null ? $value : $default;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool {
        return $this->driver->set($key, $value, $ttl);
    }

    public function delete(string $key): bool {
        return $this->driver->delete($key);
    }

    public function has(string $key): bool {
        return $this->driver->has($key);
    }

    public function clear(): bool {
        return $this->driver->clear();
    }

    // Get or set the cache (callback executed if it does not exist)
    public function remember(string $key, int $ttl, callable $callback): mixed {
        if ($ttl<=0) {
            return $callback();
        }
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }
}