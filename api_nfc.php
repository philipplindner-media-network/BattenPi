<?php
// api_nfc.php
// Diese Datei wird vom PC-Python-Skript aufgerufen

if (isset($_GET['uid'])) {
    $uid = preg_replace("/[^A-Za-z0-9]/", "", $_GET['uid']); // Sicherheit: nur Alphanumerisch
    
    // Speichert die UID in die Datei, die von der register.php gelesen wird
    file_put_contents('current_nfc_register_uid.txt', $uid);
    
    echo "OK";
} else {
    echo "No UID provided";
}
?>
