// sidebar accompagnateur : poke + scores + alertes (blocage / scan trop rapide / inactivité)
const cfg = JSON.parse(
    document.getElementById("accompanying-config").textContent,
);
const list = document.getElementById("group-list");
const inactivityMin = cfg.inactivityMin ?? 25;
const INACTIVE = ` est inactif depuis ${inactivityMin} min`;
const toasted = new Set();

function toast(msg, cls = "") {
    const el = document.createElement("div");
    el.className = "event-alert-banner" + (cls ? " " + cls : "");
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 8000);
}

function flagCard(card, on, reason) {
    card.classList.toggle("student-card--alert", on);
    if (!on || toasted.has(card.dataset.id + reason)) return;
    toasted.add(card.dataset.id + reason);
    toast((card.dataset.pseudo || "Un élève") + reason);
}

addEventListener("klask:studentScore", ({ detail }) => {
    const card = list?.querySelector(
        `.student-card[data-id="${detail.studentId}"]`,
    );
    if (!card) return;
    if (detail.studentScore !== undefined) {
        const score = card.querySelector(".student-score");
        if (score) score.textContent = detail.studentScore + " pts";
        card.dataset.lastScan = String(Math.floor(Date.now() / 1000));
        toasted.delete(card.dataset.id + INACTIVE);
        if (!detail.blocked) card.classList.remove("student-card--alert");
    }
    if (detail.blocked) flagCard(card, true, " est bloqué temporairement");
    else if (detail.reason === "fast_scan")
        flagCard(card, true, " a scanné trop vite");
});

// nouvel inscrit : lastScan= compteur d'inactivité démarre à l'arrivé
addEventListener("klask:newStudent", ({ detail }) => {
    if (!list || list.querySelector(`.student-card[data-id="${detail.id}"]`))
        return;
    list.querySelector(".group-loading")?.remove();
    const card = document.createElement("div");
    card.className = "student-card";
    Object.assign(card.dataset, {
        id: detail.id,
        pseudo: detail.pseudo,
        lastScan: Math.floor(Date.now() / 1000),
    });
    card.innerHTML =
        '<span class="avatar avatar--sm"><img alt="" loading="lazy" decoding="async"></span>' +
        '<span class="student-pseudo"></span><span class="student-score"></span>';
    card.querySelector("img").src = detail.avatar;
    card.querySelector(".student-pseudo").textContent = detail.pseudo;
    card.querySelector(".student-score").textContent = detail.score + " pts";
    list.append(card);

    const counter = document.getElementById("students-count");
    if (counter) counter.textContent = String(+counter.textContent + 1);
});

function checkInactive() {
    const limit = Date.now() / 1000 - inactivityMin * 60;
    list?.querySelectorAll(".student-card[data-last-scan]").forEach((card) => {
        if (+card.dataset.lastScan > limit) return;
        flagCard(card, true, INACTIVE);
    });
}
checkInactive();
setInterval(checkInactive, 60000);
// les navigateurs gèlent les timers d'un onglet en arrière-plan
addEventListener("visibilitychange", () => document.hidden || checkInactive());

const pokeDialog = document.getElementById("poke-confirm");

list?.addEventListener("click", (e) => {
    const card = e.target.closest(".student-card");
    if (!card || !pokeDialog) return;
    pokeDialog.querySelector(".modal-msg").textContent =
        "Envoyer un signal à " + card.dataset.pseudo + " ?";
    pokeDialog.returnValue = "";
    pokeDialog.showModal();
    pokeDialog.addEventListener(
        "close",
        () => pokeDialog.returnValue === "ok" && poke(card),
        { once: true },
    );
});

async function poke(card) {
    let res;
    try {
        res = await fetch(cfg.pokeUrl.replace("__ID__", card.dataset.id), {
            method: "POST",
            headers: { "X-CSRF-Token": cfg.csrfToken },
        });
    } catch {
        // si réseau coupé
        toast("Signal non envoyé — connexion perdue. Réessayez.");
        return;
    }
    // 403 = jeton CSRF périmé
    if (res.status === 403) {
        toast("Session renouvelée — rechargement de la page…");
        setTimeout(() => location.reload(), 1500);
        return;
    }
    if (!res.ok)
        toast(
            "Signal non envoyé (erreur " +
                res.status +
                "). Vérifiez la configuration du groupe.",
        );
    else {
        // centré comme la notif élève
        toast(
            "Signal envoyé à " + card.dataset.pseudo,
            "event-alert-banner--centered",
        );
        // sans ça checkInactive re-signale le même élève 60 sec plus tard
        card.dataset.lastScan = String(Math.floor(Date.now() / 1000));
        card.classList.remove("student-card--alert");
        for (const k of [...toasted]) {
            if (k.startsWith(card.dataset.id)) toasted.delete(k);
        }
    }
}
