<?php

use function Adminer\encrypt_string;

include_once __DIR__ . '/xxtea.inc.php';
session_name('adminer_sid');
session_start();
$redirect = '/db/adminer.php';
$vendor = 'server';
$server = 'mysql8';
$username = 'root';
$password = '123456';
$db = '';
if (isset($_GET['token'])) {
    $validToken = getValidToken();
    if ($_GET['token'] === $validToken) {
        session_regenerate_id(); // defense against session fixation
        set_password($vendor, $server, $username, $password);
        $_SESSION["db"][$vendor][$server][$username][$db] = true;
        $redirect = $redirect.'?server=mysql8&username=root';
        if (isset($_GET['db'])) $redirect = $redirect.'?server=mysql8&username=root&db='.$_GET['db'];
        if (isset($_GET['sql'])) $redirect = $redirect.'?server=mysql8&username=root&sql=';
        if (isset($_GET['dump'])) $redirect = $redirect.'?server=mysql8&username=root&dump=';
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