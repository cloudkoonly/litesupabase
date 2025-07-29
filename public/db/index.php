<?php

use Dotenv\Dotenv;
use function Adminer\encrypt_string;

$root = __DIR__.'/../../';
require $root . 'vendor/autoload.php';
if (!file_exists($root. '.env')) {
    echo ".env file not found";exit;
}
$dotEnv = Dotenv::createImmutable($root);
$dotEnv->load();

include_once __DIR__ . '/xxtea.inc.php';
session_name('adminer_sid');
session_start();
$index = '/db/adminer.php';
$vendor = 'server';
$server = $_ENV['DB_HOST']??'mysql';
$username = $_ENV['DB_USER']??'';
$password = $_ENV['DB_PASS']??'';
$db = $_ENV['DB_NAME']??'';
if (isset($_GET['token'])) {
    $validToken = getValidToken();
    if ($_GET['token'] === $validToken) {
        session_regenerate_id(); // defense against session fixation
        set_password($vendor, $server, $username, $password);
        $_SESSION["db"][$vendor][$server][$username][$db] = true;
        $redirect = $index.'?server='.$server.'&username='.$username;
        if (isset($_GET['db'])) $redirect = $index.'?server='.$server.'&username='.$username.'&db='.$_GET['db'];
        if (isset($_GET['sql'])) $redirect = $index.'?server='.$server.'&username='.$username.'&sql=';
        if (isset($_GET['dump'])) $redirect = $index.'?server='.$server.'&username='.$username.'&dump=';
    } else {
        logout($vendor,$server,$username);
    }
} else {
    logout($vendor,$server,$username);
}
header("Location: $redirect");
function set_password(string $vendor, ?string $server, string $username, ?string $password): void {
    $_SESSION["pwds"][$vendor][$server][$username] = ($_COOKIE["adminer_key"] && is_string($password)
        ? array(encrypt_string($password, $_COOKIE["adminer_key"]))
        : $password
    );
}
function getValidToken(): string
{
    $tokenFile = __DIR__.'/../../var/cache/sso_token.json';
    if (!file_exists($tokenFile)) return '';
    $tokenData = json_decode(file_get_contents($tokenFile), true);
    $expire = $tokenData['expire']??0;
    if ($expire-time()<=0) return '';
    return $tokenData['token']??'';
}
function logout($vendor,$server,$username): void
{
    foreach (array("pwds", "db", "dbs", "queries") as $key) {
        $_SESSION[$key][$vendor][$server][$username]=null;
    }
}