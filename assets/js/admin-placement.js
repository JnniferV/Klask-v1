// outil admin de placement carte
const cfg = JSON.parse(document.getElementById("placement-config").textContent);
const type = cfg.type;
const sphereId = cfg.sphereId;
const box = document.getElementById("map-box");
const img = box.querySelector("img");
const marker = document.getElementById("marker");
const ghosts = document.getElementById("ghosts");
const inpX = document.getElementById("pointX");
const inpY = document.getElementById("pointY");
const inpR = document.getElementById("radius");
const actPin = document.getElementById("act-pin");
const actForm = document.getElementById("activity-form");
const actX = document.getElementById("act-x");
const actY = document.getElementById("act-y");
const raw = JSON.parse(document.getElementById("map-data").textContent);
const data = raw.spheres ?? [];
const standalone = raw.standalone ?? [];
let addMode = false;

function sizeMarker() {
    marker.style.width = inpR.value * 2 + "%";
    marker.style.aspectRatio = "1";
}

function place(x, y) {
    inpX.value = x.toFixed(2);
    inpY.value = y.toFixed(2);
    marker.style.left = x + "%";
    marker.style.top = y + "%";
    if (type === "sphere" && inpR) sizeMarker();
}

// mode toggle
document.querySelectorAll('[name="mapMode"]').forEach((r) =>
    r.addEventListener("change", (e) => {
        addMode = e.target.value === "activity";
    }),
);

data.forEach(function (s) {
    const z = document.createElement("div");
    z.className = "ghost sphere";
    z.style.cssText =
        "left:" +
        s.centerX +
        "%;top:" +
        s.centerY +
        "%;width:" +
        (s.radius ?? 10) * 2 +
        "%;aspect-ratio:1;background:" +
        s.color;
    ghosts.appendChild(z);
    s.activities.forEach(function (a) {
        const p = document.createElement("div");
        p.className = "ghost pin";
        if (s.id === sphereId) p.style.opacity = "0.8";
        p.style.cssText +=
            "left:" + a.pointXActivity + "%;top:" + a.pointYActivity + "%";
        ghosts.appendChild(p);
    });
});

standalone.forEach(function (a) {
    const p = document.createElement("div");
    p.className = "ghost pin";
    p.style.cssText =
        "left:" + a.pointXActivity + "%;top:" + a.pointYActivity + "%";
    ghosts.appendChild(p);
});

place(parseFloat(inpX.value), parseFloat(inpY.value));

if (inpR) inpR.addEventListener("input", sizeMarker);

// clic sur la carte
box.addEventListener("click", function (e) {
    const r = img.getBoundingClientRect();
    const x = ((e.clientX - r.left) / r.width) * 100;
    const y = ((e.clientY - r.top) / r.height) * 100;

    if (addMode && actPin && actForm) {
        actX.value = x.toFixed(2);
        actY.value = y.toFixed(2);
        actPin.style.left = x + "%";
        actPin.style.top = y + "%";
        actPin.style.display = "";
        actForm.style.display = "";
    } else {
        place(x, y);
    }
});

// bouton Ajuster à mes activités
const autoBoundBtn = document.getElementById("auto-bound");
if (autoBoundBtn) {
    const suggested = cfg.suggestedBounds;
    autoBoundBtn.addEventListener("click", function () {
        if (!suggested) {
            alert("Aucun stand positionné pour cette sphère.");
            return;
        }
        inpX.value = suggested.centerX.toFixed(2);
        inpY.value = suggested.centerY.toFixed(2);
        if (inpR) inpR.value = suggested.radius.toFixed(2);
        place(suggested.centerX, suggested.centerY);
    });
}
