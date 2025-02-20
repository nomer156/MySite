<?php
// /vexio.ru/public_html/auth.php
require_once 'partials/header.php';
require_once __DIR__ . '/inc/openid.php';

$action = $_GET['action'] ?? 'login';

if ($action === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

try {
    $openid = new LightOpenID('vexio.ru');
    if ($action === 'login') {
        if (!$openid->mode) {
            $openid->identity = 'https://steamcommunity.com/openid';
            $openid->returnUrl = 'https://vexio.ru/auth.php?action=callback';
            header('Location: ' . $openid->authUrl());
            exit;
        }
    } elseif ($action === 'callback') {
        if ($openid->validate()) {
            $steamId = str_replace('https://steamcommunity.com/openid/id/', '', $openid->identity);
            $profile = getSteamProfile($steamId);
            $personaname = $profile['personaname'] ?? 'Unknown';
            $avatar = $profile['avatarfull'] ?? '';
            $stmt = $pdo->prepare("SELECT id FROM users WHERE steam_id=? LIMIT 1");
            $stmt->execute([$steamId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $userId = $row['id'];
                $pdo->prepare("UPDATE users SET personaname=?, avatar=? WHERE id=?")
                    ->execute([$personaname, $avatar, $userId]);
            } else {
                $pdo->prepare("INSERT INTO users (steam_id, personaname, avatar, tickets) VALUES (?,?,?,?)")
                    ->execute([$steamId, $personaname, $avatar, 100]);
                $userId = $pdo->lastInsertId();
            }
            $_SESSION['user_id'] = $userId;
            header("Location: index.php");
            exit;
        } else {
            echo "<div class='container'><p>Ошибка авторизации Steam</p></div>";
        }
    }
} catch (Exception $ex) {
    echo "<div class='container'><p>OpenID Error: " . $ex->getMessage() . "</p></div>";
}
require_once 'partials/footer.php';
?>
