// validation côté client du questionnaire : chaque note de 1 à 6 es unique
const selects = [...document.querySelectorAll(".rating-select")];
const errorEl = document.getElementById("quiz-error");

function getValues() {
    return selects.map((s) => s.value).filter((v) => v !== "");
}

function isValid() {
    const vals = getValues();
    return vals.length === 6 && new Set(vals).size === 6;
}

function refreshState() {
    const vals = selects.map((s) => s.value);
    const counts = {};
    vals.forEach((v) => {
        if (v) counts[v] = (counts[v] ?? 0) + 1;
    });

    let hasDuplicate = false;
    selects.forEach((s) => {
        const dup = s.value && counts[s.value] > 1;
        s.classList.toggle("duplicate", dup);
        if (dup) hasDuplicate = true;
    });

    errorEl.textContent = hasDuplicate
        ? "Deux affirmations ont la même note — chaque chiffre doit être unique."
        : "";
}

selects.forEach((s) => s.addEventListener("change", refreshState));

const form = document.getElementById("quiz-form");
const dialog = document.getElementById("quiz-confirm");

form.addEventListener("submit", (e) => {
    e.preventDefault();
    if (!isValid()) {
        errorEl.textContent =
            getValues().length < 6
                ? "Note toutes les affirmations avant de valider."
                : "Utilise chaque chiffre de 1 à 6 une seule fois.";
        return;
    }
    dialog.showModal();
});

dialog.addEventListener("close", () => {
    if (dialog.returnValue === "ok") form.submit();
});
