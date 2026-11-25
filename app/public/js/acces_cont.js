"use strict";
// Interacțiunile View-ului pentru paginile de acces la cont:
// autentificare, creare cont, parolă uitată și parolă nouă.
//
// Toate verificările se fac pe server, în controllere.
// Aici doar trimitem datele formularului și afișăm răspunsul.

// Mesajele afișate pe pagina de autentificare când serverul trimite omul înapoi la ea.
const MESAJE_LA_INTRARE = {
  "sesiune-expirata": "Sesiunea a expirat. Autentifică-te din nou.",
  "cont-modificat": "Sesiunea nu mai este valabilă. Autentifică-te din nou.",
  "parola-schimbata": "Parola a fost schimbată. Autentifică-te cu noua parolă.",
};

const form = document.querySelector("form");
const feedback = document.querySelector(".feedback");
const buton = document.querySelector(".primary");
const parametri = new URLSearchParams(window.location.search);

function arataMesaj(text, reusita = false) {
  feedback.textContent = text;
  feedback.classList.toggle("success", reusita);
  feedback.hidden = false;
}

// „Arată parola”
document.querySelectorAll("[data-passwords]").forEach((toggle) =>
  toggle.addEventListener("change", () =>
    toggle.dataset.passwords.split(",").forEach((id) => {
      document.getElementById(id).type = toggle.checked ? "text" : "password";
    }),
  ),
);

// Paginile cu două parole: cele două trebuie să coincidă.
const password = document.getElementById("password");
const confirm = document.getElementById("confirm");

if (confirm) {
  const match = () =>
    confirm.setCustomValidity(
      confirm.value && confirm.value !== password.value
        ? "Parolele nu coincid."
        : "",
    );
  password.addEventListener("input", match);
  confirm.addEventListener("input", match);
}

// Trimite formularul către ruta din atributul „action” și întoarce răspunsul.
async function trimiteFormularul() {
  let raspuns;

  try {
    raspuns = await fetch(form.action, {
      method: "POST",
      headers: { Accept: "application/json", "X-Requested-With": "fetch" },
      body: new URLSearchParams(new FormData(form)),
      credentials: "same-origin",
    });
  } catch (eroare) {
    return {
      succes: false,
      mesaj: "Serverul nu a răspuns. Verifică dacă Laragon este pornit.",
    };
  }

  try {
    return await raspuns.json();
  } catch (eroare) {
    return { succes: false, mesaj: "Serverul a trimis un răspuns neașteptat." };
  }
}

form.addEventListener("submit", async (event) => {
  event.preventDefault();

  buton.disabled = true;
  feedback.hidden = true;

  const date = await trimiteFormularul();

  // Autentificare reușită sau parolă nouă salvată: serverul spune unde mergem.
  if (date.succes && date.destinatie) {
    window.location.assign(date.destinatie);
    return;
  }

  // Cerere de cont sau de resetare primită: rămâne doar mesajul.
  if (date.succes) {
    form.hidden = true;
    arataMesaj(date.mesaj, true);
    return;
  }

  arataMesaj(date.mesaj || "Operația nu a reușit. Încearcă din nou.");
  buton.disabled = false;
});

// Pagina „Parolă nouă”: codul din link se trimite împreună cu parola.
// Dacă linkul nu mai este bun, spunem asta înainte ca omul să completeze ceva.
async function pregatesteParolaNoua() {
  const cod = parametri.get("cod") || "";
  form.elements.cod.value = cod;

  try {
    const raspuns = await fetch(
      "index.php?ruta=verificare-link&cod=" + encodeURIComponent(cod),
      { headers: { Accept: "application/json" }, credentials: "same-origin" },
    );
    const date = await raspuns.json();

    if (!date.valid) {
      form.hidden = true;
      arataMesaj(date.mesaj || "Linkul nu este valid sau a expirat.");
      return;
    }
  } catch (eroare) {
    arataMesaj("Serverul nu a răspuns. Verifică dacă Laragon este pornit.");
    return;
  }

  buton.disabled = false;
}

if (form.dataset.kind === "new-password") {
  pregatesteParolaNoua();
} else {
  if (MESAJE_LA_INTRARE[parametri.get("motiv")]) {
    arataMesaj(MESAJE_LA_INTRARE[parametri.get("motiv")], true);
  }

  buton.disabled = false;
}
