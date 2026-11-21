"use strict";
// Doar interacțiuni View; fără controller sau cereri către server.
const form = document.querySelector("form");
const feedback = document.querySelector(".feedback");
document
  .querySelectorAll("[data-passwords]")
  .forEach((toggle) =>
    toggle.addEventListener("change", () =>
      toggle.dataset.passwords
        .split(",")
        .forEach(
          (id) =>
            (document.getElementById(id).type = toggle.checked
              ? "text"
              : "password"),
        ),
    ),
  );
const password = document.getElementById("password"),
  confirm = document.getElementById("confirm");
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
form.addEventListener("submit", (event) => {
  event.preventDefault();
  feedback.textContent = {
    login:
      "Previzualizare: autentificarea nu este încă legată la server. Datele nu au fost trimise.",
    register:
      "Previzualizare: nu a fost creat niciun cont. Înregistrarea nu este încă legată la server.",
    recover:
      "Previzualizare: nu a fost trimis niciun e-mail. Recuperarea nu este încă legată la server.",
  }[form.dataset.kind];
  feedback.hidden = false;
});
document.querySelector(".primary").disabled = false;
