// scanner QR élève, html5-qrcode chargé au 1er clic (~350 Ko hors du chargement de la carte)
const cfg = JSON.parse(document.getElementById("scan-config").textContent);
const btn = document.getElementById("btn-scan");

let scanner = null;
let libLoaded = null;

const loadLib = () =>
    (libLoaded ??= new Promise((resolve, reject) => {
        const s = document.createElement("script");
        s.src = cfg.libUrl;
        s.onload = resolve;
        s.onerror = reject;
        document.head.appendChild(s);
    }));

btn?.addEventListener("click", openScanner);

async function openScanner() {
    if (scanner) return;

    const overlay = document.createElement("div");
    overlay.id = "scan-overlay";
    overlay.innerHTML =
        '<div class="scan-box">' +
        '<button id="scan-close" class="btn-scan-close" aria-label="Fermer">✕</button>' +
        '<div id="scan-reader"></div>' +
        '<p id="scan-msg" class="scan-msg">Pointez sur le QR Code…</p>' +
        "</div>";
    document.body.appendChild(overlay);
    document
        .getElementById("scan-close")
        .addEventListener("click", closeScanner);

    try {
        await loadLib();
    } catch {
        setMsg(
            "Scanner indisponible. Utilise l'appareil photo natif pour scanner le QR.",
        );
        return;
    }

    scanner = new Html5Qrcode("scan-reader");
    try {
        await scanner.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: { width: 220, height: 220 } },
            (rawValue) => {
                scanner.pause();
                processToken(rawValue);
            },
            () => {},
        );
    } catch {
        setMsg(
            "Accès caméra refusé. Utilise l'appareil photo natif pour scanner le QR.",
        );
    }
}

function extractToken(raw) {
    try {
        const match = new URL(raw).pathname.match(/\/scan\/qr\/([^/]+)/);
        if (match) return match[1];
    } catch {}
    return raw;
}

const QUEUE_KEY = "klask:scan-queue";

function readQueue() {
    try {
        return JSON.parse(localStorage.getItem(QUEUE_KEY) || "[]");
    } catch {
        return [];
    }
}

function writeQueue(q) {
    try {
        localStorage.setItem(QUEUE_KEY, JSON.stringify(q));
    } catch {}
}

function queueScan(token) {
    const q = readQueue();
    if (!q.some((e) => e.token === token)) writeQueue([...q, { token }]);
}

async function submitScan(token) {
    const res = await fetch(cfg.scanUrl, {
        method: "POST",
        headers: {
            "X-CSRF-Token": cfg.csrfToken,
            "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({ token }),
    });
    return res.json();
}

async function flushQueue() {
    const q = readQueue();
    if (!q.length) return;
    const remaining = [];
    for (const { token } of q) {
        try {
            const data = await submitScan(token);
            if (data.ok) {
                updateScores(data);
                updateParcoursPin(data.activityId);
            }
        } catch {
            remaining.push({ token });
        }
    }
    writeQueue(remaining);
}

window.addEventListener("online", flushQueue);
flushQueue(); // flush éventuels scans en attente au chargement

async function processToken(raw) {
    const token = extractToken(raw);
    setMsg("Validation…");
    try {
        const data = await submitScan(token);
        if (data.ok) {
            setMsg(
                "+" +
                    data.points +
                    " pts — " +
                    data.activityName +
                    (data.bonus ? " 🎉 +" + data.bonus + " pts bonus" : "") +
                    " ✓",
            );
            updateScores(data);
            updateParcoursPin(data.activityId);
            setTimeout(closeScanner, 2200);
        } else {
            setMsg(data.error ?? "Erreur.");
            setTimeout(() => {
                if (scanner) scanner.resume();
            }, 2000);
        }
    } catch {
        // réseau indisponible : mise en file d'attente
        queueScan(token);
        setMsg(
            "Hors-ligne — scan enregistré, sera synchronisé à la reconnexion.",
        );
        setTimeout(closeScanner, 3000);
    }
}

function updateScores(data) {
    const perso = document.getElementById("score-perso");
    const groupe = document.getElementById("score-groupe");
    if (perso && data.userScore !== undefined)
        perso.textContent = data.userScore;
    if (groupe && data.groupScore !== undefined)
        groupe.textContent = data.groupScore;
}

function updateParcoursPin(activityId) {
    // délègue la MAJ DOM + flèche à map.js
    window.dispatchEvent(
        new CustomEvent("klask:pinDone", { detail: { activityId } }),
    );
}

function setMsg(msg) {
    const el = document.getElementById("scan-msg");
    if (el) el.textContent = msg;
}

async function closeScanner() {
    if (scanner) {
        await scanner.stop().catch(() => {});
        scanner.clear();
        scanner = null;
    }
    document.getElementById("scan-overlay")?.remove();
}
