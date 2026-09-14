const apply = (t) => {
    document.body.dataset.theme = t;
    document.cookie = `klask-theme=${t};path=/;max-age=31536000;samesite=lax`;
    dispatchEvent(new Event("klask:theme")); // la carte de /map existe en deux versions
};

addEventListener("click", (e) => {
    if (e.target.closest(".theme-toggle"))
        apply(document.body.dataset.theme === "dark" ? "standard" : "dark");
});

if (
    "serviceWorker" in navigator &&
    !["localhost", "127.0.0.1"].includes(location.hostname)
)
    navigator.serviceWorker.register("/sw.js");
