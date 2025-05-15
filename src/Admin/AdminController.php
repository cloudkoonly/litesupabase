<?php

namespace Litesupabase\Admin;

use Exception;
use FilesystemIterator;
use Litesupabase\Base;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

class AdminController extends Base
{

    /**
     * @throws Throwable
     */
    public function login(Request $request, Response $response): Response
    {
        if ($this->isLogged()) {
            return $response->withHeader('Location', '/admin/dashboard')->withStatus(302);
        }
        $data = $this->initData();
        $data['csrf_token'] = $this->generateToken();
        $this->session['csrf_token'] = $data['csrf_token'];
        $html = $this->phpRenderer->fetch('admin/login.phtml', ['data' => $data]);
        return $this->respondWithHtml($response, $html);
    }

    /**
     * @throws Throwable
     */
    public function logout(Request $request, Response $response): Response
    {
        if ($this->isLogged()) {
            $this->session->clear();
            $this->rmSsoToken();
        }
        return $response->withHeader('Location', '/admin/login')->withStatus(302);
    }

    /**
     * @throws Throwable
     */
    public function submitLogin(Request $request, Response $response): Response
    {
        $json = $request->getBody();
        $data = json_decode($json, true);

        // Validate input
        if (empty($data['email']) || empty($data['password'])) {
            return $this->respondWithJson($response, 400, "Email and password are required");
        }

        try {
            // Find user
            $adminRepository = new AdminRepository($this->db);
            $admin = $adminRepository->findByEmail($data['email']);
            //password_hash($data['password'], PASSWORD_DEFAULT);
            if (!$admin || !password_verify($data['password'], $admin['password'])) {
                return $this->respondWithJson($response, 401, "Invalid credentials");
            }
            $this->session['email'] = $admin['email'];
            $this->session['sso_token'] = $this->generateToken();
            $this->writeSsoToken();
            $adminArray = [
                'name' => $admin['name'],
                'email' => $admin['email'],
                'role' => 'admin'
            ];
            // Update last sign-in
            $sql = 'UPDATE admin SET last_sign_in_at = NOW() WHERE email = :email';
            $this->db->setData([':email'=>$adminArray['email']]);
            $this->db->query($sql);
            return $this->respondWithJson($response, 200, "ok", ['user' => $adminArray]);
        } catch (Exception $e) {
            return $this->respondWithJson($response, 401, "Login failed");
        }
    }

    public function dashboard(Request $request, Response $response):Response
    {
        $adminRepository = new AdminRepository($this->db);
        $activeUsers = $adminRepository->getActiveUsers();
        $storageDir = $this->settings['root'].'public/storage/data';
        $storageSize = $this->getDirSize($storageDir);
        $tableSize = $adminRepository->getTableSize($this->settings['DB_NAME']);

        $data = $this->initAdminData($request);
        $data['main'] = $this->phpRenderer->fetch('admin/dashboard.phtml',['activeUsers'=>$activeUsers, 'storageSize'=>$storageSize, 'tableSize'=>$tableSize]);
        $html = $this->phpRenderer->fetch('admin/main.phtml', ['data' => $data]);
        return $this->respondWithHtml($response, $html);
    }

    public function auth(Request $request, Response $response):Response
    {
        $data = $this->initAdminData($request);
        $data['main'] = $this->phpRenderer->fetch('admin/auth.phtml');
        $html = $this->phpRenderer->fetch('admin/main.phtml', ['data' => $data]);
        return $this->respondWithHtml($response, $html);
    }

    public function users(Request $request, Response $response):Response
    {
        $vars = $request->getQueryParams();
        $search = $vars['search']??'';
        $sortBy = $vars['sort_by']??'id';
        $sortOrder = $vars['sort_order']??'ASC';
        $page = $vars['page']??1;
        $perPage = $vars['per_page']??10;
        $data['stats'] = $this->getStats();
        $data['total'] = $this->getTotalRecords($search);
        $data['users'] = $this->getUserList($search, $sortBy, $sortOrder, $page, $perPage);
        return $this->respondWithJson($response, 200, 'ok', $data);
    }

    public function getUser(Request $request, Response $response):Response
    {
        $id = $request->getAttribute('id');
        return $this->respondWithJson($response, 200, 'ok', $this->getUserById($id));
    }

    public function updateUser(Request $request, Response $response):Response
    {
        $id = $request->getAttribute('id');
        $json = $request->getBody();
        $data = json_decode($json, true);
        if ($this->updateUserById($id, $data)) {
            return $this->respondWithJson($response);
        }
        return $this->respondWithJson($response, 500, 'error');
    }

    public function deleteUser(Request $request, Response $response):Response
    {
        $id = $request->getAttribute('id');
        if ($this->deleteUserById($id)) {
            return $this->respondWithJson($response);
        }
        return $this->respondWithJson($response, 500, 'error');
    }

    public function db(Request $request, Response $response):Response
    {
        try {
            $data = $this->initAdminData($request);
            $db['driver'] = $this->settings['DB_CONNECTION']??'';
            $db['host'] = $this->settings['DB_HOST']??'';
            $db['port'] = $this->settings['DB_PORT']??'';
            $db['user'] = $this->settings['DB_USER']??'';

            $databases = $this->db->query('SHOW DATABASES')->rows;
            $db['databases'] = [];
            foreach ($databases as $database) {
                if (!in_array($database['Database'], ['information_schema', 'mysql', 'performance_schema', 'sys'])) {
                    $db['databases'][] = $database['Database'];
                }
            }
            $data['main'] = $this->phpRenderer->fetch('admin/db.phtml',['ssoToken'=>$this->session['sso_token']??'', 'db'=>$db]);
            $html = $this->phpRenderer->fetch('admin/main.phtml', ['data' => $data]);
            return $this->respondWithHtml($response, $html);
        } catch (Throwable $e) {
            return $this->respondWithHtml($response);
        }

    }

    public function storage(Request $request, Response $response):Response
    {
        $data = $this->initAdminData($request);
        $data['main'] = $this->phpRenderer->fetch('admin/storage.phtml',['ssoToken'=>$this->session['sso_token']??'']);
        $html = $this->phpRenderer->fetch('admin/main.phtml', ['data' => $data]);
        return $this->respondWithHtml($response, $html);
    }

    public function storageInfo(Request $request, Response $response):Response
    {
        try {
            $data = $this->cache->remember('storage_info', 300, function () {
                $storagePath = $this->settings['root'].'public/storage/data';
                
                if (!is_dir($storagePath)) {
                    throw new \Exception('Storage directory not found');
                }

                $fileTypes = [];
                $totalSize = 0;
                $totalFiles = 0;

                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($storagePath, \FilesystemIterator::SKIP_DOTS)
                );

                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $totalFiles++;
                        $size = $file->getSize();
                        $totalSize += $size;
                        
                        $extension = strtolower($file->getExtension());
                        if (empty($extension)) {
                            $extension = 'no extension';
                        }

                        if (!isset($fileTypes[$extension])) {
                            $fileTypes[$extension] = [
                                'extension' => $extension,
                                'count' => 0,
                                'size' => 0
                            ];
                        }
                        $fileTypes[$extension]['count']++;
                        $fileTypes[$extension]['size'] += $size;
                    }
                }

                $initQuota = $this->settings['storageQuota']??10;
                $quota = $initQuota * 1024 * 1024 * 1024; // in bytes
                $usagePercent = ($totalSize / $quota) * 100;

                uasort($fileTypes, function($a, $b) {
                    return $b['count'] <=> $a['count'];
                });

                return [
                    'used_space' => $totalSize,
                    'quota' => $quota,
                    'usage_percent' => min(round($usagePercent, 1), 100),
                    'total_files' => $totalFiles,
                    'file_types' => array_values($fileTypes)
                ];
            });

            return $this->respondWithJson($response, 200, 'ok', $data);
        } catch (\Exception $e) {
            return $this->respondWithJson($response, 500, $e->getMessage());
        }
    }

    public function api(Request $request, Response $response):Response
    {
        $data = $this->initAdminData($request);
        $data['main'] = $this->phpRenderer->fetch('admin/api.phtml');
        $html = $this->phpRenderer->fetch('admin/main.phtml', ['data' => $data]);
        return $this->respondWithHtml($response, $html);
    }

    /**
     * @throws Throwable
     */
    public function initData():array
    {
        return [
            'header' => $this->getHeader(),
            'footer' => $this->getFooter(),
        ];
    }

    /**
     * @throws Throwable
     */
    public function initAdminData(Request $request):array
    {
        $uri = $request->getUri()->getPath();
        return [
            'header' => $this->getHeader(),
            'top' => $this->phpRenderer->fetch('admin/top.phtml'),
            'aside' => $this->phpRenderer->fetch('admin/aside.phtml', ['uri'=>$uri, 'email'=>$this->session['email']??'']),
            'footer' => $this->getFooter(),
        ];
    }

    /**
     * @throws Throwable
     */
    private function getHeader(): string
    {
        return $this->phpRenderer->fetch('admin/header.phtml');
    }

    /**
     * @throws Throwable
     */
    private function getFooter(): string
    {
        return $this->phpRenderer->fetch('admin/footer.phtml');
    }

    /**
     * @return string
     * @throws Exception
     */
    public function generateToken(): string
    {
        $rawToken = bin2hex(random_bytes(32)); // 64-character random string
        $token = hash('sha256', $rawToken);
        return $token;
    }

    private function writeSsoToken():void
    {
        $tokenFile = $this->settings['sso_token.file'];
        $token = $this->session['sso_token']??'';
        $expire = time() + 86400;
        file_put_contents($tokenFile, json_encode(['token'=>$token,'expire'=>$expire],JSON_PRETTY_PRINT));
    }
    private function rmSsoToken():void
    {
        $tokenFile = $this->settings['sso_token.file'];
        unlink($tokenFile);
    }

    private function getDirSize(string $dir): string
    {
        $size = 0;

        if (!is_dir($dir)) {
            return '0 B';
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        $size = rtrim(number_format($size, 2, '.', ''), '0');
        $size = rtrim($size, '.');

        return "$size {$units[$unitIndex]}";
    }

    public function getStats(): array {
        return $this->cache->remember('user_stats', 300, function () {
            $query = "
            SELECT 
                (SELECT COUNT(*) FROM users) AS total_users,
                (SELECT COUNT(*) FROM users WHERE is_active = 1) AS verified_users,
                (SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()) AS today_new_users";
            return $this->db->query($query)->row;
        });
    }

    public function getUserList(string $search = '', string $sortBy = 'id', string $sortOrder = 'ASC', int $page = 1, int $perPage = 10): array {
        $offset = ($page - 1) * $perPage;
        $sortBy = in_array($sortBy, ['id', 'name', 'email', 'created_at', 'is_active']) ? $sortBy : 'id';
        $sortOrder = strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC';

        $query = "SELECT id, name, email, is_active, created_at, provider, last_sign_in_at FROM users WHERE 1=1";
        $params = [];

        if (!empty($search)) {
            $query .= " AND (name LIKE :search OR email LIKE :search)";
            $params[':search'] = "%$search%";
        }

        $query .= " ORDER BY $sortBy $sortOrder LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($query);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getTotalRecords(string $search = ''): int {
        $cacheKey = 'total_records_' . md5($search);
        return $this->cache->remember($cacheKey, 60, function () use ($search) {
            $query = "SELECT COUNT(*) as num FROM users WHERE 1=1";
            $params = [];
            if (!empty($search)) {
                $query .= " AND (name LIKE :search OR email LIKE :search)";
                $params[':search'] = "%$search%";
                $this->db->setData($params);
            }
            return (int) $this->db->query($query)->row['num']??0;
        });
    }

    private function getUserById(int $id)
    {
        $query = "SELECT id, name, email, is_active, created_at, provider, last_sign_in_at FROM users WHERE id=:id";
        $this->db->setData([':id'=>$id]);
        return $this->db->query($query)->row;
    }

    private function updateUserById(int $id, array $data): bool
    {
        $sql = "UPDATE users set name=:name,is_active=:is_active WHERE id=:id";
        $this->db->setData([
            ':name' => $data['name']??'',
            ':is_active' => $data['is_active']??'',
            ':id' => $id
        ]);
        return $this->db->query($sql);
    }

    private function deleteUserById(mixed $id)
    {
        $sql = "UPDATE users set name=:name,is_active=:is_active WHERE id=:id";
        $this->db->setData([
            ':id' => $id
        ]);
        return $this->db->query($sql);
    }
}
