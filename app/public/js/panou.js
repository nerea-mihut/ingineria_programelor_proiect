"use strict";
// Interacțiunile View-ului pentru pagina principală.
// Datele contului și modulele rolului vin de la server; aici doar le afișăm.
// Folosește funcțiile din comun.js.
const feedback = document.querySelector(".feedback");

function completeaza(id, valoare) {
  document.getElementById(id).textContent = valoare || "—";
}

// Un modul cu rută este un link. Unul fără rută nu are încă pagină.
function creeazaModul(modul) {
  const element = document.createElement(modul.ruta ? "a" : "div");
  element.className = "module";

  if (modul.ruta) {
    element.href = "index.php?ruta=" + encodeURIComponent(modul.ruta);
  }

  const titlu = document.createElement("span");
  titlu.className = "module-title";
  titlu.textContent = modul.titlu;

  const eticheta = document.createElement("span");
  eticheta.className = modul.ruta ? "badge accent" : "badge";
  eticheta.textContent = modul.ruta ? "Deschide" : "În lucru";
  titlu.append(eticheta);

  const descriere = document.createElement("span");
  descriere.className = "module-text";
  descriere.textContent = modul.descriere;

  element.append(titlu, descriere);

  const rand = document.createElement("li");
  rand.append(element);

  return rand;
}

async function afiseazaPanoul() {
  const sesiune = await incarcaSesiunea();

  if (!sesiune) {
    feedback.textContent =
      "Datele contului nu au putut fi încărcate. Reîncarcă pagina.";
    feedback.hidden = false;
    return;
  }

  const { utilizator, module } = sesiune;

  document.getElementById("welcome-title").textContent =
    "Bun venit, " + utilizator.nume + "!";
  completeaza("user-name", utilizator.nume);
  completeaza("user-email", utilizator.email);
  completeaza("user-role", utilizator.rol_eticheta);
  completeaza("user-department", utilizator.departament);

  document.getElementById("modules").replaceChildren(...module.map(creeazaModul));
  document.getElementById("no-modules").hidden = module.length > 0;
}

afiseazaPanoul();
