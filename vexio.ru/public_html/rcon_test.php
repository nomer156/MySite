<?php

$socket = fsockopen("tcp://46.174.52.43", 27015, $errno, $errstr, 5);
if (!$socket) {
    die("Error: $errstr ($errno)");
}
fwrite($socket, "rcon ciAlweYf28NwXorGsSLs say Test from PHP!\n");
$response = fread($socket, 4096);
echo "Response: " . bin2hex($response);
fclose($socket);
?>