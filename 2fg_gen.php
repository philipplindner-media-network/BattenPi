<?php
session_start();
// Sicherheit: Nur wer schon Level 5+ ist, darf den Generator für andere öffnen
// ODER du nimmst die Sperre raus, wenn du ihn selbst als Backup nutzt.
if (!isset($_SESSION['level_id']) || $_SESSION['level_id'] < 5) {
    // Optional: Mit Passwort schützen, falls man nicht eingeloggt ist
}

$secret = "smarhomes-systems-123456_test"; // Ändere dies in ein eigenes Wort!
$time_slice = floor(time() / 60);
$token = substr(hash('sha256', $secret . $time_slice), 0, 6); // 6-stelliger Code

// QR-Code URL zur Anzeige
$qr_data = urlencode($token);
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=$qr_data";
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Studio 2FA</title>
    <style>
        body { background: #050505; color: white; font-family: sans-serif; text-align: center; padding: 50px 20px; }
        .code { font-size: 40px; color: #00d2ff; font-weight: bold; letter-spacing: 5px; margin: 20px 0; }
        .progress { width: 100%; background: #222; height: 5px; border-radius: 5px; margin-top: 20px; }
        .bar { height: 100%; background: #00d2ff; width: 100%; transition: width 1s linear; }
    </style>
</head>
<body>
    <h2>Backup Login</h2>
    <img src="<?= $qr_url ?>" style="border: 10px solid white; border-radius: 10px;">
    <div class="code"><?= strtoupper($token) ?></div>
    <div class="progress"><div class="bar" id="timerBar"></div></div>
    <script>
        let timeLeft = 60 - (Math.floor(Date.now() / 1000) % 60);
        setInterval(() => {
            timeLeft--;
            document.getElementById('timerBar').style.width = (timeLeft / 60 * 100) + "%";
            if (timeLeft <= 0) location.reload();
        }, 1000);
    </script>
</body>
</html>
