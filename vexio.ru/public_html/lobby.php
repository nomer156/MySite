<?php
session_start();
require_once 'inc/db.php';
require_once 'inc/auth.php';
require_once 'inc/functions.php';
require_once 'inc/steam_api.php';
require_once 'inc/rcon.php';

if (!isLoggedIn() || !isset($_GET['match_id'])) {
    header('Location: tournaments.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$match_id = (int)$_GET['match_id'];

$stmt = $pdo->prepare("SELECT m.*, t.name, t.type, t.commission, t.prize_pool FROM matches m JOIN tournaments t ON m.tournament_id = t.id WHERE m.id = ? AND m.lobby_active = TRUE");
$stmt->execute([$match_id]);
$match = $stmt->fetch();

if (!$match) {
    header('Location: tournaments.php');
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM match_players WHERE match_id = ? AND user_id = ?");
$stmt->execute([$match_id, $user_id]);
$is_participant = $stmt->fetchColumn() > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $stmt = $pdo->prepare("INSERT INTO lobby_chat (match_id, user_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$match_id, $user_id, $_POST['message']]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave'])) {
    leaveMatch($user_id, $match_id);
    header('Location: tournaments.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ready']) && $is_participant) {
    $stmt = $pdo->prepare("UPDATE match_players SET ready = 1 WHERE match_id = ? AND user_id = ?");
    $stmt->execute([$match_id, $user_id]);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM match_players WHERE match_id = ?");
    $stmt->execute([$match_id]);
    $player_count = $stmt->fetchColumn();

    $max_players = $match['mode'] == '1v1' ? 2 : ($match['mode'] == '2v2' ? 4 : 10);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM match_players WHERE match_id = ? AND ready = 1");
    $stmt->execute([$match_id]);
    $ready_count = $stmt->fetchColumn();

    if ($player_count == $max_players && $ready_count == $max_players) {
        $stmt = $pdo->prepare("UPDATE matches SET status = 'ongoing', end_time = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?");
        $stmt->execute([$match_id]);
        try {
            $rcon = new Rcon();
            foreach ($players as $player) {
                $rcon->setPlayerTeam($player['steam_id'], $player['team']);
            }
        } catch (Exception $e) {
            error_log("RCON error on match start: " . $e->getMessage());
        }
    }
}

$winning_team = checkMatchCompletion($match_id);

$stmt = $pdo->prepare("SELECT mp.user_id, mp.team, u.username, u.steam_id FROM match_players mp JOIN users u ON mp.user_id = u.id WHERE mp.match_id = ?");
$stmt->execute([$match_id]);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);
$team1 = array_filter($players, fn($p) => $p['team'] == 'team1'); // Спецназ (CT)
$team2 = array_filter($players, fn($p) => $p['team'] == 'team2'); // Террористы (T)
$player_count = count($players);
$max_players = $match['mode'] == '1v1' ? 2 : ($match['mode'] == '2v2' ? 4 : 10);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> - Лобби</title>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="lobby">
    <header>
        <a href="index.php" class="logo"><h1><?php echo SITE_TITLE; ?></h1></a>
        <nav>
            <a href="index.php" class="nav-btn">Главная</a>
            <a href="tournaments.php" class="nav-btn">Турниры</a>
            <a href="profile.php" class="nav-btn">Профиль</a>
            <a href="rewards.php" class="nav-btn">Призы</a>
            <a href="rules.php" class="nav-btn">Правила</a>
            <a href="?logout" class="nav-btn">Выйти</a>
        </nav>
    </header>

    <main>
        <section class="lobby">
            <div class="lobby-main">
                <h2><?php echo htmlspecialchars($match['name']); ?></h2>
                <div class="match-core" id="match-players">
                    <div class="team" id="team1">
                        <p>Спецназ</p>
                        <?php foreach ($team1 as $player): ?>
                            <?php $steam = getSteamProfile($player['steam_id']); ?>
                            <div class="player-info">
                                <img src="<?php echo $steam['avatar'] ?? 'https://via.placeholder.com/40'; ?>" alt="Avatar" class="avatar small">
                                <span><?php echo htmlspecialchars($player['username']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="vs">VS</div>
                    <div class="team" id="team2">
                        <p>Террористы</p>
                        <?php foreach ($team2 as $player): ?>
                            <?php $steam = getSteamProfile($player['steam_id']); ?>
                            <div class="player-info">
                                <img src="<?php echo $steam['avatar'] ?? 'https://via.placeholder.com/40'; ?>" alt="Avatar" class="avatar small">
                                <span><?php echo htmlspecialchars($player['username']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <p class="map">Карта: <?php echo $match['map'] ?? 'de_dust2'; ?></p>
                <p class="stake">Ставка: <?php echo $match['bet_amount']; ?> TP</p>
                <div id="match-result">
                    <?php if ($winning_team): ?>
                        <p class="success">Победила <?php echo $winning_team == 'team1' ? 'Спецназ' : 'Террористы'; ?>! Команда получает <?php echo $match['bet_amount'] * $max_players; ?> TP</p>
                    <?php endif; ?>
                </div>
                <?php if ($is_participant): ?>
                    <form method="POST" id="lobby-actions">
                        <button type="submit" name="leave" class="action-btn">Выйти</button>
                        <?php if ($match['status'] == 'pending' && $player_count == $max_players): ?>
                            <button type="submit" name="ready" class="action-btn">Готов</button>
                        <?php elseif ($match['status'] == 'ongoing' && $player_count == $max_players): ?>
                            <a href="steam://connect/46.174.52.43:27015" class="action-btn">Подключиться к серверу</a>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>
            <div class="lobby-side">
                <div class="match-details" id="match-details">
                    <p>Тип: <?php echo $match['type'] == 'quick' ? 'Быстрый' : 'Сетка'; ?></p>
                    <p>Приз: <?php echo $match['type'] == 'quick' ? $match['bet_amount'] * $max_players : $match['prize_pool']; ?> TP</p>
                    <p>Статус: <?php echo $match['status'] == 'pending' ? 'Ожидание' : 'В процессе'; ?></p>
                    <p>До конца: <span id="timer"><?php echo $match['end_time'] ? round((strtotime($match['end_time']) - time()) / 60) : 'Не начат'; ?></span> мин</p>
                </div>
                <div class="chat" id="chat-messages">
                    <!-- Чат обновляется через AJAX -->
                </div>
                <?php if ($is_participant): ?>
                    <form id="chat-form" class="chat-form">
                        <input type="text" name="message" placeholder="Чат" required>
                        <button type="submit" class="action-btn small">></button>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer>
        <p>© 2025 <?php echo SITE_DOMAIN; ?></p>
    </footer>

    <script>
    function updateLobby() {
        fetch('api/lobby_data.php?match_id=<?php echo $match_id; ?>')
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Lobby update error:', data.error);
                    return;
                }

                // Обновляем команды
                const team1 = document.getElementById('team1');
                const team2 = document.getElementById('team2');
                team1.innerHTML = '<p>Спецназ</p>';
                team2.innerHTML = '<p>Террористы</p>';
                data.match.team1.forEach(player => {
                    team1.insertAdjacentHTML('beforeend', `<div class="player-info"><img src="${player.avatar}" alt="Avatar" class="avatar small"><span>${player.username}</span></div>`);
                });
                data.match.team2.forEach(player => {
                    team2.insertAdjacentHTML('beforeend', `<div class="player-info"><img src="${player.avatar}" alt="Avatar" class="avatar small"><span>${player.username}</span></div>`);
                });

                // Обновляем детали матча
                const details = document.getElementById('match-details');
                details.innerHTML = `
                    <p>Тип: ${data.match.type == 'quick' ? 'Быстрый' : 'Сетка'}</p>
                    <p>Приз: ${data.match.prize} TP</p>
                    <p>Статус: ${data.match.status == 'pending' ? 'Ожидание' : 'В процессе'}</p>
                    <p>До конца: <span id="timer">${data.match.end_time}</span> мин</p>
                `;

                // Обновляем действия
                const actions = document.getElementById('lobby-actions');
                if (data.match.status === 'ongoing' && data.match.players_count === data.match.max_players) {
                    actions.innerHTML = '<button type="submit" name="leave" class="action-btn">Выйти</button><a href="steam://connect/46.174.52.43:27015" class="action-btn">Подключиться к серверу</a>';
                } else if (data.match.status === 'pending' && data.match.players_count === data.match.max_players) {
                    actions.innerHTML = '<button type="submit" name="leave" class="action-btn">Выйти</button><button type="submit" name="ready" class="action-btn">Готов</button>';
                } else {
                    actions.innerHTML = '<button type="submit" name="leave" class="action-btn">Выйти</button>';
                }

                // Обновляем чат
                const chat = document.getElementById('chat-messages');
                chat.innerHTML = '';
                data.chat.forEach(msg => {
                    chat.insertAdjacentHTML('beforeend', `<p><strong>${msg.username}:</strong> ${msg.message}</p>`);
                });
                chat.scrollTop = chat.scrollHeight;

                // Уведомление о завершении
                const result = document.getElementById('match-result');
                if (data.match.status === 'finished') {
                    const winningTeam = '<?php echo $winning_team; ?>' === 'team1' ? 'Спецназ' : 'Террористы';
                    result.innerHTML = `<p class="success">Победила ${winningTeam}! Команда получает ${data.match.prize} TP</p>`;
                    setTimeout(() => window.location.href = 'tournaments.php', 5000);
                } else {
                    result.innerHTML = '';
                }
            })
            .catch(error => console.error('Error updating lobby:', error));
    }

    setInterval(updateLobby, 5000);
    updateLobby();

    document.getElementById('chat-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const message = this.querySelector('input[name="message"]').value;
        fetch('lobby.php?match_id=<?php echo $match_id; ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'message=' + encodeURIComponent(message)
        }).then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            this.reset();
            updateLobby();
        }).catch(error => console.error('Error sending message:', error));
    });
    </script>
</body>
</html>