/**
 * BattenPi - Waschmaschinen Steuerung v3
 * Logik: 15 Min Puffer + Leistungs-Check (Watt) vor Abschaltung
 */

const wmConfig = {
    dpProgram: "0_userdata.0.waschmaschine.program_name",
    socketId: "xxx",          // Aktualisiert auf deinen neuen Schalt-Datenpunkt
    powerId: "xxx"      // Aktualisiert auf deinen echten Watt-Datenpunkt
};

// Überprüfe, ob window.ioURL gesetzt ist (Fallback falls direkt aufgerufen)
window.ioURL = window.ioURL || "http://xxx:8087";

// Hilfsfunktion: Erkennt, ob der Portrait-Modus in der URL aktiv ist
function getPortraitParam() {
    return window.location.search.includes('view=portrait') ? '?view=portrait' : '';
}

window.startWaschmaschine = function(name, seconds) {
    const buffer = 900; // 15 Min Puffer
    if(!confirm(name + " starten?")) return;

    const endTime = Date.now() + ((seconds + buffer) * 1000);
    localStorage.setItem('wm_endTime', endTime);
    localStorage.setItem('wm_name', name);

    // Befehle an ioBroker senden
    fetch(`${window.ioURL}/set/${wmConfig.socketId}?value=true`);
    fetch(`${window.ioURL}/set/${wmConfig.dpProgram}?value=${encodeURIComponent(name)}`);
    
    // Seite neu laden und den View-Status (Hochformat) beibehalten!
    window.location.href = 'index.php' + getPortraitParam(); 
};

window.checkFinalPowerOff = function() {
    fetch(`${window.ioURL}/getPlainValue/${wmConfig.powerId}`).then(res => res.text()).then(val => {
        let watt = parseFloat(val.replace(/"/g, ""));
        if (watt < 3.0) {
            fetch(`${window.ioURL}/set/${wmConfig.socketId}?value=false`);
            localStorage.removeItem('wm_endTime');
            localStorage.removeItem('wm_name');
            window.location.href = 'index.php' + getPortraitParam();
        } else {
            const timerEl = document.getElementById('wm-timer');
            const infoEl = document.getElementById('wm-info');
            if(timerEl) timerEl.innerText = "WAIT";
            if(infoEl) infoEl.innerText = "Warte auf Stillstand (" + watt + "W)";
            setTimeout(window.checkFinalPowerOff, 30000);
        }
    }).catch(e => console.error("Fehler beim PowerOff-Check", e));
};

window.updateWMDisplay = function() {
    const endTime = localStorage.getItem('wm_endTime');
    if (!endTime) return;
    const remaining = Math.round((endTime - Date.now()) / 1000);

    const timerEl = document.getElementById('wm-timer');
    if (remaining <= 0) {
        window.checkFinalPowerOff();
    } else if (timerEl) {
        const mins = Math.floor(remaining / 60);
        const secs = remaining % 60;
        timerEl.innerText = `${mins}:${secs < 10 ? '0' : ''}${secs}`;
    }
};

if (localStorage.getItem('wm_endTime')) {
    setInterval(window.updateWMDisplay, 1000);
}

window.stopWaschmaschine = function() {
    if(!confirm("Programm wirklich abbrechen?")) return;
    localStorage.removeItem('wm_endTime');
    localStorage.removeItem('wm_name');
    fetch(`${window.ioURL}/set/${wmConfig.socketId}?value=false`);
    window.location.href = 'index.php' + getPortraitParam();
};

