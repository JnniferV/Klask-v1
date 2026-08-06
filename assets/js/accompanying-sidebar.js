// Sidebar accompagnateur : envoi du poke au clic coté student
// les cartes sont rendues côté serveur (Twig)
// config (URLs et jeton CSRF) injectée par le template via #accompanying-config
const cfg = JSON.parse(
    document.getElementById("accompanying-config").textContent,
);
const list = document.getElementById("group-list");

// Score d'un élève mis à jour en direct dès quil scanne
addEventListener("klask:studentScore", ({ detail }) => {
    const score = list?.querySelector(
        `.student-card[data-id="${detail.studentId}"] .student-score`,
    );
    if (score) score.textContent = detail.studentScore + " pts";
});

list?.addEventListener("click", async (e) => {
    const card = e.target.closest(".student-card");
    if (!card) return;
    if (!confirm("Envoyer un signal à " + card.dataset.pseudo + " ?")) return;
    const res = await fetch(cfg.pokeUrl.replace("__ID__", card.dataset.id), {
        method: "POST",
        headers: { "X-CSRF-Token": cfg.csrfToken },
    });
    if (!res.ok)
        alert(
            "Signal non envoyé (erreur " +
                res.status +
                "). Vérifiez la configuration du groupe.",
        );
});
