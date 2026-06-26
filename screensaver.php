<?php
// Exklusiver Screensaver mit Logo, Uhrzeit und Live-Wetter aus ioBroker (OpenWeatherMap)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Screensaver</title>
    <style>
        :root {
            --bg-color: #0d0d0d;
            --accent-color: #00d2ff; /* Neon-Blau */
            --text-muted: #555555;
        }
        
        body {
            margin: 0;
            padding: 0;
            background-color: var(--bg-color);
            color: #ffffff;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            user-select: none;
            cursor: none;
        }

        /* Sanfte Bewegung zum Schutz vor Einbrennen (Burn-In-Protection) */
        .saver-container {
            text-align: center;
            animation: burnInProtection 25s ease-in-out infinite alternate;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        /* Logo-Bereich */
        .logo-container {
            margin-bottom: -15px;
            max-width: 280px;
            height: auto;
            opacity: 0.85;
            filter: drop-shadow(0 0 15px rgba(0, 210, 255, 0.15));
        }

        .logo-container img {
            width: 100%;
            height: auto;
            border-radius: 12px;
        }

        /* Wetter-Anzeige */
        .weather-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
            font-size: 3.5vw;
            font-weight: 300;
            color: #eeeeee;
        }

        .weather-icon-img {
            width: 6vw;
            height: auto;
            filter: drop-shadow(0 0 8px rgba(255, 255, 255, 0.2));
        }

        .time {
            font-size: 12vw;
            font-weight: 700;
            line-height: 1.05;
            color: #ffffff;
            text-shadow: 0 0 20px rgba(0, 210, 255, 0.2);
            margin: 10px 0 0 0;
        }

        .date {
            font-size: 2.8vw;
            color: var(--accent-color);
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: -5px;
            font-weight: 400;
        }

        .hint {
            margin-top: 40px;
            font-size: 1.2vw;
            color: var(--text-muted);
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        @keyframes burnInProtection {
            0% { transform: translate(-3px, -3px); }
            33% { transform: translate(3px, -1px); }
            66% { transform: translate(-1px, 3px); }
            100% { transform: translate(3px, 3px); }
        }
    </style>
</head>
<body onclick="exitScreensaver()">

    <div class="saver-container">
        <div class="logo-container">
            <img src="pi_SHS.png" alt="SmartHome Steuerung">
        </div>

        <div id="scroll-time" class="time">00:00</div>
        <div id="scroll-date" class="date">Wird geladen...</div>
        
        <div class="weather-container" id="weather-box" style="display: none;">
            <img class="weather-icon-img" id="weather-icon" src="" alt="Wetter">
            <span id="weather-temp">-- °C</span>
        </div>

        <div class="hint">Tippen zum Dashboard</div>
    </div>

    <script>
        // --- CONFIG: ioBroker SimpleAPI-Adresse ---
        const ioURL = "http://192.168.1.23:8087"; 
        
        // Exakte Datenpunkte laut deinen Screenshots
        const dpTemp = "openweathermap.0.forecast.current.temperature";
        const dpIcon = "openweathermap.0.forecast.current.icon";

        function updateClock() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            document.getElementById('scroll-time').textContent = hours + ':' + minutes;
            
            const options = { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' };
            document.getElementById('scroll-date').textContent = now.toLocaleDateString('de-DE', options);
        }

        // Wetter über ioBroker SimpleAPI laden
        function updateWeather() {
            // 1. Temperatur auslesen
            fetch(`${ioURL}/get/${dpTemp}`)
                .then(response => response.json())
                .then(data => {
                    if(data && data.val !== undefined) {
                        // Auf eine Nachkommastelle runden
                        const temp = parseFloat(data.val).toFixed(1);
                        document.getElementById('weather-temp').textContent = temp + " °C";
                        document.getElementById('weather-box').style.display = "flex";
                    }
                })
                .catch(err => console.log("Wetter-Fehler Temperatur:", err));

            // 2. Icon-URL auslesen
            fetch(`${ioURL}/get/${dpIcon}`)
                .then(response => response.json())
                .then(data => {
                    if(data && data.val !== undefined) {
                        // Setzt die Bild-URL direkt in das <img> Tag ein
                        document.getElementById('weather-icon').src = data.val;
                    }
                })
                .catch(err => console.log("Wetter-Fehler Icon:", err));
        }

        // Intervalle aktivieren
        setInterval(updateClock, 1000);
        updateClock();

        // Wetter sofort laden und alle 15 Minuten erneuern
        updateWeather();
        setInterval(updateWeather, 900000);

        function exitScreensaver() {
            const urlParams = new URLSearchParams(window.location.search);
            const view = urlParams.get('view');
            if (view === 'portrait') {
                window.location.href = 'index.php?menu=0&view=portrait';
            } else {
                window.location.href = 'index.php?menu=0';
            }
        }
    </script>
</body>
</html>
