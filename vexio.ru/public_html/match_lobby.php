<?php
// /vexio.ru/public_html/match_lobby.php
require_once 'partials/header.php';

// Для отладки ошибок (уберите в продакшене)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$matchId = intval($_GET['match_id'] ?? 0);
if (!$matchId) {
    echo "<div class='container'><p>Не указан match_id.</p></div>";
    require_once 'partials/footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM matches WHERE id=? LIMIT 1");
$stmt->execute([$matchId]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$match) {
    echo "<div class='container'><p>Турнир не найден.</p></div>";
    require_once 'partials/footer.php';
    exit;
}

$user = isLoggedIn() ? getCurrentUser($pdo) : null;
$mp = null;
if ($user) {
    $stmtMP = $pdo->prepare("SELECT * FROM match_players WHERE match_id=? AND user_id=? LIMIT 1");
    $stmtMP->execute([$matchId, $user['id']]);
    $mp = $stmtMP->fetch(PDO::FETCH_ASSOC);
}

$msg = '';

// Определяем максимальное число участников по режиму
if ($match['mode'] === '1v1') $maxPlayers = 2;
elseif ($match['mode'] === '2v2') $maxPlayers = 4;
elseif ($match['mode'] === '5v5') $maxPlayers = 10;
else $maxPlayers = isset($match['participants_limit']) ? intval($match['participants_limit']) : 100;

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM match_players WHERE match_id=?");
$stmtCount->execute([$matchId]);
$currentCount = (int)$stmtCount->fetchColumn();

// Если статус 'Created' и участников >= max, переводим турнир в 'Live'
if ($match['status'] === 'Created' && $currentCount >= $maxPlayers) {
    $pdo->prepare("UPDATE matches SET status='Live' WHERE id=?")->execute([$matchId]);
    $stmt->execute([$matchId]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Если турнир, показываем таймер обратного отсчёта до начала
$showTimer = false;
if ($match['tournament_type'] === 'tournament' && !empty($match['start_time'])) {
    $showTimer = true;
}

// Обработка POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Присоединение к лобби, если пользователь не зарегистрирован
    if ($action === 'join_lobby' && !$mp && $match['status'] === 'Created' && $currentCount < $maxPlayers) {
        $entryFee = intval($match['entry_fee']);
        if ($user['tickets'] < $entryFee) {
            $msg = "Недостаточно билетов для вступления (требуется: $entryFee).";
        } else {
            $pdo->prepare("INSERT INTO match_players (match_id, user_id, team, entry_fee) VALUES (?,?,1,?)")
                ->execute([$matchId, $user['id'], $entryFee]);
            $pdo->prepare("UPDATE users SET tickets = tickets - ? WHERE id=?")
                ->execute([$entryFee, $user['id']]);
            $stmtMP->execute([$matchId, $user['id']]);
            $mp = $stmtMP->fetch(PDO::FETCH_ASSOC);
            $msg = "Вы успешно присоединились к турниру.";
            $stmtCount->execute([$matchId]);
            $currentCount = (int)$stmtCount->fetchColumn();
        }
    }
    // Смена команды
    elseif ($action === 'switch_team' && $mp && $match['status'] === 'Created') {
        $newTeam = ($mp['team'] == 1) ? 2 : 1;
        $pdo->prepare("UPDATE match_players SET team=? WHERE id=?")
            ->execute([$newTeam, $mp['id']]);
        $msg = "Вы переключились в команду #$newTeam";
        $stmtMP->execute([$matchId, $user['id']]);
        $mp = $stmtMP->fetch(PDO::FETCH_ASSOC);
    }
    // Выход из лобби
    elseif ($action === 'leave' && $mp && $match['status'] === 'Created') {
        $entryFee = intval($mp['entry_fee']);
        $pdo->prepare("UPDATE users SET tickets = tickets + ? WHERE id=?")
            ->execute([$entryFee, $user['id']]);
        $pdo->prepare("DELETE FROM match_players WHERE id=?")->execute([$mp['id']]);
        $msg = "Вы покинули лобби, билеты возвращены.";
        $stmtCount->execute([$matchId]);
        $cntAfter = (int)$stmtCount->fetchColumn();
        if ($cntAfter <= 0) {
            $pdo->prepare("DELETE FROM matches WHERE id=?")->execute([$matchId]);
            $msg .= " Турнир удалён, так как никого не осталось.";
            header("Location: tournaments.php?msg=" . urlencode($msg));
            exit;
        }
        $stmtMP->execute([$matchId, $user['id']]);
        $mp = $stmtMP->fetch(PDO::FETCH_ASSOC);
    }
    // Отправка сообщения в чат
    elseif ($action === 'chat' && $mp) {
        $text = trim($_POST['message'] ?? '');
        $text = strip_tags($text);
        if ($text !== '') {
            $pdo->prepare("INSERT INTO match_chat (match_id, user_id, message) VALUES (?,?,?)")
                ->execute([$matchId, $user['id'], $text]);
        }
    }
}

// Загружаем чат
$stmtChat = $pdo->prepare("
    SELECT mc.*, u.personaname
      FROM match_chat mc
      JOIN users u ON u.id = mc.user_id
     WHERE mc.match_id = ?
     ORDER BY mc.id ASC
");
$stmtChat->execute([$matchId]);
$chatRows = $stmtChat->fetchAll(PDO::FETCH_ASSOC);

// Загружаем участников турнира
$stmtPL = $pdo->prepare("
    SELECT mp.*, u.personaname, u.avatar
      FROM match_players mp
      JOIN users u ON u.id = mp.user_id
     WHERE mp.match_id = ?
     ORDER BY mp.id ASC
");
$stmtPL->execute([$matchId]);
$allPlayers = $stmtPL->fetchAll(PDO::FETCH_ASSOC);

$team1 = [];
$team2 = [];
$totalFee1 = 0;
$totalFee2 = 0;
foreach ($allPlayers as $p) {
    if ($p['team'] == 2) {
        $team2[] = $p;
        $totalFee2 += intval($p['entry_fee']);
    } else {
        $team1[] = $p;
        $totalFee1 += intval($p['entry_fee']);
    }
}

$stmt->execute([$matchId]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<div class="container" style="background:#1c1c24; padding:20px; border-radius:5px;">
    <?php if ($msg): ?>
        <div class="message" style="margin-bottom:10px;"><?= htmlspecialchars($msg, ENT_QUOTES) ?></div>
    <?php endif; ?>

    <h2>Лобби турнира #<?= $match['id'] ?></h2>
    <p>Карта: <?= htmlspecialchars($match['map_name'], ENT_QUOTES) ?> |
       Регион: <?= htmlspecialchars($match['region'], ENT_QUOTES) ?> |
       Режим: <?= htmlspecialchars($match['mode'], ENT_QUOTES) ?></p>
    <p>Статус: <?= htmlspecialchars($match['status'], ENT_QUOTES) ?></p>
    <p>Участников: <?= count($allPlayers) ?>/<?= $maxPlayers ?></p>

    <!-- Если пользователь не в лобби, показываем кнопку "Присоединиться" -->
    <?php if ($user && !$mp && $match['status'] === 'Created' && $currentCount < $maxPlayers): ?>
        <form method="post" style="margin-top:10px;">
            <input type="hidden" name="action" value="join_lobby">
            <button type="submit" class="play-button">Присоединиться к турниру</button>
        </form>
    <?php endif; ?>

    <?php if ($showTimer): ?>
        <div id="countdown" data-start-time="<?= htmlspecialchars($match['start_time'], ENT_QUOTES) ?>"></div>
        <script>
            function updateCountdown() {
                const elem = document.getElementById('countdown');
                if (!elem) return;
                const startTime = new Date(elem.getAttribute('data-start-time')).getTime();
                const now = new Date().getTime();
                const diff = startTime - now;
                if (diff <= 0) {
                    elem.innerHTML = "Турнир начался!";
                } else {
                    const hours = Math.floor(diff / (1000 * 60 * 60));
                    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                    elem.innerHTML = "До начала турнира: " + hours + "ч " + minutes + "м " + seconds + "с";
                }
            }
            setInterval(updateCountdown, 1000);
            updateCountdown();
        </script>
    <?php endif; ?>

    <div style="display:flex; gap:20px; margin-top:20px;">
        <div style="flex:1;">
            <h3>Команда 1 (сумма: <?= $totalFee1 ?> билетов)</h3>
            <?php foreach ($team1 as $pl): ?>
                <div style="margin-bottom:5px;">
                    <img src="<?= htmlspecialchars($pl['avatar']) ?>" style="width:24px; height:24px; vertical-align:middle; border-radius:3px;">
                    <?= htmlspecialchars($pl['personaname'], ENT_QUOTES) ?> (<?= $pl['entry_fee'] ?>)
                </div>
            <?php endforeach; ?>
        </div>
        <div style="flex:1;">
            <h3>Команда 2 (сумма: <?= $totalFee2 ?> билетов)</h3>
            <?php foreach ($team2 as $pl): ?>
                <div style="margin-bottom:5px;">
                    <img src="<?= htmlspecialchars($pl['avatar']) ?>" style="width:24px; height:24px; vertical-align:middle; border-radius:3px;">
                    <?= htmlspecialchars($pl['personaname'], ENT_QUOTES) ?> (<?= $pl['entry_fee'] ?>)
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($match['status'] === 'Created' && $mp): ?>
        <form method="post" style="margin-top:10px;">
            <input type="hidden" name="action" value="switch_team">
            <button type="submit" class="play-button">Сменить команду (1 ⇆ 2)</button>
        </form>

        <form method="post" style="margin-top:10px;">
            <input type="hidden" name="action" value="leave">
            <button type="submit" class="play-button" style="background:#666;">Покинуть лобби (вернуть билеты)</button>
        </form>
    <?php endif; ?>

    <?php if ($mp): ?>
        <div style="margin-top:15px;">
            <a href="steam://connect/46.174.52.43:27015" class="play-button" style="background:#2962FF;">Подключиться к серверу</a>
        </div>
    <?php endif; ?>

    <div style="margin-top:20px; background:#2c2c34; padding:10px;">
        <h3>Чат участников</h3>
        <div style="max-height:200px; overflow-y:auto; border:1px solid #444; padding:5px;">
            <?php if (!$chatRows): ?>
                <p style="color:#777;">Сообщений нет</p>
            <?php else: ?>
                <?php foreach ($chatRows as $row): ?>
                    <div style="margin-bottom:5px;">
                        <strong><?= htmlspecialchars($row['personaname'], ENT_QUOTES) ?>:</strong>
                        <?= htmlspecialchars($row['message'], ENT_QUOTES) ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php if ($mp): ?>
            <form method="post" style="margin-top:10px;">
                <input type="hidden" name="action" value="chat">
                <input type="text" name="message" required placeholder="Напишите сообщение..." style="width:80%;">
                <button type="submit" class="play-button">Отправить</button>
            </form>
        <?php else: ?>
            <p>Только участники могут писать в чат.</p>
        <?php endif; ?>
    </div>
</div>
<?php
ob_end_flush();
require_once 'partials/footer.php';
?>
