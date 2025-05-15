<?php
namespace Litesupabase\Library\Cache;
class FileCacheDriver implements CacheDriver {
    private $cachePath;

    public function __construct(array $config) {
        $this->cachePath = rtrim($config['path'], '/') . '/';
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    public function get(string $key): mixed {
        $file = $this->getFilePath($key);
        if (!file_exists($file)) {
            return null;
        }

        $data = unserialize(file_get_contents($file));
        if ($data['expires_at'] !== null && $data['expires_at'] < time()) {
            $this->delete($key);
            return null;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool {
        $file = $this->getFilePath($key);
        $data = [
            'value' => $value,
            'expires_at' => $ttl ? time() + $ttl : null,
        ];

        return file_put_contents($file, serialize($data), LOCK_EX) !== false;
    }

    public function delete(string $key): bool {
        $file = $this->getFilePath($key);
        return file_exists($file) && unlink($file);
    }

    public function has(string $key): bool {
        return $this->get($key) !== null;
    }

    public function clear(): bool {
        $files = glob($this->cachePath . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }

    private function getFilePath(string $key): string {
        return $this->cachePath . md5($key) . '.cache';
    }
}