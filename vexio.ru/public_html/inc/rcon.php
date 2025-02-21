<?php

class Rcon {
    private $host = '46.174.52.43';
    private $port = 27015;
    private $password = 'APXWI8E4'; // Укажи пароль RCON от MyArena
    private $socket;

    public function __construct() {
        $this->socket = fsockopen('udp://' . $this->host, $this->port, $errno, $errstr, 2);
        if (!$this->socket) {
            throw new Exception("RCON connection failed: $errstr ($errno)");
        }
    }

    public function send($command) {
        $packet = pack('VV', 1, 10) . $command . "\0password\0"; // Упрощённый пример
        fwrite($this->socket, $packet);
        $response = fread($this->socket, 4096);
        return $response;
    }

    public function setPlayerTeam($steam_id, $team) {
        $team_id = $team == 'team1' ? 3 : 2; // 3 = CT (Спецназ), 2 = T (Террористы)
        $this->send("sm_team $steam_id $team_id"); // Пример команды SourceMod
    }

    public function getMatchResult() {
        // Пока заглушка — нужен доступ к логам или API MyArena
        return rand(0, 1) ? 'team1' : 'team2'; // Случайный победитель
    }

    public function __destruct() {
        fclose($this->socket);
    }
}

?>