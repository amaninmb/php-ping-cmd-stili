<?php
// ------- CANLI CMD BENZETİMİ AYARLARI -------
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', 0);
while (ob_get_level()) { ob_end_flush(); }
ob_implicit_flush(true);
header("Content-Type: text/plain; charset=utf-8");

// ------- AYAR ------
$host = isset($_GET['host']) ? $_GET['host'] : "erapor.saglik.gov.tr";

// DNS çöz → IP bul
$ip = gethostbyname($host);

// CMD başlığı
echo "Pinging $host [$ip] with 32 bytes of data:\n\n";

// ------- SÜREKLİ PING (Windows'a BENZER) -------
while (true) {

    // ping komutu (Windows formatına yakın)
    $cmd = "ping $ip -n 1";

    $output = [];
    exec($cmd, $output);

    foreach ($output as $line) {

        // Windows çıktısını sadeleştir (sadece Reply-from satırı)
        if (stripos($line, "Reply") !== false || stripos($line, "Yanıt") !== false) {
            echo $line . "\n";
        }

        // Hata ya da TTL yoksa yine yaz
        if (stripos($line, "timed") !== false ||
            stripos($line, "TTL") !== false ||
            stripos($line, "TTL=") !== false) {
            echo $line . "\n";
        }
    }

    // Anlık yazması için flush
    flush();

    // CMD -t davranışı (1 saniye aralık)
    sleep(1);
}
?>
