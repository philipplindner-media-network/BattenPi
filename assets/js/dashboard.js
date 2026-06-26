/**
 * BattenPi Dashboard Logic - FULL STABLE VERSION
 * Fixes: ReferenceErrors, Camera-Support, and WLED-Scaling
 */

// --- 1. GLOBALE VARIABLEN & FALLBACKS ---
window.ioURL = window.ioURL || "http://xxx:8087";
window.activeDimmerId = '';
window.activeColorId = '';

// --- 2. GLOBALE FUNKTIONEN (Für index.php onclick Events) ---

/**
 * Verarbeitet Klicks auf Kacheln (Toggle, Ordner, Kamera)[cite: 3, 6]
 */
window.handleBtnClick = async function(el, e) {
    const type = el.getAttribute('data-type');
    const ioId = el.getAttribute('data-io-id');
    const label = el.querySelector('.label')?.innerText || '';
    const dimmerId = el.getAttribute('data-dimmer-id');
    const colorId = el.getAttribute('data-color-id');

    // Falls auf den Dimmer-Button innerhalb der Kachel geklickt wurde[cite: 2]
    if (e.target.classList.contains('dim-btn')) {
        window.openControl(e, label, dimmerId, colorId);
        return;
    }

    // Navigation für Ordner[cite: 2]
    if (type === 'folder') {
        window.location.href = '?menu=' + el.id.replace('btn_', '');
        return;
    }

    // Kamera-Logik (Letztes Bild oder Live-Stream)[cite: 3, 6]
    if (label.toLowerCase().includes('letzte') || label.toLowerCase().includes('spion')) {
        if (label.toLowerCase().includes('letzte')) {
            try {
                const res = await fetch(`${window.ioURL}/getPlainValue/mqtt.1.kameraserver.vis.last_url`);
                let imgUrl = (await res.text()).replace(/"/g, "").trim();
                window.openMediaModal(label, imgUrl, 'img');
            } catch (err) { console.error("Kamera-Fehler:", err); }
        } else {
            const videoUrl = "http://192.168.1.24:8090/video.webm?oids=9&size=1280x720&backColor=0,0,0";
            window.openMediaModal(label, videoUrl, 'video');
        }
        return;
    }

    // Standard Toggle (An/Aus)
    if (type === 'toggle' && ioId) {
        fetch(`${window.ioURL}/toggle/${ioId}`, { mode: 'no-cors' });
        setTimeout(updateAll, 300);
    }
};

/**
 * Öffnet das Steuerungs-Modal für Licht/Dimmer[cite: 6]
 */
window.openControl = async function(e, name, dimmerId, colorId) {
    if(e) e.stopPropagation();
    const modal = document.getElementById('ctrlModal');
    if(!modal) return;

    window.activeDimmerId = dimmerId;
    window.activeColorId = colorId;
    document.getElementById('modalTitle').innerText = name;
    
    // UI Sektionen anzeigen/ausblenden
    if(document.getElementById('dimmerSection')) document.getElementById('dimmerSection').style.display = dimmerId ? 'block' : 'none';
    if(document.getElementById('colorSection')) document.getElementById('colorSection').style.display = colorId ? 'block' : 'none';
    if(document.getElementById('mediaBox')) document.getElementById('mediaBox').innerHTML = "";

    // Reset-Button für Licht einblenden[cite: 6]
    const resetBtn = modal.querySelector("button[onclick='resetDevice()']");
    if(resetBtn) resetBtn.style.display = 'block';

    // Aktuellen Dimmer-Wert laden[cite: 5, 6]
    if (dimmerId) {
        try {
            const res = await fetch(`${window.ioURL}/getPlainValue/${dimmerId}`);
            const val = await res.text();
            let displayVal = dimmerId.toLowerCase().includes('wled') ? Math.round((val/255)*100) : val;
            if(document.getElementById('dimRange')) document.getElementById('dimRange').value = displayVal;
        } catch(err) { console.error("Fehler beim Laden des Dimmer-Werts"); }
    }

    modal.style.display = 'flex';
};

/**
 * Öffnet das Modal für Kamera-Bilder oder Videos[cite: 5]
 */
window.openMediaModal = function(name, url, type) {
    const modal = document.getElementById('ctrlModal');
    const modalContent = document.querySelector('.modal-content');
    
    document.getElementById('modalTitle').innerText = name;
    
    if (document.getElementById('dimmerSection')) document.getElementById('dimmerSection').style.display = 'none';
    if (document.getElementById('colorSection')) document.getElementById('colorSection').style.display = 'none';
    
    const resetBtn = modal.querySelector("button[onclick='resetDevice()']");
    if (resetBtn) resetBtn.style.display = 'none';

    modalContent.style.width = "90%"; 
    modalContent.style.maxWidth = "900px";

    let box = document.getElementById('mediaBox') || document.createElement('div');
    box.id = 'mediaBox';
    box.innerHTML = type === 'video' 
        ? `<video autoplay muted loop playsinline style="width:100%; border-radius:10px;"><source src="${url}" type="video/webm"></video>` 
        : `<img src="${url}" style="width:100%; border-radius:10px; border:2px solid var(--accent);">`;
    
    if (!document.getElementById('mediaBox')) modalContent.insertBefore(box, modalContent.lastElementChild);
    
    modal.style.display = 'flex';
};

window.closeControl = function() {
    document.getElementById('ctrlModal').style.display = 'none';
    if(document.getElementById('mediaBox')) document.getElementById('mediaBox').innerHTML = "";
};

/**
 * Setzt das aktive Licht auf Standardwerte (100% / Warmweiß)[cite: 5, 6]
 */
window.resetDevice = function() {
    if (window.activeDimmerId) {
        let maxVal = window.activeDimmerId.toLowerCase().includes('wled') ? 255 : 100;
        fetch(`${window.ioURL}/set/${window.activeDimmerId}?value=${maxVal}`, { mode: 'no-cors' });
        if(document.getElementById('dimRange')) document.getElementById('dimRange').value = 100;
    }
    if (window.activeColorId) {
        fetch(`${window.ioURL}/set/${window.activeColorId}?value=${encodeURIComponent('#ffce77')}`, { mode: 'no-cors' });
    }
    setTimeout(updateAll, 500);
};

window.setDimmer = function(val) {
    if (!window.activeDimmerId) return;
    let target = window.activeDimmerId.toLowerCase().includes('wled') ? Math.round((val/100)*255) : val;
    fetch(`${window.ioURL}/set/${window.activeDimmerId}?value=${target}`, { mode: 'no-cors' });
};

window.setColor = function(hex) {
    if (!window.activeColorId) return;
    fetch(`${window.ioURL}/set/${window.activeColorId}?value=${encodeURIComponent(hex.toUpperCase())}`, { mode: 'no-cors' })
    .then(() => setTimeout(updateAll, 500));
};

// --- 3. UPDATER & HILFSFUNKTIONEN ---

async function updateAll() {
    const buttons = document.querySelectorAll('.button');
        
    for (const btn of buttons) {
        const type = btn.getAttribute('data-type');
        const mainId = btn.getAttribute('data-io-id'); // Haupt ID (Schalten)
        const statusId = btn.getAttribute('data-status-id'); // Zusatz ID (Watt)
        const dimmerId = btn.getAttribute('data-dimmer-id');
        const valDisplay = btn.querySelector('.live-val');
        const powerDisplay = btn.querySelector('.power-val');
        const unit = btn.getAttribute('data-unit') || '';
        const labelText = btn.querySelector('.label')?.innerText.toLowerCase() || '';

        if (!valDisplay || !mainId || type === 'folder') continue;

        // NEU: Spezial-Logik für die Warnungskachel (anstatt continue)
        if (btn.getAttribute('onclick') && btn.getAttribute('onclick').includes('showWarning')) {
            try {
                const resWarn = await fetch(`${window.ioURL}/getPlainValue/${mainId}`);
                if (resWarn.ok) {
                    let rawWarn = await resWarn.text();
                    rawWarn = rawWarn.trim().replace(/^"/, "").replace(/"$/, "").replace(/\\"/g, '"');
                    
                    const data = JSON.parse(rawWarn);
                    const warn = Array.isArray(data) ? data[0] : data;
                    
                    if (warn && warn.Event) {
                        valDisplay.innerText = warn.Event.toUpperCase(); // Zeigt z.B. "STARKE HITZE"
                        valDisplay.style.color = "#FEFE04"; // Gelb/Orange wie im ioBroker
                    } else {
                        valDisplay.innerText = "KEINE WARNUNG";
                        valDisplay.style.color = "#777";
                    }
                }
            } catch (e) {
                valDisplay.innerText = "KEINE WARNUNG";
                valDisplay.style.color = "#777";
            }
            continue; // Springt nach der Verarbeitung zur nächsten Kachel
        }

        try {
            // 1. HAUPT-WERT LADEN
            const resMain = await fetch(`${window.ioURL}/getPlainValue/${mainId}`);
            let rawMain = resMain.ok ? (await resMain.text()).replace(/"/g, "").trim() : "ID?";

            // Status verarbeiten (AN/AUS)
            if (rawMain === "true" || rawMain === "on" || (type === 'toggle' && rawMain == "1")) {
                valDisplay.innerText = "AN";
                valDisplay.style.color = "#01DF3A";
            } else if (rawMain === "false" || rawMain === "off" || (type === 'toggle' && rawMain == "0")) {
                valDisplay.innerText = "AUS";
                valDisplay.style.color = "#777";
            } else {
                // SPEZIAL-LOGIK: LDR / Helligkeit (22 = 100%, 1200 = 0%)
                if (labelText.includes('ldr') || labelText.includes('helligkeit')) {
                    let rawVal = parseFloat(rawMain);
                    if (!isNaN(rawVal)) {
                        let percent = ((1022 - rawVal) / (1022 - 22)) * 100;
                        percent = Math.max(0, Math.min(100, percent)); // Begrenzung 0-100
                        valDisplay.innerText = Math.round(percent) + " %";
                        valDisplay.style.color = "#FFD700"; // Gold-Gelb
                    }
                }  
                // SPEZIAL-LOGIK: Internet Status (IP)
                else if (labelText.includes('internet')) {
                    let parts = rawMain.split('.');     
                    if (parts[2] === '178') {
                        valDisplay.innerText = "FRITZ!Box";
                        valDisplay.style.color = "#0054a6"; // Blau
                    } else if (parts[2] === '188') {
                        valDisplay.innerText = "LTE-Netz";
                        valDisplay.style.color = "#ffce77"; // Gelb/Orange
                    } else if (parts[2] === '67') {
                        valDisplay.innerText = "OpenWRT One";
                        valDisplay.style.color = "#9efd38"; // Ein schönes OpenWRT-Grün/Hellgrün
                    } else {
                        valDisplay.innerText = rawMain;
                        valDisplay.style.color = "var(--accent)";
                    }
                }  
                // STANDARD: Messwerte (Temp, Luftdruck etc.)
                else {
                    let numVal = parseFloat(rawMain);
                    if (!isNaN(numVal)) {
                        // Luftdruck-Korrektur (Pascal zu hPa)
                        if (labelText.includes('luftdruck') && numVal > 5000) numVal = numVal / 100;

                        valDisplay.innerText = numVal.toLocaleString('de-DE') + unit;
                        valDisplay.style.color = "var(--accent)";
                    } else {
                        valDisplay.innerText = rawMain.toUpperCase();
                        valDisplay.style.color = "var(--accent)";
                    }
                }
            }

            // 2. ZUSATZ-WERT LADEN (WATT / STATUS)
            if (statusId && statusId !== "" && statusId !== "-" && powerDisplay) {
                try {
                    const resStatus = await fetch(`${window.ioURL}/getPlainValue/${statusId}`);
                    if (resStatus.ok) {
                        let rawStatus = (await resStatus.text()).replace(/"/g, "").trim();
                        
                        // Wenn der ioBroker "true"/"false" liefert, ignorieren wir es für die Watt-Anzeige
                        if (rawStatus === "true" || rawStatus === "false" || rawStatus === "1" || rawStatus === "0") {
                            if (type !== 'dimmer') powerDisplay.innerText = "";
                        } else {
                            let numStatus = parseFloat(rawStatus);
                            if (!isNaN(numStatus)) {
                                powerDisplay.innerText = numStatus.toLocaleString('de-DE') + " W";
                                powerDisplay.style.color = "#00BFFF"; // Schönes Hellblau
                                powerDisplay.style.fontSize = "0.9em";
                            }
                        }
                    }
                } catch (e) { console.error("Watt-Update Fehler", e); }
            } else if (powerDisplay && type !== 'dimmer') {
                powerDisplay.innerText = "";
            }

            // 3. DIMMER LOGIK (Nur noch für echte Dimmer wie Lampen!)
            if (dimmerId && dimmerId !== "" && type === 'dimmer' && powerDisplay) {
                try {
                    const resD = await fetch(`${window.ioURL}/getPlainValue/${dimmerId}`);
                    if (resD.ok) {
                        let rawD = await resD.text();
                        powerDisplay.innerText = "Dim: " + Math.round(parseFloat(rawD)) + "%";
                        powerDisplay.style.color = ""; // Standardfarbe für Dimmer
                    }
                } catch (e) { console.error("Dimmer-Update Fehler", e); }
            }

        } catch (e) {
            valDisplay.innerText = "OFFLINE";
            valDisplay.style.color = "red";
        }
    }
}

function updateClock() {
    const now = new Date();
    const c = document.getElementById('clock');
    const d = document.getElementById('date');
    if(c) c.innerText = now.toLocaleTimeString('de-DE');
    if(d) d.innerText = now.toLocaleDateString('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' });
}

// --- KALENDER LOGIK ---

// Umbenannt von updateCalendarPreview zu updateCalendar
async function updateCalendar() {
    const preview = document.getElementById('cal-preview');
    if (!preview) return;

    try {
        const response = await fetch(`${window.ioURL}/getPlainValue/ical.0.data.count`);
        if (response.ok) {
            const rawCount = await response.text();
            // Entfernt eventuelle Anführungszeichen und Leerzeichen
            const countStr = rawCount.replace(/"/g, "").trim();
            const count = parseInt(countStr, 10);

            // Prüfen, ob die Zahl gültig ist und ob Termine anstehen
            if (!isNaN(count) && count > 0) {
                preview.innerText = count + (count === 1 ? " Termin heute" : " Termine heute");
            } else {
                // Keine Termine -> Saubere Anzeige statt Fehler
                preview.innerText = "Keine Termine heute";
            }
        } else {
            preview.innerText = "Keine Daten";
        }
    } catch (e) {  
        console.error("Kalender Fehler:", e);
        preview.innerText = "Keine Daten";  
    }
}

async function openCalendar() {
    const modal = document.getElementById('calendar-modal');
    const content = document.getElementById('calendar-full-content');
    
    if (!modal) return;
    modal.style.display = 'block';
    if (content) content.innerHTML = "Lade Daten...";

    try {
        const response = await fetch(`${window.ioURL}/getPlainValue/ical.0.data.html`);
        if (response.ok) {
            let html = await response.text();
            
            // Bereinigung für die Anzeige
            html = html.replace(/\\n/g, '<br>').replace(/"/g, '').trim();
            
            // Falls der HTML-Inhalt komplett leer oder ein leeres Objekt ist
            if (!html || html === "" || html === "null") {
                if (content) content.innerHTML = "<div style='color: #666; text-align: center; padding: 20px;'>Aktuell keine anstehenden Termine eingetragen.</div>";
            } else {
                if (content) content.innerHTML = html;
            }
        } else {
            if (content) content.innerHTML = "Fehler beim Laden der Termine.";
        }
    } catch (e) {
        console.error("Fehler beim Öffnen des Kalenders:", e);
        if (content) content.innerHTML = "Fehler beim Laden der Termine.";
    }
}

function closeCalendar() {
    const modal = document.getElementById('calendar-modal');
    if (modal) modal.style.display = 'none';
}
// --- AUFRUFE AM ENDE DER DATEI ---

// Das muss jetzt zum Namen oben passen:
updateCalendar(); 
setInterval(updateCalendar, 900000); // Alle 15 Minuten

window.showWarning = async function(btn) {
    // Holt primär status-id, falls leer oder "-", wird die Haupt-id (io-id) genutzt!
    let sid = btn.getAttribute('data-status-id');
    if (!sid || sid === "" || sid === "-") {
        sid = btn.getAttribute('data-io-id');
    }
    
    const modal = document.getElementById('ctrlModal');
    const title = document.getElementById('modalTitle');
    const mediaBox = document.getElementById('mediaBox');
    
    if (!sid) {
        alert("Fehler: Kein Datenpunkt für diese Kachel hinterlegt!");
        return;
    }

    // UI vorbereiten
    title.innerText = "⚠️ Wetterwarnung";
    mediaBox.innerHTML = "<div style='color:var(--accent); padding:20px;'>Lade Details...</div>";
    modal.style.display = 'flex';

    // Verstecke Dimmer/Farbe und RESET-Button für die Warnung
    if(document.getElementById('dimmerSection')) document.getElementById('dimmerSection').style.display = 'none';
    if(document.getElementById('colorSection')) document.getElementById('colorSection').style.display = 'none';
    const resetBtn = modal.querySelector("button[onclick='resetDevice()']");
    if(resetBtn) resetBtn.style.display = 'none';

    try {
        const res = await fetch(`${window.ioURL}/getPlainValue/${sid}`);
        if (!res.ok) throw new Error("Fehler beim Abruf");
        let raw = await res.text();

        // 1. Bereinigen: Entferne Anführungszeichen am Anfang/Ende und Backslashes
        raw = raw.trim().replace(/^"/, "").replace(/"$/, "").replace(/\\"/g, '"');

        try {
            // 2. In ein Objekt umwandeln
            const data = JSON.parse(raw);
            const warn = Array.isArray(data) ? data[0] : data;

            // 3. Schönes HTML Layout bauen
            mediaBox.innerHTML = `
                <div style="text-align:left; background:rgba(255,255,255,0.05); padding:20px; border-radius:10px; border:1px solid #444; line-height:1.5;">
                    <div style="color:#FEFE04; font-weight:bold; font-size:1.3em; margin-bottom:10px; border-bottom:1px solid #555; padding-bottom:5px;">
                        ${warn.Event || 'WARNUNG'}
                    </div>

                    <div style="color:#eee; margin-bottom:15px; font-size:1.1em; max-height: 220px; overflow-y: auto;">
                        ${warn.Description || 'Keine nähere Beschreibung verfügbar.'}
                    </div>

                    <div style="display:grid; grid-template-columns: 80px 1fr; gap:5px; font-size:0.9em; color:#999;">
                        <span>📅 Zeit:</span> <span style="color:#ccc;">${warn.Effective || '--:--'}</span>
                        <span>📊 Stufe:</span> <span style="color:#ccc;">Level ${warn.Level || 'Unbekannt'}</span>
                        <span>ℹ️ Typ:</span> <span style="color:#ccc;">${warn.AlarmType || 'Wetter'}</span>
                    </div>
                </div>
            `;
        } catch (parseErr) {
            // Fallback: Falls JSON-Parsing fehlschlägt, zeige den bereinigten Roh-Text
            mediaBox.innerHTML = `<div style="text-align:left; color:#ccc; padding:10px;">${raw}</div>`;
        }
    } catch (err) {
        mediaBox.innerHTML = "<div style='color:red;'>Verbindungsfehler zum ioBroker oder Datenpunkt leer.</div>";
    }
};

// --- 4. START ---
setInterval(updateAll, 10000);
setInterval(updateClock, 1000);
updateAll();
updateClock();
