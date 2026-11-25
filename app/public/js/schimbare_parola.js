"use strict";
// Interacțiunile View-ului pentru pagina „Schimbare parolă”.
// Verificarea parolei actuale și regulile parolei noi sunt pe server.
// Folosește funcțiile din comun.js.
const form = document.querySelector("form");
const feedback = document.querySelector(".feedback");
const buton = document.getElementById("save");
const parolaNoua = document.getElementById("password");
const confirmare = document.getElementById("confirm");

function arataMesaj(text, reusita = false) {
  feedback.textContent = text;
  feedback.classList.toggle("success", reusita);
  feedback.hidden = false;
}

// „Arată parolele”
document.getElementById("show-passwords").addEventListener("change", (event) => {
  form.querySelectorAll(".field input").forEach((camp) => {
    camp.type = event.target.checked ? "text" : "password";
  });
});

// Cele două parole noi trebuie să coincidă.
function verificaConfirmarea() {
  confirmare.setCustomValidity(
    confirmare.value && confirmare.value !== parolaNoua.value
      ? "Parolele nu coincid."
      : "",
  );
}

parolaNoua.addEventListener("input", verificaConfirmarea);
confirmare.addEventListener("input", verificaConfirmarea);

form.addEventListener("submit", async (event) => {
  event.preventDefault();

  buton.disabled = true;
  feedback.hidden = true;

  const { reusit, date } = await cereServerului(
    "schimbare-parola",
    new FormData(form),
  );

  if (reusit && date.succes) {
    form.reset();
    arataMesaj(date.mesaj, true);
  } else {
    arataMesaj(date.mesaj || "Parola nu a putut fi schimbată.");
  }

  buton.disabled = false;
});

incarcaSesiunea();
buton.disabled = false;
