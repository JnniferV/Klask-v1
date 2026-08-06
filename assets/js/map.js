const BOOT = JSON.parse(
    document.getElementById("map-bootstrap")?.textContent || "{}",
);
const OVERLAY = document.getElementById("map-overlays");
const BUBBLE = document.getElementById("activity-bubble");
const BUBNAME = document.getElementById("bubble-name");
const BUBDESC = document.getElementById("bubble-desc");
const BUBWAIT = document.getElementById("bubble-wait");
const TOP_SPHERES = new Set(BOOT.topSpheres ?? []);
const BOTTOM_SPHERES = new Set(BOOT.bottomSpheres ?? []);
const PARCOURS = BOOT.parcours ?? [];
const PARCOURS_MAP = new Map(PARCOURS.map((p) => [p.id, p]));
// toutes les activités scannées, parcours ou non (stands, ateliers, conférences, professions)
const SCANNED = new Set(BOOT.scanned ?? []);

const sphereStyle = (color, s) =>
    `--c:${color};left:${s.centerX}%;top:${s.centerY}%;width:${(s.radius ?? 10) * 2}%;aspect-ratio:1`;

function createSphere(sphere) {
    const el = document.createElement("div");
    el.id = "sphere-" + sphere.id;
    el.dataset.color = sphere.color;
    const cls = TOP_SPHERES.has(sphere.id)
        ? " sphere-zone--priority"
        : BOTTOM_SPHERES.has(sphere.id)
          ? " sphere-zone--muted"
          : "";
    el.className = "sphere-zone" + cls;
    el.dataset.activityIds = sphere.activities.map((a) => a.id).join(",");
    el.style.cssText = sphereStyle(sphere.color, sphere);
    el.innerHTML = `<span class="sphere-label">${sphere.name}</span>`;
    return el;
}

// Position + état d'un pin : partagé entre la création et la mise à jour temps réel
function applyActivity(el, a) {
    el.style.left = a.pointXActivity + "%";
    el.style.top = a.pointYActivity + "%";
    el.dataset.name = a.name;
    el.dataset.desc = a.descriptionActivity ?? "";
    el.dataset.avail = a.isAvailable ? "1" : "0";
    el.dataset.wait = a.waitMinutes ?? "";
    el.dataset.cap = a.capacity ?? "ok";
    el.setAttribute("aria-label", a.name);
    el.classList.toggle("unavailable", !a.isAvailable);
    el.classList.toggle("cap-full", a.capacity === "full");
    el.classList.toggle("cap-almost", a.capacity === "almost");
}

function createPin(activity, color) {
    const btn = document.createElement("button");
    btn.id = "pin-" + activity.id;
    const step = PARCOURS_MAP.get(activity.id);
    let plannedClass = SCANNED.has(activity.id) ? " planned-done" : "";
    if (step && !plannedClass) {
        if (step.current) plannedClass = " planned-current";
        else if (step.urgent)
            plannedClass = " planned-urgent"; // atelier/conf imminent
        else if (step.step === (PARCOURS.find((p) => p.current)?.step ?? 0) + 1)
            plannedClass = " planned-next";
        else plannedClass = " planned-future";
    }
    btn.className = `activity-pin${activity.isInternship ? " internship" : ""}${plannedClass}`;
    btn.style.setProperty("--c", color);
    applyActivity(btn, activity);
    return btn;
}

// coordonnées des activité
let coordsMap = new Map();
// ID de l'activité urgente (atelier/conf) reçue via Mercure
let urgentActivityId = null;
// Id du dernier scan validé, mis à jour à chaque scan
let lastScannedId = BOOT.scanned?.[0] ?? null;

// score max possible
function congratsIfMaxScore() {
    if (
        !coordsMap.size ||
        ![...coordsMap.keys()].every((id) => SCANNED.has(id))
    )
        return false;
    showBanner(
        "🎉 FÉLICITATIONS — Tu as atteint le score maximal !",
        "event-alert-banner--congrats",
    );
    return true;
}

// Lecture du JSON embarqué dans le HTML
function loadMap() {
    try {
        const mapData = BOOT.mapData;
        if (!mapData) throw new Error("Données carte introuvables.");
        const spheres = mapData.spheres ?? [];
        const standalone = mapData.standalone ?? [];
        const frag = document.createDocumentFragment();
        coordsMap = new Map();

        for (const sphere of spheres) {
            const sphereEl = createSphere(sphere);
            // Tagger la zone sphère selon l'état du parcours
            const steps = sphere.activities
                .map((a) => PARCOURS_MAP.get(a.id))
                .filter(Boolean);
            if (steps.some((s) => s.current || s.urgent)) {
                sphereEl.classList.remove(
                    "sphere-zone--priority",
                    "sphere-zone--muted",
                );
                sphereEl.classList.add("sphere-zone--current");
            } else if (steps.length > 0 && steps.every((s) => s.done)) {
                sphereEl.classList.remove(
                    "sphere-zone--priority",
                    "sphere-zone--muted",
                );
                sphereEl.classList.add("sphere-zone--done");
            }
            frag.appendChild(sphereEl);
            for (const act of sphere.activities) {
                frag.appendChild(createPin(act, sphere.color));
                coordsMap.set(act.id, {
                    x: act.pointXActivity,
                    y: act.pointYActivity,
                });
            }
        }
        for (const act of standalone) {
            frag.appendChild(createPin(act, "#6c5ce7"));
            coordsMap.set(act.id, {
                x: act.pointXActivity,
                y: act.pointYActivity,
            });
        }

        document.getElementById("map-loading")?.remove();
        OVERLAY.appendChild(frag);

        // flèche si scans faits OU urgent actif (ex: atelier/conf imminent)
        const hasUrgent = PARCOURS.some((p) => p.urgent && !p.done);
        if (
            (PARCOURS.some((p) => p.done) || hasUrgent) &&
            PARCOURS.length > 1
        ) {
            renderParcoursPath(coordsMap);
        }
        congratsIfMaxScore();
    } catch (err) {
        const el = document.getElementById("map-loading");
        if (el) el.textContent = "Erreur de chargement de la carte.";
    }
}

// flèche de la dernière sphère validée vers la prochaine
function renderParcoursPath(coordsMap) {
    const lastDoneIdx = PARCOURS.reduce((acc, p, i) => (p.done ? i : acc), -1);
    const currentIdx = PARCOURS.findIndex((p) => p.current);
    if (currentIdx === -1) return;

    // Mode urgent
    const urgentStep =
        urgentActivityId === null
            ? PARCOURS.find((p) => p.urgent && !p.done)
            : null;
    const isUrgent = urgentActivityId !== null || urgentStep != null;
    const toId = urgentActivityId ?? urgentStep?.id ?? PARCOURS[currentIdx].id;
    // lastScanned en priorité,sinon lastDone par ordre, sinon current si mode urgent
    const fromId =
        lastScannedId ??
        (lastDoneIdx !== -1
            ? PARCOURS[lastDoneIdx].id
            : isUrgent
              ? PARCOURS[currentIdx].id
              : null);
    if (!fromId) return;

    const from = coordsMap.get(fromId);
    const to = coordsMap.get(toId);
    if (!from || !to || fromId === toId) return;

    const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
    svg.setAttribute("class", "parcours-layer");
    svg.setAttribute("viewBox", "0 0 100 100");
    svg.setAttribute("preserveAspectRatio", "none");

    // pointe de flèche
    const defs = document.createElementNS("http://www.w3.org/2000/svg", "defs");
    const marker = document.createElementNS(
        "http://www.w3.org/2000/svg",
        "marker",
    );
    marker.setAttribute("id", "neon-arrow");
    marker.setAttribute("markerWidth", "6");
    marker.setAttribute("markerHeight", "6");
    marker.setAttribute("refX", "5");
    marker.setAttribute("refY", "3");
    marker.setAttribute("orient", "auto");
    const poly = document.createElementNS(
        "http://www.w3.org/2000/svg",
        "polygon",
    );
    poly.setAttribute("points", "0 0, 6 3, 0 6");
    poly.setAttribute("class", "neon-arrow-head");
    marker.appendChild(poly);
    defs.appendChild(marker);
    svg.appendChild(defs);

    // Ligne de la fleche
    [["neon-glow"], ["neon-beam"]].forEach(([cls]) => {
        const line = document.createElementNS(
            "http://www.w3.org/2000/svg",
            "line",
        );
        line.setAttribute("x1", from.x);
        line.setAttribute("y1", from.y);
        line.setAttribute("x2", to.x);
        line.setAttribute("y2", to.y);
        line.setAttribute("class", cls);
        if (cls === "neon-beam")
            line.setAttribute("marker-end", "url(#neon-arrow)");
        svg.appendChild(line);
    });

    OVERLAY.appendChild(svg);
}

// mise à jour de l'état parcours
window.addEventListener("klask:pinDone", ({ detail: { activityId } }) => {
    // Toujours mémoriser le dernier scan comme début de la flèche
    lastScannedId = activityId;
    SCANNED.add(activityId);
    if (activityId === urgentActivityId) urgentActivityId = null;

    const entry = PARCOURS_MAP.get(activityId);
    if (entry) {
        entry.done = true;
        entry.current = false;

        let newCurrent = null;
        for (const p of PARCOURS) {
            p.current = !p.done && newCurrent === null;
            if (p.current) newCurrent = p;
        }

        // MAJ classes DOM du pin scanné
        const donePin = document.getElementById("pin-" + activityId);
        donePin?.classList.remove(
            "planned-current",
            "planned-next",
            "planned-future",
            "planned-urgent",
        );
        donePin?.classList.add("planned-done");

        if (newCurrent) {
            const currentPin = document.getElementById("pin-" + newCurrent.id);
            currentPin?.classList.remove(
                "planned-next",
                "planned-future",
                "planned-urgent",
            );
            currentPin?.classList.add("planned-current");
        }
    }

    // MAJ classes sphere-zone
    for (const sphereEl of document.querySelectorAll(".sphere-zone")) {
        const ids = sphereEl.dataset.activityIds?.split(",").map(Number) ?? [];
        const steps = ids.map((id) => PARCOURS_MAP.get(id)).filter(Boolean);
        if (!steps.length) continue;
        sphereEl.classList.toggle(
            "sphere-zone--done",
            steps.every((s) => s.done),
        );
        sphereEl.classList.toggle(
            "sphere-zone--current",
            !steps.every((s) => s.done) && steps.some((s) => s.current),
        );
    }

    // Re-render flèche depuis le dernier scan
    document.querySelector(".parcours-layer")?.remove();
    if (PARCOURS.some((p) => p.current)) {
        renderParcoursPath(coordsMap);
    } else if (
        !congratsIfMaxScore() &&
        PARCOURS.length > 0 &&
        PARCOURS.every((p) => p.done)
    ) {
        showBanner(
            "✅ Parcours terminé ! Il te reste des stands, ateliers et conférences à valider.",
            "event-alert-banner--congrats",
        );
    }
});

function showBanner(text, cls = "", ttl = 0) {
    const el = document.createElement("div");
    el.className = "event-alert-banner" + (cls ? " " + cls : "");
    el.textContent = text;
    document.body.appendChild(el);
    if (ttl) setTimeout(() => el.remove(), ttl);
}

// alerte event (Atelier/Conf dans n min) ou notif admin (titre, lien, type)
function showEventAlert(data) {
    if (data.categoryType) {
        showBanner(
            `⚡ ${data.categoryType} « ${data.activityName} » dans ${data.minutesBefore} min`,
            "",
            8000,
        );
        return;
    }
    const el = document.createElement("div");
    el.className = "event-alert-banner notif-" + (data.type ?? "info");
    if (data.color) el.style.background = data.color;
    if (data.title)
        el.append(
            Object.assign(document.createElement("strong"), {
                textContent: data.title,
            }),
        );
    if (data.message) el.append(data.title ? " — " : "", data.message);
    if (data.link)
        el.append(
            " ",
            Object.assign(document.createElement("a"), {
                href: data.link,
                textContent: "En savoir plus",
                target: "_blank",
                rel: "noopener",
            }),
        );
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 10000);
}

function setAlertMap(active) {
    const img = document.getElementById("map-plan");
    if (img) {
        const next = active ? img.dataset.srcAlert : img.dataset.srcNormal;
        if (img.getAttribute("src") !== next) {
            img.onload = () => {
                window.dispatchEvent(new Event("klask:mapRefit"));
                img.onload = null;
            };
            img.src = next;
        }
    }
    OVERLAY.hidden = active;
    document.getElementById("btn-scan")?.toggleAttribute("hidden", active);
    if (active) showBanner("🚨 Mode alerte — sorties de secours", "", 0);
    else document.querySelector(".event-alert-banner")?.remove();
}

if (BOOT.alertMapActive) setAlertMap(true);

// Notif publiée avant l'ouverture de la page (Mercure ne la rejoue pas)
if (BOOT.notification) showEventAlert(BOOT.notification);

// Mise à jour temps réel via Mercure
function handleMapUpdate(e) {
    const data = JSON.parse(e.data);

    if (data.type === "alert-map") {
        setAlertMap(data.active);
        return;
    }

    if (data.alert) {
        showEventAlert(data);
        // alerte atelier/conf avec activityId → forcer toutes les flèches vers cet événement
        if (data.activityId && PARCOURS.length > 1) {
            urgentActivityId = data.activityId;
            document.querySelector(".parcours-layer")?.remove();
            renderParcoursPath(coordsMap);
        }
        return;
    }

    if (data.poked) {
        window.dispatchEvent(new Event("klask:poke"));
        return;
    }

    if (data.studentScore !== undefined) {
        window.dispatchEvent(
            new CustomEvent("klask:studentScore", { detail: data }),
        );
        return;
    }

    if (data.type === "sphere") {
        const el = document.getElementById("sphere-" + data.id);
        if (!el) return;
        if (data.name)
            el.querySelector(".sphere-label").textContent = data.name;
        if (data.color) el.dataset.color = data.color;
        if (data.centerX !== undefined)
            el.style.cssText = sphereStyle(el.dataset.color, data);
        el.style.setProperty("--c", el.dataset.color);
        return;
    }

    if (data.type === "activity") {
        // create = nouveau pin ou move/update = même payload complet
        if (data.action === "create") {
            OVERLAY.appendChild(createPin(data, data.color));
        } else {
            const pin = document.getElementById("pin-" + data.id);
            if (pin) applyActivity(pin, data);
        }
        coordsMap.set(data.id, {
            x: data.pointXActivity,
            y: data.pointYActivity,
        });
    }
}

// Bulle d'activité -> positionnée à côté du pin cliqué
function positionBubble(pin) {
    // Coordonnées locales du canvas (indépendantes du zoom/pan)
    const cW = OVERLAY.offsetWidth;
    const cH = OVERLAY.offsetHeight;
    const pinLeft = (parseFloat(pin.style.left) / 100) * cW;
    const pinTop = (parseFloat(pin.style.top) / 100) * cH;
    const pinHalf = pin.offsetWidth / 2;

    // Rendre visible hors-écran pour mesurer la bulle
    BUBBLE.style.cssText = "left:-9999px;top:0;transform:none;bottom:auto";
    BUBBLE.hidden = false;

    const bubW = BUBBLE.offsetWidth;
    const bubH = BUBBLE.offsetHeight;

    let left = pinLeft + pinHalf + 10;
    if (left + bubW > cW) left = pinLeft - pinHalf - bubW - 10;

    let top = pinTop - bubH / 2;
    top = Math.max(4, Math.min(top, cH - bubH - 4));

    BUBBLE.style.cssText = `left:${left}px;top:${top}px;transform:none;bottom:auto`;
}

OVERLAY.addEventListener("click", (e) => {
    const pin = e.target.closest(".activity-pin");
    if (!pin) return;

    e.stopPropagation();

    BUBNAME.textContent = pin.dataset.name;
    BUBDESC.textContent = pin.dataset.desc;
    BUBWAIT.textContent =
        pin.dataset.avail !== "1"
            ? "Stand indisponible pour le moment."
            : pin.dataset.cap === "full"
              ? "Stand complet — repasse un peu plus tard."
              : (pin.dataset.cap === "almost" ? "Presque plein. " : "") +
                (pin.dataset.wait
                    ? `Temps d'attente estimé : ${pin.dataset.wait} min`
                    : "");

    positionBubble(pin);
});

document.getElementById("btn-close-bubble")?.addEventListener("click", (e) => {
    e.stopPropagation();
    BUBBLE.hidden = true;
});

document.addEventListener("click", () => {
    BUBBLE.hidden = true;
});

// Instructions — chargées à la demande (intro 1re visite ou clic ?)
function loadInstructionImage(container) {
    if (!container || container.querySelector(".intro-img")) return;
    const raw = document.getElementById("instruction-image-url");
    if (!raw) return;
    const url = JSON.parse(raw.textContent);
    if (!url) return;
    const img = document.createElement("img");
    img.className = "intro-img";
    img.alt = "Instructions";
    img.decoding = "async";
    img.src = url;
    container.appendChild(img);
}

loadInstructionImage(
    document.querySelector("#intro-overlay [data-instruction-scroll]"),
);

const helpModal = document.getElementById("help-modal");

document.getElementById("btn-help")?.addEventListener("click", () => {
    loadInstructionImage(helpModal?.querySelector("[data-instruction-scroll]"));
    helpModal.hidden = false;
});

helpModal?.addEventListener("click", (e) => {
    if (e.target === helpModal || e.target.closest(".intro-close"))
        helpModal.hidden = true;
});

document
    .querySelector("[data-intro-ack]")
    ?.addEventListener("click", function () {
        fetch(this.dataset.introAck, {
            method: "POST",
            credentials: "same-origin",
        }).then((r) => {
            if (r.ok) document.getElementById("intro-overlay")?.remove();
        });
    });

// Pan + Zoom
(function initPanZoom() {
    const area = document.querySelector(".map-area");
    const canvas = document.querySelector(".map-canvas");
    const img = document.getElementById("map-plan");
    if (!area || !canvas || !img) return;

    let scale = 1,
        tx = 0,
        ty = 0;
    let SCALE_MIN = 0.3,
        SCALE_MAX = 4;
    let dragging = false,
        startX = 0,
        startY = 0,
        originTx = 0,
        originTy = 0;
    let lastDist = null,
        lastMidX = 0,
        lastMidY = 0,
        touchOriginTx = 0,
        touchOriginTy = 0;

    function computeMinScale() {
        if (!canvas.offsetHeight) return 0.3;
        return Math.min(
            area.clientWidth / canvas.offsetWidth,
            area.clientHeight / canvas.offsetHeight,
        );
    }

    function clampPan() {
        const w = canvas.offsetWidth * scale,
            h = canvas.offsetHeight * scale;
        tx =
            w <= area.clientWidth
                ? (area.clientWidth - w) / 2
                : Math.min(0, Math.max(area.clientWidth - w, tx));
        ty =
            h <= area.clientHeight
                ? (area.clientHeight - h) / 2
                : Math.min(0, Math.max(area.clientHeight - h, ty));
    }

    function applyTransform() {
        canvas.style.transform = `translate(${tx}px,${ty}px) scale(${scale})`;
    }

    function initFit() {
        SCALE_MIN = computeMinScale();
        scale = SCALE_MIN;
        tx = 0;
        ty = 0;
        clampPan();
        applyTransform();
    }

    img.complete && img.naturalHeight
        ? initFit()
        : img.addEventListener("load", initFit, { once: true });
    window.addEventListener("klask:mapRefit", initFit);

    window.addEventListener("resize", () => {
        SCALE_MIN = computeMinScale();
        scale = Math.max(scale, SCALE_MIN);
        clampPan();
        applyTransform();
    });

    // Zoom molette
    area.addEventListener(
        "wheel",
        (e) => {
            e.preventDefault();
            const rect = area.getBoundingClientRect();
            const mouseX = e.clientX - rect.left,
                mouseY = e.clientY - rect.top;
            const next = Math.min(
                SCALE_MAX,
                Math.max(SCALE_MIN, scale * (e.deltaY < 0 ? 1.1 : 0.9)),
            );
            tx = mouseX - (mouseX - tx) * (next / scale);
            ty = mouseY - (mouseY - ty) * (next / scale);
            scale = next;
            clampPan();
            applyTransform();
        },
        { passive: false },
    );

    // Pan souris
    area.addEventListener("mousedown", (e) => {
        if (
            e.target.closest(
                ".activity-pin, #activity-bubble, .sidebar-container, .btn-help, #help-modal, #btn-scan, #scan-overlay",
            )
        )
            return;
        dragging = true;
        startX = e.clientX;
        startY = e.clientY;
        originTx = tx;
        originTy = ty;
    });
    window.addEventListener("mousemove", (e) => {
        if (!dragging) return;
        tx = originTx + e.clientX - startX;
        ty = originTy + e.clientY - startY;
        clampPan();
        applyTransform();
    });
    window.addEventListener("mouseup", () => {
        dragging = false;
    });

    // Pan + zoom touch (pinch)
    area.addEventListener(
        "touchstart",
        (e) => {
            if (e.touches.length === 1) {
                dragging = true;
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientY;
                originTx = tx;
                originTy = ty;
            } else if (e.touches.length === 2) {
                dragging = false;
                const [a, b] = e.touches;
                lastDist = Math.hypot(
                    b.clientX - a.clientX,
                    b.clientY - a.clientY,
                );
                const rect = area.getBoundingClientRect();
                lastMidX = (a.clientX + b.clientX) / 2 - rect.left;
                lastMidY = (a.clientY + b.clientY) / 2 - rect.top;
                touchOriginTx = tx;
                touchOriginTy = ty;
            }
        },
        { passive: true },
    );

    area.addEventListener(
        "touchmove",
        (e) => {
            e.preventDefault();
            if (e.touches.length === 1 && dragging) {
                tx = originTx + e.touches[0].clientX - startX;
                ty = originTy + e.touches[0].clientY - startY;
                clampPan();
                applyTransform();
            } else if (e.touches.length === 2) {
                const [a, b] = e.touches;
                const dist = Math.hypot(
                    b.clientX - a.clientX,
                    b.clientY - a.clientY,
                );
                const next = Math.min(
                    SCALE_MAX,
                    Math.max(SCALE_MIN, (scale * dist) / lastDist),
                );
                tx = lastMidX - (lastMidX - touchOriginTx) * (next / scale);
                ty = lastMidY - (lastMidY - touchOriginTy) * (next / scale);
                scale = next;
                lastDist = dist;
                clampPan();
                applyTransform();
            }
        },
        { passive: false },
    );

    area.addEventListener("touchend", () => {
        dragging = false;
        lastDist = null;
    });
})();

// init
loadMap();

// Sidebar toggle
const _sidebar = document.getElementById("sidebar-panel");
const _trigger = document.getElementById("sidebar-trigger-zone");
if (_sidebar && _trigger) {
    _trigger.addEventListener("click", () => {
        const open = _sidebar.classList.toggle("open");
        _trigger.classList.toggle("is-open", open);
    });
}

// carte est un point d'arrivée
if (document.querySelector(".map-wrapper[data-student]")) {
    // retour arrière pas possible
    history.pushState(null, "", location.href);
    addEventListener("popstate", () =>
        history.pushState(null, "", location.href),
    );

    const header = document.querySelector(".site-header");
    let startY = 0;
    addEventListener(
        "touchstart",
        (e) => {
            startY = e.touches[0].clientY;
        },
        { passive: true },
    );
    addEventListener(
        "touchend",
        (e) => {
            const dy = e.changedTouches[0].clientY - startY;
            if (dy > 40 && startY < 50)
                header?.classList.add("header--visible");
            else if (dy < -40) header?.classList.remove("header--visible");
        },
        { passive: true },
    );
}

// Pour mercure et reco forcée
(window.requestIdleCallback ?? ((fn) => setTimeout(fn, 0)))(
    () => {
        if (!BOOT.mercureUrl) return;

        const STALE_AFTER_MS = 45000;
        let es, lastActivity;

        function connect() {
            try {
                es?.close();
                lastActivity = Date.now();
                es = new EventSource(BOOT.mercureUrl);
                es.onopen = () => {
                    lastActivity = Date.now();
                };
                es.addEventListener("message", (e) => {
                    lastActivity = Date.now();
                    handleMapUpdate(e);
                });
                es.onerror = () =>
                    console.warn("Mercure: reconnexion automatique…");
            } catch (err) {
                console.warn("Mercure:", err);
            }
        }

        connect();
        setInterval(() => {
            if (Date.now() - lastActivity > STALE_AFTER_MS) connect();
        }, 15000);
    },
    { timeout: 2000 },
);
