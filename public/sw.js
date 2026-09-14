// Service Worker Klask — cache statique runtime
const CACHE = "klask-v6";

// extensions statiques à cacher (même origine)
const STATIC_RE = /\.(js|css|svg|webp|png|woff2|ico)(\?.*)?$/;

self.addEventListener("install", () => self.skipWaiting());

self.addEventListener("activate", (e) => {
    e.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((k) => k !== CACHE)
                        .map((k) => caches.delete(k)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener("fetch", (e) => {
    const { request } = e;
    if (request.method !== "GET") return;

    const url = new URL(request.url);

    // statiques même origine (JS/CSS/SVG/fonts/images) — cache-first
    if (url.origin === self.location.origin && STATIC_RE.test(url.pathname)) {
        e.respondWith(
            caches
                .match(request)
                .then((cached) => cached ?? fetchAndCache(request)),
        );
        return;
    }

    // ne pas interférer avec /scan, /map HTML, Mercure SSE, etc
});

function fetchAndCache(request) {
    return fetch(request).then((r) => {
        if (r.ok) {
            const copie = r.clone();
            caches.open(CACHE).then((c) => c.put(request, copie));
        }
        return r;
    });
}
