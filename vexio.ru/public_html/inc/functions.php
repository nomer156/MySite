<?php
require_once 'db.php';
require_once 'rcon.php';

function deductTournamentPoints($user_id, $amount) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET tournament_points = tournament_points - ? WHERE id = ? AND tournament_points >= ?");
    return $stmt->execute([$amount, $user_id, $amount]);
}

function addTournamentPoints($user_id, $amount, $description = 'Победа в матче') {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET tournament_points = tournament_points + ? WHERE id = ?");
    $stmt->execute([$amount, $user_id]);
    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, description) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $amount, $description]);
}

function isUserInMatch($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT current_match_id FROM users WHERE id = ? AND current_match_id IS NOT NULL");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

function joinMatch($user_id, $match_id, $bet_amount, $team = 'team1') {
    global $pdo;
    if (!isUserInMatch($user_id) && deductTournamentPoints($user_id, $bet_amount)) {
        $stmt = $pdo->prepare("INSERT INTO match_players (match_id, user_id, team) VALUES (?, ?, ?)");
        $stmt->execute([$match_id, $user_id, $team]);
        $stmt = $pdo->prepare("UPDATE users SET current_match_id = ? WHERE id = ?");
        $stmt->execute([$match_id, $user_id]);
        return true;
    }
    return false;
}

function leaveMatch($user_id, $match_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT bet_amount FROM matches WHERE id = ? AND lobby_active = TRUE");
    $stmt->execute([$match_id]);
    $match = $stmt->fetch();
    if ($match) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM match_players WHERE match_id = ? AND user_id = ?");
        $stmt->execute([$match_id, $user_id]);
        if ($stmt->fetchColumn() > 0) {
            addTournamentPoints($user_id, $match['bet_amount'], 'Возврат ставки');
            $stmt = $pdo->prepare("DELETE FROM match_players WHERE match_id = ? AND user_id = ?");
            $stmt->execute([$match_id, $user_id]);
            $stmt = $pdo->prepare("UPDATE users SET current_match_id = NULL WHERE id = ?");
            $stmt->execute([$user_id]);

            // Удаляем матч, если игроков не осталось
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM match_players WHERE match_id = ?");
            $stmt->execute([$match_id]);
            if ($stmt->fetchColumn() == 0) {
                $stmt = $pdo->prepare("DELETE FROM matches WHERE id = ?");
                $stmt->execute([$match_id]);
            }
        }
    }
}

function addWelcomeBonus($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ? AND description = 'Приветственный бонус'");
    $stmt->execute([$user_id]);
    if ($stmt->fetchColumn() == 0) {
        addTournamentPoints($user_id, 100, 'Приветственный бонус');
    }
}

function getPlayerLevel($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT wins FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $wins = $stmt->fetchColumn();
    return ($wins > 10) ? 'pro' : 'newbie';
}

function joinQuickMatch($user_id, $tournament_id, $bet_amount, $mode) {
    global $pdo;
    $max_players = $mode == '1v1' ? 2 : ($mode == '2v2' ? 4 : 10);
    $stmt = $pdo->prepare("SELECT m.id FROM matches m WHERE m.tournament_id = ? AND m.status = 'pending' AND m.lobby_active = TRUE");
    $stmt->execute([$tournament_id]);
    $match = $stmt->fetch();

    if ($match) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM match_players WHERE match_id = ? AND team = 'team1'");
        $stmt->execute([$match['id']]);
        $team1_count = $stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM match_players WHERE match_id = ? AND team = 'team2'");
        $stmt->execute([$match['id']]);
        $team2_count = $stmt->fetchColumn();
        $player_count = $team1_count + $team2_count;

        if ($player_count < $max_players) {
            $team = $team1_count <= $team2_count ? 'team1' : 'team2';
            if (joinMatch($user_id, $match['id'], $bet_amount, $team)) {
                return $match['id'];
            }
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO matches (tournament_id, bet_amount, map, mode, end_time) VALUES (?, ?, 'de_dust2', ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
        $stmt->execute([$tournament_id, $bet_amount, $mode]);
        $match_id = $pdo->lastInsertId();
        if (joinMatch($user_id, $match_id, $bet_amount, 'team1')) {
            return $match_id;
        }
    }
    return false;
}

function joinBracketTournament($user_id, $tournament_id, $bet_amount, $mode) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT mp.user_id) as players FROM match_players mp JOIN matches m ON mp.match_id = m.id WHERE m.tournament_id = ? AND m.stage = 'round_16'");
    $stmt->execute([$tournament_id]);
    $player_count = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT max_players FROM tournaments WHERE id = ?");
    $stmt->execute([$tournament_id]);
    $max_players = $stmt->fetchColumn();

    if ($player_count >= $max_players) {
        return false;
    }

    $stmt = $pdo->prepare("INSERT INTO matches (tournament_id, bet_amount, map, mode, stage, end_time) VALUES (?, ?, 'de_dust2', ?, 'round_16', DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
    $stmt->execute([$tournament_id, $bet_amount, $mode]);
    $match_id = $pdo->lastInsertId();

    if (joinMatch($user_id, $match_id, $bet_amount, 'team1')) {
        if ($player_count + 1 == $max_players) {
            startBracketTournament($tournament_id);
        }
        return $match_id;
    }
    return false;
}

function startBracketTournament($tournament_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT m.id FROM matches m WHERE m.tournament_id = ? AND m.stage = 'round_16'");
    $stmt->execute([$tournament_id]);
    $matches = $stmt->fetchAll();

    if (count($matches) < 2) return;

    $stmt = $pdo->prepare("SELECT mp.user_id FROM match_players mp JOIN matches m ON mp.match_id = m.id WHERE m.tournament_id = ? AND m.stage = 'round_16'");
    $stmt->execute([$tournament_id]);
    $players = $stmt->fetchAll(PDO::FETCH_COLUMN);

    shuffle($players);
    $match_index = 0;
    for ($i = 0; $i < count($players); $i++) {
        $team = $i % 2 == 0 ? 'team1' : 'team2';
        $stmt = $pdo->prepare("INSERT INTO match_players (match_id, user_id, team) VALUES (?, ?, ?)");
        $stmt->execute([$matches[$match_index]['id'], $players[$i], $team]);
        if ($team == 'team2') $match_index++;
    }

    $stmt = $pdo->prepare("UPDATE matches SET status = 'pending' WHERE tournament_id = ? AND stage = 'round_16'");
    $stmt->execute([$tournament_id]);
    $stmt = $pdo->prepare("UPDATE tournaments SET status = 'ongoing' WHERE id = ?");
    $stmt->execute([$tournament_id]);
}

function finishMatch($match_id, $winning_team) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT bet_amount, mode FROM matches WHERE id = ? AND status = 'ongoing'");
    $stmt->execute([$match_id]);
    $match = $stmt->fetch();

    if ($match) {
        $max_players = $match['mode'] == '1v1' ? 2 : ($match['mode'] == '2v2' ? 4 : 10);
        $prize = $match['bet_amount'] * $max_players;
        $stmt = $pdo->prepare("SELECT user_id FROM match_players WHERE match_id = ? AND team = ?");
        $stmt->execute([$match_id, $winning_team]);
        $winners = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($winners as $winner_id) {
            addTournamentPoints($winner_id, $prize / count($winners), 'Победа в матче');
        }

        $stmt = $pdo->prepare("UPDATE matches SET status = 'finished' WHERE id = ?");
        $stmt->execute([$match_id]);
        $stmt = $pdo->prepare("UPDATE users SET current_match_id = NULL WHERE current_match_id = ?");
        $stmt->execute([$match_id]);

        // Выкидываем игроков с сервера через RCON
        try {
            $rcon = new Rcon();
            $rcon->send("kickall"); // Команда для выкидывания всех игроков (может отличаться в зависимости от сервера)
        } catch (Exception $e) {
            error_log("RCON error on match finish: " . $e->getMessage());
        }
    }
}

function checkMatchCompletion($match_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT end_time FROM matches WHERE id = ? AND status = 'ongoing'");
    $stmt->execute([$match_id]);
    $end_time = $stmt->fetchColumn();

    if ($end_time && strtotime($end_time) <= time()) {
        try {
            $rcon = new Rcon();
            $winning_team = $rcon->getMatchResult();
            finishMatch($match_id, $winning_team);
            return $winning_team;
        } catch (Exception $e) {
            error_log("RCON error: " . $e->getMessage());
            $stmt = $pdo->prepare("SELECT team FROM match_players WHERE match_id = ? LIMIT 1");
            $stmt->execute([$match_id]);
            $winning_team = $stmt->fetchColumn() == 'team1' ? 'team1' : 'team2';
            finishMatch($match_id, $winning_team);
            return $winning_team;
        }
    }
    return null;
}
?>