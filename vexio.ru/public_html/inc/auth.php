<?php
require_once 'config.php';
require_once 'db.php';
require_once 'openid.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function steamLogin() {
    $openid = new LightOpenID(SITE_DOMAIN);
    if (!$openid->mode) {
        $openid->identity = 'https://steamcommunity.com/openid';
        header('Location: ' . $openid->authUrl());
        exit;
    } elseif ($openid->mode == 'id_res') {
        if ($openid->validate()) {
            $steam_id = basename($openid->identity);
            global $pdo;
            $stmt = $pdo->prepare("SELECT id FROM users WHERE steam_id = ?");
            $stmt->execute([$steam_id]);
            $user_id = $stmt->fetchColumn();

            if (!$user_id) {
                $stmt = $pdo->prepare("INSERT INTO users (username, steam_id) VALUES (?, ?)");
                $stmt->execute(['SteamUser_' . substr($steam_id, -6), $steam_id]);
                $user_id = $pdo->lastInsertId();
                addWelcomeBonus($user_id);
            }

            $_SESSION['user_id'] = $user_id;
            header('Location: profile.php');
            exit;
        }
    }
    header('Location: index.php');
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>