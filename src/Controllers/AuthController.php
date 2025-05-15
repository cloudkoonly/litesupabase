<?php

namespace Litesupabase\Controllers;

use Curl\Curl;
use Exception;
use Litesupabase\Library\GoogleAuth;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Litesupabase\Base;
use Litesupabase\Database\UserRepository;
use Firebase\JWT\JWT;

class AuthController extends Base
{
    private UserRepository $userRepository;

    public function signup(Request $request, Response $response): Response
    {
        $json = $request->getBody();
        $data = json_decode($json, true);
        $this->logger->info("Hello, Data:".json_encode($data));

        // Validate input
        if (empty($data['email']) || empty($data['password'])) {
            return $this->respondWithJson($response, 400, "Email and password are required");
        }

        try {
            // Check if user exists
            $this->userRepository = new UserRepository($this->db);
            if ($this->userRepository->findByEmail($data['email'])) {
                return $this->respondWithJson($response, 400, "Email already exists");
            }

            // Create user with default role
            $userData = [
                'name' => $data['name'] ?? '',
                'email' => $data['email'],
                'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            ];

            $user = $this->userRepository->create($userData);
            $this->logger->info("Created User:".json_encode($user));

            $userArray = [
                'uuid' => $user['uuid'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => 'user'
            ];
            $accessToken = $this->generateAccessToken($userArray);
            $refreshToken = $this->generateAndStoreRefreshToken($user['uuid']);
            return $this->respondWithJson($response, 200, "ok", ['user' => $userArray, 'access_token' => $accessToken, 'refresh_token' => $refreshToken]);
        } catch (Exception $e) {
            $this->logger->error("Failed to create user:".$e->getMessage());
            return $this->respondWithJson($response, 500, "Failed to create user");
        }
    }

    public function login(Request $request, Response $response): Response
    {
        $json = $request->getBody();
        $data = json_decode($json, true);

        // Validate input
        if (empty($data['email']) || empty($data['password'])) {
            return $this->respondWithJson($response, 400, "Email and password are required");
        }

        try {
            // Find user
            $this->userRepository = new UserRepository($this->db);
            $user = $this->userRepository->findByEmail($data['email']);
            if (!$user || !password_verify($data['password'], $user['password'])) {
                return $this->respondWithJson($response, 401, "Invalid credentials");
            }
            $userArray = [
                'uuid' => $user['uuid'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => 'user'
            ];
            $accessToken = $this->generateAccessToken($userArray);
            $refreshToken = $this->generateAndStoreRefreshToken($user['uuid']);
            // Update last sign-in
            $sql = 'UPDATE users SET last_sign_in_at = NOW() WHERE uuid = :uuid';
            $this->db->setData([':uuid'=>$user['uuid']]);
            $this->db->query($sql);
            return $this->respondWithJson($response, 200, "ok", ['user' => $userArray, 'access_token' => $accessToken, 'refresh_token' => $refreshToken]);
        } catch (Exception $e) {
            return $this->respondWithJson($response, 401, "Login failed");
        }
    }

    public function logout(Request $request, Response $response): Response
    {
        // In a token-based system, the client just needs to remove the token
        $response->getBody()->write(json_encode(['message' => 'Logged out successfully']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function config(Request $request, Response $response): Response
    {
        $config = [
            'googleClientId' => $this->settings['GOOGLE_CLIENT_ID'],
            'githubClientId' => $this->settings['GITHUB_CLIENT_ID'],
        ];
        return $this->respondWithJson($response, 200, "ok", $config);
    }

    public function forgotPassword(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? '';

        if (empty($email)) {
            return $this->respondWithJson($response, 400, "Email is required");
        }

        try {
            // Check if user exists
            $this->userRepository = new UserRepository($this->db);
            $user = $this->userRepository->findByEmail($email);
            if (!$user) {
                // For security reasons, we still return success even if the email doesn't exist
                return $this->respondWithJson($response, 200, "If your email exists in our system, you will receive password reset instructions.");
            }

            // Generate reset token
            $resetToken = bin2hex(random_bytes(32));
            $resetTokenExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store reset token in database
            $this->userRepository->createResetToken($resetToken, $resetTokenExpiry, $user['uuid']);

            // Generate reset link
            $resetLink = $_ENV['APP_URL'] . '/reset-password?email='.$user['email'].'&token=' . $resetToken;

            // TODO: Send email with reset link
            // For now, we'll just return the reset link in the response
            // In production, you should integrate with an email service

            return $this->respondWithJson($response, 200, "If your email exists in our system, you will receive password reset instructions.");

        } catch (Exception $e) {
            $this->logger->error("Reset password error:".$e->getMessage());
            return $this->respondWithJson($response, 500, "An error occurred while reset password");
        }
    }

    public function getUser(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $userArray = [
            'id' => $user->user->uuid,
            'name' => $user->user->name,
            'email' => $user->user->email,
            'role' => $user->user->role
        ];

        $response->getBody()->write(json_encode($userArray));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @throws Exception
     */
    public function googleCallback(Request $request, Response $response): Response
    {
        $clientID = $_ENV['googleClientID']??'';
        $clientSecret =$_ENV['googleClientSecret']??'';
        $redirectUri = $_ENV['googleRedirectUri']??'';
        $vars = $request->getQueryParams();
        $code = $vars['code']??'';
        if (empty($code)) {
            $this->logger->warning("Google auth error");
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
        $googleAuth = new GoogleAuth($clientID,$clientSecret,$redirectUri);
        list($providerId, $email, $name, $gender, $picture) = $googleAuth->getGoogleAccountInfo($code);
        if ($email) {
            $this->logger->info("google auth ok, email:".$email.",name:".$name.",gender:".$gender.",picture:".$picture);
            $meta = ['gender'=>$gender, 'picture'=>$picture];
            return $this->ReturnTokenUseEmailByLoginWithThird('google', $providerId, $email, $name, $meta, $response);
        } else {
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * @throws Exception
     */
    public function githubCallback(Request $request, Response $response): Response
    {
        $clientID = $_ENV['githubClientID']??'';
        $clientSecret = $_ENV['githubClientSecret']??'';
        $redirectUri = $_ENV['githubRedirectUri']??'';
        $vars = $request->getQueryParams();
        $code = $vars['code']??'';
        if (empty($code)) {
            $this->logger->warning("Github auth error");
            return $response->withHeader('Location', '/index.html?e=Github auth error')->withStatus(302);
        }
        $curl = new Curl();
        $curl->setTimeout(3);
        $url = "https://github.com/login/oauth/access_token";
        $body = [
            'client_id' => $clientID,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ];
        $headers = [
            'Accept' => 'application/json'
        ];
        $curl->setHeaders($headers);
        $curl->post($url, $body);
        $result = $curl->getRawResponse();
        $this->logger->info("Request github token api url:" . $url . ",headers:" . json_encode($headers) . ",body:" . json_encode($body) . ", Get subscriptionConfirm result serialization:" . $result . ", HTTP code:".$curl->getHttpStatusCode());
        $data = json_decode($result, true);
        if (isset($data['access_token'])) {
            $access_token = $data['access_token'];
            $user_url = "https://api.github.com/user";
            $headers = [
                'Authorization: token ' . $access_token
            ];
            $curl->setHeaders($headers);
            $curl->post($user_url);
            $result2 = $curl->getRawResponse();
            $this->logger->info("Request github user api url:" . $url . ",headers:" . json_encode($headers) . ",body:" . json_encode($body) . ", Get subscriptionConfirm result serialization:" . $result2 . ", HTTP code:".$curl->getHttpStatusCode());
            $userData = json_decode($result2, true);

            $providerId = $userData['id']??0;
            $email = $userData['email']?:$userData['login'];
            $name = $userData['name']??'';
            $gender = $userData['gender']??'';
            $this->logger->info("github auth ok, email:".$email.",name:".$name.",gender:".$gender);
            if ($email) {
                return $this->ReturnTokenUseEmailByLoginWithThird('github', $providerId, $email, $name, $result2, $response);
            }
        }
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    /**
     * Generate and store refresh token
     * @param string $uuid
     * @return string Raw refresh token
     * @throws Exception
     */
    private function generateAndStoreRefreshToken(string $uuid): string {

        // Generate cryptographically secure refresh token
        $rawToken = bin2hex(random_bytes(32)); // 64-character random string
        $tokenHash = hash('sha256', $rawToken); // Hash for storage
        $refreshTokenExpireDay = $this->settings['REFRESH_TOKEN_EXPIRE_DAY'];
        $expiresAt = date('Y-m-d H:i:s', time() + $refreshTokenExpireDay*86400);

        // Store in database
        $this->userRepository = new UserRepository($this->db);
        $this->userRepository->storeRefreshToken($uuid, $tokenHash, $expiresAt);

        return $rawToken;
    }

    private function generateAccessToken(array $user): string
    {
        $issuedAt = time();
        $expire = $issuedAt + $this->settings['ACCESS_TOKEN_EXPIRE'];

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'sub' => $user['uuid'],
            'user' => $user,
            'type' => 'access'
        ];
        $accessSecretKey = $this->settings['JWT_ACCESS_SECRET'];
        return JWT::encode($payload, $accessSecretKey, $this->settings['ALGORITHM']);
    }

    /**
     * @throws Exception
     */
    public function generateSecurePassword($length = 16): string
    {
        $bytes = random_bytes($length);
        return substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $length);
    }

    /**
     * @param string $type
     * @param mixed $providerId
     * @param mixed $email
     * @param mixed $name
     * @param array $meta
     * @param mixed $response
     * @return Response
     * @throws Exception
     */
    public function ReturnTokenUseEmailByLoginWithThird(string $type, mixed $providerId, string $email, string $name, array $meta, Response $response): Response
    {
        $sql = "select * from users where email=:email";
        $this->db->setData([":email" => $email]);
        $userResult = $this->db->query($sql)->row;
        if ($userResult) {
            $this->logger->info("Third auth login");
        } else {
            $this->logger->info("Third auth sign up");
            // Create user with default role
            $userData = [
                'name' => $name,
                'email' => $email,
                'provider' => $type,
                'provider_id' => $providerId,
                'metadata' => json_encode($meta),
                'password' => password_hash($this->generateSecurePassword(), PASSWORD_DEFAULT),
            ];
            $userResult = $this->userRepository->create($userData);
            $this->logger->info("Login with google and created User:" . json_encode($userResult));
        }
        $userArray = [
            'uuid' => $userResult['uuid'],
            'name' => $userResult['name'],
            'email' => $userResult['email'],
            'role' => 'user'
        ];
        $accessToken = $this->generateAccessToken($userArray);
        $refreshToken = $this->generateAndStoreRefreshToken($userResult['uuid']);
        return $this->respondWithJson($response, 200, "ok", ['user' => $userArray, 'access_token' => $accessToken, 'refresh_token' => $refreshToken]);
    }

    public function refresh(Request $request, Response $response): Response
    {
        try {
            $json = $request->getBody();
            $vars = json_decode($json, true);

            $refreshToken = $vars['refresh_token'] ?? '';
            if (empty($refreshToken)) {
                return $this->respondWithJson($response, 400, "Refresh token required");
            }

            // Hash the provided refresh token
            $tokenHash = hash('sha256', $refreshToken);

            // Verify refresh token
            $sql = 'SELECT uuid, email, name FROM users WHERE refresh_token_hash=:refresh_token_hash AND revoked=FALSE AND is_active=TRUE';
            $this->db->setData([
                ':refresh_token_hash' => $tokenHash
            ]);
            $tokenData = $this->db->query($sql)->row;
            if (!$tokenData) {
                return $this->respondWithJson($response, 400, "Invalid or expired refresh token");
            }
            // Generate new access token
            $newAccessToken = $this->generateAccessToken($tokenData);
            // Optionally rotate refresh token (enhances security)
            $newRefreshToken = $this->generateAndStoreRefreshToken($tokenData['uuid']);
            return $this->respondWithJson($response, 200, "ok", ['user' => $tokenData, 'access_token' => $newAccessToken, 'refresh_token' => $newRefreshToken]);
        } catch (Exception $e) {
            $this->logger->error('Refresh Token Error: ' . $e->getMessage());
            return $this->respondWithJson($response, 500, "Error");
        }
    }
}
