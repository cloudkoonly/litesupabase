<?php
namespace Litesupabase\Library\Cache;
use Redis;
use RedisException;
use RuntimeException;

class RedisCacheDriver implements CacheDriver {
    private $redis;

    public function __construct(array $config) {
        $this->redis = new Redis();
        try {
            $this->redis->connect(
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? 6379,
                $config['timeout'] ?? 2.0
            );
            if (isset($config['database'])) {
                $this->redis->select($config['database']);
            }
        } catch (RedisException $e) {
            throw new RuntimeException("Redis connection failed: " . $e->getMessage());
        }
    }

    public function get(string $key): mixed {
        try {
            $value = $this->redis->get($key);
            return $value === false ? null : unserialize($value);
        } catch (RedisException $e) {
            return null;
        }
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool {
        try {
            $serialized = serialize($value);
            if ($ttl) {
                return $this->redis->setEx($key, $ttl, $serialized);
            }
            return $this->redis->set($key, $serialized);
        } catch (RedisException $e) {
            return false;
        }
    }

    public function delete(string $key): bool {
        try {
            return $this->redis->del($key) > 0;
        } catch (RedisException $e) {
            return false;
        }
    }

    public function has(string $key): bool {
        try {
            return $this->redis->exists($key) > 0;
        } catch (RedisException $e) {
            return false;
        }
    }

    public function clear(): bool {
        try {
            return $this->redis->flushDB();
        } catch (RedisException $e) {
            return false;
        }
    }
}