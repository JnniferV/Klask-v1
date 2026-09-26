const BOOT = JSON.parse(
    document.getElementById("map-bootstrap")?.textContent || "{}",
);
const OVERLAY = document.getElementById("map-overlays");
const BUBBLE = document.getElementById("activity-bubble");
const BUBNAME = document.getElementById("bubble-name");
const BUBDESC = document.getElementById("bubble-desc");
const BUBWAIT = document.getElementById("bubble-wait");
const BUBPTS = document.getElementById("bubble-points");
const TOP_SPHERES = new Set(BOOT.topSpheres ?? []);
const BOTTOM_SPHERES = new Set(BOOT.bottomSpheres ?? []);
const PARCOURS = BOOT.parcours ?? [];
const PARCOURS_MAP = new Map(PARCOURS.map((p) => [p.id, p]));
// toutes les activités scannées
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
    // le libellé fuit les activités : elles en bas → libellé en haut, et inversement
    const ys = sphere.activities.map((a) => a.pointYActivity);
    if (ys.length) {
        const bas = ys.reduce((s, y) => s + y, 0) / ys.length > sphere.centerY;
        el.classList.add(bas ? "sphere-label--haut" : "sphere-label--bas");
    }
    el.append(
        Object.assign(document.createElement("span"), {
            className: "sphere-label",
            textContent: sphere.name,
        }),
    );
    return el;
}

// position + état d'un pin : partagé entre la création et la mise à jour temps réel
function applyActivity(el, a) {
    el.style.left = a.pointXActivity + "%";
    el.style.top = a.pointYActivity + "%";
    el.dataset.name = a.name;
    el.dataset.desc = a.descriptionActivity ?? "";
    el.dataset.avail = a.isAvailable ? "1" : "0";
    el.dataset.wait = a.waitMinutes ?? "";
    el.dataset.cap = a.capacity ?? "ok";
    el.dataset.sphereId = a.sphereId ?? "";
    el.dataset.category = a.categoryType ?? "";
    el.dataset.basePoints = a.basePoints ?? 0;
    el.setAttribute("aria-label", a.name);
    el.classList.toggle("unavailable", !a.isAvailable);
    // halo allumé à la main depuis l'admin
    el.classList.toggle("highlighted", !!a.isHighlighted);
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
// id du dernier scan validé mis à jour à chaque scan
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

// lecture du JSON embarqué dans le HTML
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
            // tagger la zone sphère selon l'état du parcours
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

// flèche de la dernière sphère validé vers la prochaine
function renderParcoursPath(coordsMap) {
    const lastDoneIdx = PARCOURS.reduce((acc, p, i) => (p.done ? i : acc), -1);
    const currentIdx = PARCOURS.findIndex((p) => p.current);
    if (currentIdx === -1) return;

    // mode urgent
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

    // ligne de la fleche
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
window.addEventListener("klask:pinDone", ({ detail }) => {
    const { activityId, nextStepId } = detail;
    // toujours mémoriser le dernier scan comme début de la flèche
    lastScannedId = activityId;
    SCANNED.add(activityId);
    if (activityId === urgentActivityId) urgentActivityId = null;

    const entry = PARCOURS_MAP.get(activityId);
    if (entry) {
        entry.done = true;

        // le serveur donne l'étape suivante, sinon la première non faite
        const newCurrent =
            PARCOURS.find((p) => !p.done && p.id === nextStepId) ??
            PARCOURS.find((p) => !p.done);
        for (const p of PARCOURS) p.current = p === newCurrent;

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

    // re-render flèche depuis le dernier scan
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

const ALERT_BANNER_ID = "alert-map-banner";

// bannières déjà fermées
const DISMISSED_KEY = "klask:notifs-fermees";
let dismissed = new Set();
try {
    dismissed = new Set(JSON.parse(localStorage.getItem(DISMISSED_KEY)) ?? []);
} catch {}

function dismiss(el, id) {
    el.remove();
    if (!id) return;
    dismissed.add(id);
    try {
        localStorage.setItem(
            DISMISSED_KEY,
            JSON.stringify([...dismissed].slice(-50)),
        );
    } catch {}
}

// croix de fermeture pour notif
function closeButton(onClose) {
    const b = Object.assign(document.createElement("button"), {
        type: "button",
        className: "notif-close",
        textContent: "✕",
    });
    b.setAttribute("aria-label", "Fermer");
    b.addEventListener("click", onClose);
    return b;
}

function showBanner(text, cls = "", id = "") {
    const el = document.createElement("div");
    el.className = "event-alert-banner" + (cls ? " " + cls : "");
    el.textContent = text;
    if (id) el.id = id;
    else el.prepend(closeButton(() => el.remove()));
    document.body.appendChild(el);
}

// alerte event (Atelier/Conf dans n min) ou notif admin (titre, lien, type)
function showEventAlert(data) {
    if (data.categoryType) {
        showBanner(
            `⚡ ${data.categoryType} « ${data.activityName} » dans ${data.minutesBefore} min`,
        );
        return;
    }
    const domId = data.id ? "notif-" + data.id : "";
    if (domId && (dismissed.has(data.id) || document.getElementById(domId)))
        return;

    const el = document.createElement("div");
    el.className =
        "event-alert-banner event-alert-banner--centered notif-" +
        (data.type ?? "info");
    if (domId) el.id = domId;
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
    el.prepend(closeButton(() => dismiss(el, data.id)));
    document.body.appendChild(el);
}

let alertActive = false;

// la carte existe en thème clair/sombre et mode alerte
function applyPlan() {
    const img = document.getElementById("map-plan");
    const theme = document.body.dataset.theme === "dark" ? "dark" : "light";
    const next = img?.dataset[theme + (alertActive ? "Alert" : "")];
    if (!next || img.getAttribute("src") === next) return;
    img.onload = () => {
        window.dispatchEvent(new Event("klask:mapRefit"));
        img.onload = null;
    };
    img.src = next;
}

function setAlertMap(active) {
    alertActive = active;
    applyPlan();
    OVERLAY.hidden = active;
    document.getElementById("btn-scan")?.toggleAttribute("hidden", active);

    document.getElementById(ALERT_BANNER_ID)?.remove();
    if (active)
        showBanner("🚨 Mode alerte — sorties de secours", "", ALERT_BANNER_ID);
}

setAlertMap(BOOT.alertMapActive);
addEventListener("klask:theme", applyPlan);

const replayNotifications = () =>
    fetch(BOOT.notificationsUrl, { headers: { Accept: "application/json" } })
        .then((r) => (r.ok ? r.json() : []))
        .then((list) => list.forEach(showEventAlert))
        .catch(() => {});

replayNotifications();

// mise à jour temps réel via Mercure
function handleMapUpdate(e) {
    let data;
    try {
        data = JSON.parse(e.data);
    } catch {
        return;
    }

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
        showPokeNotif();
        return;
    }

    if (data.newStudent) {
        window.dispatchEvent(
            new CustomEvent("klask:newStudent", { detail: data.newStudent }),
        );
        return;
    }

    // total du groupe avec scan d'un coéquipier MàJ la sidebar de tout le groupe
    if (data.groupScore !== undefined) {
        const el = document.getElementById("score-groupe");
        if (el) el.textContent = data.groupScore;
        return;
    }

    if (
        data.studentScore !== undefined ||
        data.blocked !== undefined ||
        data.reason
    ) {
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
        const pin = document.getElementById("pin-" + data.id);

        if (data.action === "delete") {
            pin?.remove();
            coordsMap.delete(data.id);
            BUBBLE.hidden = true; // la bulle peut décrire le pin supprimé
            // la flèche pointait peut-être dessus
            if (PARCOURS_MAP.has(data.id)) {
                document.querySelector(".parcours-layer")?.remove();
                renderParcoursPath(coordsMap);
            }
            return;
        }

        // id inconnu : création, id connu : mise à jour
        if (pin) applyActivity(pin, data);
        else OVERLAY.appendChild(createPin(data, data.color));
        coordsMap.set(data.id, {
            x: data.pointXActivity,
            y: data.pointYActivity,
        });
    }
}

// hors de .map-canvas : son transform ferait viser le canvas et non l'écran
// mobile : bandeau bas en CSS, desktop : ancrée au pin en coordonnées écran
function positionBubble(pin) {
    BUBBLE.style.cssText = "";
    BUBBLE.hidden = false;
    if (getComputedStyle(BUBBLE).getPropertyValue("--anchored").trim() !== "1")
        return;

    const p = pin.getBoundingClientRect();
    const w = BUBBLE.offsetWidth;
    const h = BUBBLE.offsetHeight;
    // 25px = pointe de la flèche, alignée sur le pin
    const left = Math.min(
        Math.max(8, p.left + p.width / 2 - 25),
        innerWidth - w - 8,
    );
    const above = p.top - h - 12;
    const top = Math.min(
        Math.max(8, above >= 8 ? above : p.bottom + 12),
        innerHeight - h - 8,
    );

    BUBBLE.style.cssText = `left:${left}px;top:${top}px`;
}

//un stand hors des 3 sphères préférées rapporte moins
function pinPoints(pin) {
    const sphereId = +pin.dataset.sphereId;
    return pin.dataset.category === "Stand" &&
        sphereId &&
        !TOP_SPHERES.has(sphereId)
        ? BOOT.pointsOutsideTop3
        : +pin.dataset.basePoints || 0;
}

OVERLAY.addEventListener("click", (e) => {
    const pin = e.target.closest(".activity-pin");
    if (!pin) return;

    e.stopPropagation();

    BUBNAME.textContent = pin.dataset.name;
    BUBDESC.textContent = pin.dataset.desc;
    if (BUBPTS) {
        const pts = pinPoints(pin);
        BUBPTS.textContent = pts ? pts + " pts" : "";
        BUBPTS.hidden = !pts;
    }
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

BUBBLE.addEventListener("click", (e) => e.stopPropagation());

document.getElementById("btn-close-bubble")?.addEventListener("click", (e) => {
    e.stopPropagation();
    BUBBLE.hidden = true;
});

document.addEventListener("click", () => {
    BUBBLE.hidden = true;
});

function showPokeNotif() {
    if (!document.getElementById("btn-scan")) return;
    let notif = document.getElementById("poke-notif");
    if (!notif) {
        notif = document.createElement("div");
        notif.id = "poke-notif";
        notif.className = "poke-notif";
        const close = closeButton(() => (notif.hidden = true));
        const msg = document.createElement("p");
        msg.className = "poke-notif__msg";
        msg.textContent =
            "Votre accompagnateur a remarqué que vous étiez inactif ou que vous avez scanné trop et trop vite. Si vous avez des questions, n'hésitez pas à venir nous les poser. Team Klask";
        notif.append(close, msg);
        document.body.appendChild(notif);
    }
    notif.hidden = false;
}

const helpModal = document.getElementById("help-modal");

document.getElementById("btn-help")?.addEventListener("click", () => {
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

// pan + Zoom
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

    // cadre réellement dessiné dans le webp du plan : le reste est de la marge blanche
    // (mesuré sur `cartes claire.webp` — x 18,7-81,2 %, y 12,1-96,1 %)
    // le bas descend jusqu'à 96 % : les flèches ENTRÉE y sont, elles étaient rognées
    const PLAN = { x: 0.187, y: 0.121, w: 0.625, h: 0.84 };

    function computeMinScale() {
        if (!canvas.offsetHeight) return 0.3;
        const sx = area.clientWidth / (canvas.offsetWidth * PLAN.w),
            sy = area.clientHeight / (canvas.offsetHeight * PLAN.h);
        // mobile : le plan remplit l'écran (aucun vide) ; à partir de 768px il tient en entier,
        // l'écran étant paysage et le plan portrait
        return area.clientWidth < 768 ? Math.max(sx, sy) : Math.min(sx, sy);
    }

    // bornes calculées sur le cadre dessiné, pas sur le webp : sinon on peut se déplacer
    // dans la marge blanche, très visible sur mobile où le plan déborde de l'écran
    function clampPan() {
        const w = canvas.offsetWidth * PLAN.w * scale,
            h = canvas.offsetHeight * PLAN.h * scale,
            ox = canvas.offsetWidth * PLAN.x * scale,
            oy = canvas.offsetHeight * PLAN.y * scale;
        tx =
            w <= area.clientWidth
                ? (area.clientWidth - w) / 2 - ox
                : Math.min(-ox, Math.max(area.clientWidth - w - ox, tx));
        ty =
            h <= area.clientHeight
                ? (area.clientHeight - h) / 2 - oy
                : Math.min(-oy, Math.max(area.clientHeight - h - oy, ty));
    }

    function applyTransform() {
        canvas.style.transform = `translate(${tx}px,${ty}px) scale(${scale})`;
    }

    function initFit() {
        SCALE_MIN = computeMinScale();
        scale = SCALE_MIN;
        // centré sur le plan, pas sur l'image : sinon la marge blanche entre dans le cadrage
        tx = area.clientWidth / 2 - (PLAN.x + PLAN.w / 2) * canvas.offsetWidth * scale;
        ty = area.clientHeight / 2 - (PLAN.y + PLAN.h / 2) * canvas.offsetHeight * scale;
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

    // zoom molette
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

    // pan souris
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

    // pan + zoom touch (pinch)
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

// sidebar toggle
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

if (BOOT.mercureUrl) {
    let es;

    function connect() {
        try {
            es?.close();
            es = new EventSource(BOOT.mercureUrl, { withCredentials: true });
            es.addEventListener("message", handleMapUpdate);
            es.onerror = () =>
                console.warn("Mercure: reconnexion automatique…");
        } catch (err) {
            console.warn("Mercure:", err);
        }
    }

    connect();

    setInterval(() => {
        if (es?.readyState === EventSource.CLOSED) connect();
    }, 15000);

    addEventListener("visibilitychange", () => {
        if (document.hidden) return;
        if (es?.readyState === EventSource.CLOSED) connect();
        replayNotifications();
    });
}
