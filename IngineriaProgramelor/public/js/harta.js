"use strict";

// Harta halei: doar afisare. Starea utilajelor se reimprospateaza automat.
const hala = document.getElementById("hala");
const indicator = document.getElementById("stare-conexiune");
const INTERVAL_MS = 5000;
const ESECURI_PANA_LA_NECUNOSCUT = 3;

let ruleaza = false;
let esecuri = 0;

function seteazaStare(element, stare) {
    element.className = "utilaj stare-" + stare;
}

function aplica(date) {
    const primite = new Map(date.utilaje.map((u) => [u.cod, u.stare]));

    hala.querySelectorAll(".utilaj").forEach((element) => {
        seteazaStare(element, primite.get(element.dataset.cod) ?? "necunoscut");
    });

    esecuri = 0;
    indicator.classList.remove("eroare");
    indicator.textContent = "Actualizat la " + date.actualizat;
}

function marcheazaEroare() {
    esecuri += 1;
    indicator.classList.add("eroare");
    indicator.textContent = "Conexiune pierdută, se reîncearcă…";

    if (esecuri >= ESECURI_PANA_LA_NECUNOSCUT) {
        hala.querySelectorAll(".utilaj").forEach((element) => seteazaStare(element, "necunoscut"));
    }
}

async function actualizeaza() {
    if (ruleaza) {
        return;
    }

    ruleaza = true;

    try {
        const raspuns = await fetch(hala.dataset.url, {
            headers: { Accept: "application/json" },
            cache: "no-store",
            credentials: "same-origin",
        });

        if (raspuns.redirected || raspuns.status === 401 || raspuns.status === 403) {
            window.location.href = "/login";
            return;
        }

        if (!raspuns.ok) {
            throw new Error("Raspuns neasteptat");
        }

        aplica(await raspuns.json());
    } catch (eroare) {
        marcheazaEroare();
    } finally {
        ruleaza = false;
    }
}

actualizeaza();
setInterval(actualizeaza, INTERVAL_MS);
