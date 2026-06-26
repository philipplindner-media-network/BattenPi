<?php
// --- DATENBANK KONFIGURATION ---
define('DB_HOST', 'xxx');
define('DB_USER', 'smarthome'); // Ersetzen Sie dies
define('DB_PASS', 'xxx'); // Ersetzen Sie dies
define('DB_NAME', 'smarthome'); // Ersetzen Sie dies

// --- IOBROKER KONFIGURATION ---
// Die IP-Adresse deines ioBroker Servers
define('IO_BROKER_IP', 'xxx'); 
// Der Port der Simple-API (Standard ist 8087)
define('IO_BROKER_PORT', '8087');

// --- SYSTEM EINSTELLUNGEN ---
define('APP_NAME', 'BattenPi Control');
define('UPDATE_INTERVAL_VALUES', 10000); // Live-Werte alle 10 Sek.
define('UPDATE_INTERVAL_PING', 5000);    // Ping alle 5 Sek.
define('GRID_COLUMNS', 4); // 4 Spalten
define('GRID_ROWS', 3);    // 2 Zeilen

// --- IOBROKER STATES FÜR STATUS ---
// Welcher State soll für den "Online-Check" geprüft werden?
define('IO_CHECK_STATE', 'system.adapter.admin.0.alive');
?>
