<?php

namespace Litesupabase\Database;

use Exception;
use PDO;
use Litesupabase\Library\DB;

class UserRepository
{

    private DB $db;
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * @throws Exception
     */
    public function create(array $data): array
    {
        $uuid = bin2hex(random_bytes(16));
        $sql = "INSERT INTO users (uuid, name, email, password, created_at, updated_at, provider, provider_id, metadata) 
                VALUES (:uuid, :name, :email, :password, NOW(), NOW(), :provider, :provider_id, :metadata)";
        $this->db->setData([
            ':uuid' => $uuid,
            ':name' => $data['name'],
            ':email' => $data['email'],
            ':password' => $data['password'],
            ':provider' => $data['provider']??'email',
            ':provider_id' => $data['provider_id']??'',
            ':metadata' => json_encode($data['metadata']??[])
        ]);
        if ($this->db->query($sql)) {
            return [
                'uuid' => $uuid,
                'name' => $data['name'],
                'email' => $data['email']
            ];
        }
        return [];
    }

    public function findById(string $uuid): ?array
    {
        $sql = "SELECT id, name, email, created_at, updated_at FROM users WHERE uuid = :uuid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['uuid' => $uuid]);
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    /**
     * @throws Exception
     */
    public function findByEmail(string $email): ?array
    {
        $sql = "SELECT * FROM users WHERE email = :email";
        $this->db->setData([':email' => $email]);
        return $this->db->query($sql)->row ?: null;
    }

    public function update(int $id, array $data): array
    {
        $fields = [];
        $params = ['id' => $id];

        foreach ($data as $key => $value) {
            if (in_array($key, ['name', 'email', 'password'])) {
                $fields[] = "$key = :$key";
                $params[$key] = $value;
            }
        }

        if (empty($fields)) {
            return $this->findById($id);
        }

        $fields[] = "updated_at = NOW()";
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM users WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * @throws Exception
     */
    public function createResetToken(string $resetToken, string $resetTokenExpiry, string $uuid): mixed
    {
        $sql = "UPDATE users SET password_reset_token=:password_reset_token,
                                 password_reset_expires_at=:password_reset_expires_at WHERE uuid=:uuid";
        $this->db->setData([
            ':password_reset_token' => $resetToken,
            ':password_reset_expires_at' => $resetTokenExpiry,
            ':uuid' => $uuid
        ]);
        return $this->db->query($sql);
    }

    /**
     * @throws Exception
     */
    public function storeRefreshToken(string $uuid, string $tokenHash, string $expiresAt): void
    {
        $sql = 'UPDATE users set refresh_token_hash=:refresh_token_hash,expires_at=:expires_at where uuid=:uuid';
        $this->db->setData([
            ':uuid' => $uuid,
            ':refresh_token_hash' => $tokenHash,
            ':expires_at' => $expiresAt
        ]);
        $this->db->query($sql);
    }
}
