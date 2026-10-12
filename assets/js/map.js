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
// toutes les activités déjà scannées par l'élève
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
    // libellé à l'opposé des activités pour pas les cacher
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

// place et état d'un pin, création ou direct
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
            plannedClass = " planned-urgent"; // atelier ou conf qui commence très bientôt
        else if (step.step === (PARCOURS.find((p) => p.current)?.step ?? 0) + 1)
            plannedClass = " planned-next";
        else plannedClass = " planned-future";
    }
    btn.className = `activity-pin${activity.isInternship ? " internship" : ""}${plannedClass}`;
    btn.style.setProperty("--c", color);
    applyActivity(btn, activity);
    return btn;
}

// position de chaque activité sur la carte
let coordsMap = new Map();
// activité urgente reçue en direct, atelier ou conf
let urgentActivityId = null;
// dernier scan validé, mis à jour à chaque scan
let lastScannedId = BOOT.scanned?.[0] ?? null;

// bravo si l'élève a tout scanné sur la carte
function congratsIfMaxScore() {
    if (
        !coordsMap.size ||
        // le point pause compte pas, pareil que le serveur
        ![...coordsMap.keys()].every(
            (id) =>
                SCANNED.has(id) ||
                document.getElementById("pin-" + id)?.dataset.category === "Pause",
        )
    )
        return false;
    showBanner(
        "🎉 FÉLICITATIONS — Tu as atteint le score maximal !",
        "event-alert-banner--congrats",
    );
    return true;
}

// on lit les données mises dans la page
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
            // on marque la sphère selon où en est le parcours
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

        // flèche si déjà des scans ou un atelier urgent
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

// flèche de la dernière sphère faite vers la suivante
function renderParcoursPath(coordsMap) {
    const lastDoneIdx = PARCOURS.reduce((acc, p, i) => (p.done ? i : acc), -1);
    const currentIdx = PARCOURS.findIndex((p) => p.current);
    if (currentIdx === -1) return;

    // mode urgent, un atelier ou conf arrive
    const urgentStep =
        urgentActivityId === null
            ? PARCOURS.find((p) => p.urgent && !p.done)
            : null;
    const isUrgent = urgentActivityId !== null || urgentStep != null;
    const toId = urgentActivityId ?? urgentStep?.id ?? PARCOURS[currentIdx].id;
    // on part du dernier scan, sinon de la dernière faite
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

    // on dessine la pointe de la flèche
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

    // on dessine le trait de la flèche
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

// on met à jour où en est le parcours
window.addEventListener("klask:pinDone", ({ detail }) => {
    const { activityId, nextStepId } = detail;
    // le dernier scan sert toujours de départ à la flèche
    lastScannedId = activityId;
    SCANNED.add(activityId);
    if (activityId === urgentActivityId) urgentActivityId = null;

    const entry = PARCOURS_MAP.get(activityId);
    if (entry) {
        entry.done = true;

        // étape donnée par le serveur, sinon la première pas faite
        const newCurrent =
            PARCOURS.find((p) => !p.done && p.id === nextStepId) ??
            PARCOURS.find((p) => !p.done);
        for (const p of PARCOURS) p.current = p === newCurrent;

        // on met à jour le pin qu'on vient de scanner
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

    // on met à jour l'état des sphères
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

    // on redessine la flèche depuis le dernier scan
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

// bannières que l'élève a déjà fermées
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

// petite croix pour fermer une notif
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

// alerte atelier ou conf, ou message envoyé par l'admin
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

// plan clair, sombre ou en mode alerte
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
        .then((list) =>
            list.forEach((d) => (d.poked ? showPokeNotif(d.at) : showEventAlert(d))),
        )
        .catch(() => {});

replayNotifications();

// mises à jour en direct reçues par mercure
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
        // atelier ou conf urgent, toutes les flèches vont dessus
        if (data.activityId && PARCOURS.length > 1) {
            urgentActivityId = data.activityId;
            document.querySelector(".parcours-layer")?.remove();
            renderParcoursPath(coordsMap);
        }
        return;
    }

    if (data.poked) {
        showPokeNotif(data.at);
        return;
    }

    if (data.newStudent) {
        window.dispatchEvent(
            new CustomEvent("klask:newStudent", { detail: data.newStudent }),
        );
        return;
    }

    // total du groupe mis à jour pour tout le monde
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
            BUBBLE.hidden = true; // la bulle parlait peut-être du pin supprimé
            // la flèche visait peut-être ce pin
            if (PARCOURS_MAP.has(data.id)) {
                document.querySelector(".parcours-layer")?.remove();
                renderParcoursPath(coordsMap);
            }
            return;
        }

        // id inconnu on crée, id connu on met à jour
        if (pin) applyActivity(pin, data);
        else OVERLAY.appendChild(createPin(data, data.color));
        coordsMap.set(data.id, {
            x: data.pointXActivity,
            y: data.pointYActivity,
        });
    }
}

// hors canvas, en bas sur mobile, au pin sinon
function positionBubble(pin) {
    BUBBLE.style.cssText = "";
    BUBBLE.hidden = false;
    if (getComputedStyle(BUBBLE).getPropertyValue("--anchored").trim() !== "1")
        return;

    const p = pin.getBoundingClientRect();
    const w = BUBBLE.offsetWidth;
    const h = BUBBLE.offsetHeight;
    // 25px pour la pointe, bien alignée sur le pin
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

// un stand hors des 3 préférées rapporte moins
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

// signal en direct ou au retour, fermé il revient pas
function showPokeNotif(at) {
    const id = "poke-" + at;
    if (!document.getElementById("btn-scan") || dismissed.has(id)) return;
    document.getElementById("poke-notif")?.remove();
    const notif = document.createElement("div");
    notif.id = "poke-notif";
    notif.className = "poke-notif";
    const msg = document.createElement("p");
    msg.className = "poke-notif__msg";
    msg.textContent =
        "Votre accompagnateur a remarqué que vous étiez inactif ou que vous avez scanné trop et trop vite. Si vous avez des questions, n'hésitez pas à venir nous les poser. Team Klask";
    notif.append(closeButton(() => dismiss(notif, id)), msg);
    document.body.appendChild(notif);
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

// déplacer et zoomer la carte avec doigts ou souris
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

    // zone utile du plan, x 18,7-81,2 et y 12,1-96,1
    const PLAN = { x: 0.187, y: 0.121, w: 0.625, h: 0.84 };

    function computeMinScale() {
        if (!canvas.offsetHeight) return 0.3;
        const sx = area.clientWidth / (canvas.offsetWidth * PLAN.w),
            sy = area.clientHeight / (canvas.offsetHeight * PLAN.h);
        // mobile plein écran, dès 768px le plan tient entier
        return area.clientWidth < 768 ? Math.max(sx, sy) : Math.min(sx, sy);
    }

    // limites sur le plan dessiné, pas sur la marge blanche
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
        // centré sur le plan, sans la marge blanche autour
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

    // zoomer avec la molette de la souris
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

    // déplacer la carte en glissant la souris
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

    // déplacer et zoomer avec les doigts sur mobile
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

// on lance tout au chargement de la page
loadMap();

// ouvrir ou fermer le panneau sur le côté
const _sidebar = document.getElementById("sidebar-panel");
const _trigger = document.getElementById("sidebar-trigger-zone");
if (_sidebar && _trigger) {
    _trigger.addEventListener("click", () => {
        const open = _sidebar.classList.toggle("open");
        _trigger.classList.toggle("is-open", open);
    });
}

// la carte est la page d'arrivée de l'élève
if (document.querySelector(".map-wrapper[data-student]")) {
    // on bloque le bouton retour du navigateur
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
