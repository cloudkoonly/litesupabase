<?php

namespace Litesupabase\Library\Cache;
interface CacheDriver {
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, ?int $ttl = null): bool;
    public function delete(string $key): bool;
    public function has(string $key): bool;
    public function clear(): bool;
}