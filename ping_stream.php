<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

set_time_limit(0);
ignore_user_abort(false);

function sse($event, $data) {
    if ($event) echo "event: $event\n";
    foreach (explode("\n", trim($data)) as $line) {
        echo "data: $line\n";
    }
    echo "\n";
    @ob_flush();
    @flush();
}

$host = trim($_GET['host'] ?? '');
if (!$host || !preg_match('/^[A-Za-z0-9\.\-]+$/', $host)) {
    sse(null, "Geçersiz host.");
    exit;
}

$resolved = gethostbyname($host);
sse("meta", json_encode(["ip" => $resolved]));

// ❗ cmd /C kaldırıldı – sorunu çözen satır
$cmd = 'ping -t ' . escapeshellarg($host);

// bufferları kapat
while (ob_get_level()) ob_end_flush();
ob_implicit_flush(true);

$desc = [
    0 => ["pipe", "r"],
    1 => ["pipe", "w"],
    2 => ["pipe", "w"]
];
$proc = proc_open($cmd, $desc, $pipes);

if (!is_resource($proc)) {
    sse(null, "proc_open açılamadı.");
    exit;
}

stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);

while (!connection_aborted()) {

    $line = fgets($pipes[1]);
    if ($line !== false) {
        $line = trim($line);
        if ($line !== "") {
            sse(null, $line);

            // Reply satırı parse
            if (preg_match('/Reply from ([0-9\.]+):.*time[=<] ?([0-9a-z]+).*TTL=([0-9]+)/i', $line, $m)) {
                sse("meta", json_encode([
                    "ip"   => $m[1],
                    "time" => $m[2],
                    "ttl"  => $m[3]
                ]));
            }
        }
    }

    usleep(100000);
}

foreach ($pipes as $p) fclose($p);
proc_terminate($proc);
proc_close($proc);

sse("meta", json_encode(["note" => "Ping sonlandırıldı."]));
