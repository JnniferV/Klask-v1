// empêche la double soumission (double-tap mobile, réseau lent)
const form = document.querySelector("form");

form?.addEventListener("submit", () => {
    form.querySelector('[type="submit"]').disabled = true;
});
