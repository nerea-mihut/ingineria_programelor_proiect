"use strict";
// Funcții comune pentru paginile de după autentificare:
// discuția cu serverul, datele utilizatorului și deconectarea.
const RUTA_LOGIN = "index.php?ruta=login";

// Trimite omul la pagina de autentificare. „motiv” explică de ce (sesiune expirată etc.).
function mergiLaAutentificare(motiv) {
  window.location.replace(
    RUTA_LOGIN + (motiv ? "&motiv=" + encodeURIComponent(motiv) : ""),
  );
}

// Trimite o cerere către o rută a aplicației și întoarce răspunsul JSON.
// Fără „campuri” citește date (GET); cu „campuri” trimite o operație (POST).
// Întoarce { reusit, cod, date }.
async function cereServerului(ruta, campuri) {
  const optiuni = {
    headers: { Accept: "application/json", "X-Requested-With": "fetch" },
    credentials: "same-origin",
  };

  if (campuri) {
    optiuni.method = "POST";
    optiuni.body = new URLSearchParams(campuri);
  }

  let raspuns;

  try {
    raspuns = await fetch("index.php?ruta=" + ruta, optiuni);
  } catch (eroare) {
    return {
      reusit: false,
      cod: 0,
      date: { mesaj: "Serverul nu a răspuns. Verifică dacă Laragon este pornit." },
    };
  }

  let date;

  try {
    date = await raspuns.json();
  } catch (eroare) {
    date = { mesaj: "Serverul a trimis un răspuns neașteptat." };
  }

  // Sesiunea nu mai există: pagina nu mai are ce arăta.
  if (raspuns.status === 401) {
    mergiLaAutentificare(date.motiv);
    return new Promise(() => {});
  }

  return { reusit: raspuns.ok, cod: raspuns.status, date };
}

// Citește cine este autentificat și completează numele din bara de sus.
// Întoarce { utilizator, module } sau null dacă datele nu au putut fi citite.
async function incarcaSesiunea() {
  const { reusit, date } = await cereServerului("sesiune");

  if (!reusit) {
    return null;
  }

  const cine = document.getElementById("who");

  if (cine) {
    const nume = document.createElement("strong");
    nume.textContent = date.utilizator.nume;
    cine.replaceChildren(nume, date.utilizator.rol_eticheta);
    cine.hidden = false;
  }

  return date;
}

// Butonul „Deconectare” din bara de sus.
const butonDeconectare = document.getElementById("logout");

if (butonDeconectare) {
  butonDeconectare.addEventListener("click", async () => {
    butonDeconectare.disabled = true;

    const { reusit } = await cereServerului("deconectare", {});

    if (reusit) {
      mergiLaAutentificare();
      return;
    }

    butonDeconectare.disabled = false;
    window.alert("Deconectarea nu a reușit. Încearcă din nou.");
  });
}
