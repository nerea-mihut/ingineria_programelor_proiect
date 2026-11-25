"use strict";
// Interacțiunile View-ului pentru pagina „Administrare conturi”.
// Serverul verifică la fiecare operație că cel autentificat este administrator general.
// Folosește funcțiile din comun.js.

let roluri = [];
let cronometruMesaj = 0;

const mesaj = document.getElementById("toast");

// ----- Elemente ajutătoare -----

function element(tag, clasa, text) {
  const nou = document.createElement(tag);

  if (clasa) {
    nou.className = clasa;
  }

  if (text !== undefined) {
    nou.textContent = text;
  }

  return nou;
}

function arataMesaj(text, eroare = false) {
  mesaj.textContent = text;
  mesaj.classList.toggle("error", eroare);
  mesaj.hidden = false;

  window.clearTimeout(cronometruMesaj);
  cronometruMesaj = window.setTimeout(() => {
    mesaj.hidden = true;
  }, 7000);
}

function buton(text, laApasare, principal = false) {
  const nou = element("button", principal ? "button primary" : "button", text);
  nou.type = "button";
  nou.addEventListener("click", () => laApasare(nou));

  return nou;
}

// Numele, e-mailul și un detaliu (data cererii) pentru un rând din listă.
function persoana(nume, email, detaliu) {
  const bloc = element("div", "person");
  bloc.append(element("strong", "", nume), element("span", "", email));

  if (detaliu) {
    bloc.append(element("span", "", detaliu));
  }

  return bloc;
}

// Cele două liste derulante: rolul și departamentele permise pentru rolul ales.
function alegereRolSiDepartament(numePersoana, rolCurent, departamentCurent) {
  const rol = element("select");
  rol.setAttribute("aria-label", "Rol pentru " + numePersoana);

  const departament = element("select");
  departament.setAttribute("aria-label", "Departament pentru " + numePersoana);

  // Fără rol ales încă, sau cu un rol pe care aplicația nu îl cunoaște.
  if (!roluri.some((unRol) => unRol.cod === rolCurent)) {
    const gol = new Option(rolCurent ? rolCurent + " (necunoscut)" : "Alege rolul", "");
    gol.disabled = true;
    gol.selected = true;
    rol.append(gol);
  }

  roluri.forEach((unRol) =>
    rol.append(new Option(unRol.eticheta, unRol.cod, false, unRol.cod === rolCurent)),
  );

  function actualizeazaDepartamentele(deSelectat) {
    const rolAles = roluri.find((unRol) => unRol.cod === rol.value);
    const permise = rolAles ? rolAles.departamente : [];

    departament.replaceChildren();

    if (permise.length === 0) {
      departament.append(new Option(rolAles ? "Fără departament" : "—", ""));
    }

    permise.forEach((nume) =>
      departament.append(new Option(nume, nume, false, nume === deSelectat)),
    );

    departament.disabled = permise.length < 2;
  }

  rol.addEventListener("change", () => actualizeazaDepartamentele(null));
  actualizeazaDepartamentele(departamentCurent);

  const eticheteaza = (text, lista) => {
    const eticheta = element("label", "choice", text);
    eticheta.append(lista);

    return eticheta;
  };

  return {
    rol,
    departament,
    campuri: [eticheteaza("Rol", rol), eticheteaza("Departament", departament)],
  };
}

// Trimite o operație, afișează rezultatul și reîncarcă listele.
async function executa(ruta, campuri, butonApasat) {
  butonApasat.disabled = true;

  const { reusit, date } = await cereServerului(ruta, campuri);

  butonApasat.disabled = false;

  if (!reusit) {
    arataMesaj(date.mesaj || "Operația nu a reușit.", true);
    return null;
  }

  await incarcaListele();

  if (date.mesaj) {
    arataMesaj(date.mesaj);
  }

  return date;
}

// ----- Cereri de cont -----

function randCerereCont(cerere) {
  const rand = element("li", "row");
  const alegere = alegereRolSiDepartament(cerere.nume_complet, "", null);

  const aproba = buton(
    "Aprobă",
    (apasat) =>
      executa(
        "aprobare-cerere-cont",
        {
          id_cerere: cerere.id_cerere,
          rol: alegere.rol.value,
          departament: alegere.departament.value,
        },
        apasat,
      ),
    true,
  );

  const respinge = buton("Respinge", (apasat) => {
    if (window.confirm("Respingi cererea de cont pentru " + cerere.nume_complet + "?")) {
      executa("respingere-cerere-cont", { id_cerere: cerere.id_cerere }, apasat);
    }
  });

  const actiuni = element("div", "row-actions");
  actiuni.append(aproba, respinge);

  rand.append(
    persoana(cerere.nume_complet, cerere.email, "Cerere din " + cerere.creata_la),
    ...alegere.campuri,
    actiuni,
  );

  return rand;
}

// ----- Resetarea parolei -----

// Linkul apare o singură dată; nu poate fi afișat din nou mai târziu.
function arataLinkul(date) {
  const link = new URL(date.cale, window.location.href).href;

  document.getElementById("link-title").textContent =
    "Link de resetare pentru " + date.nume;
  document.getElementById("link-value").value = link;
  document.getElementById("link-note").textContent =
    "Valabil " +
    date.minute +
    " de minute, pentru o singură folosire. Transmite-l doar persoanei respective. " +
    "După ce închizi pagina, linkul nu mai poate fi afișat.";

  const cutie = document.getElementById("link-box");
  cutie.hidden = false;
  cutie.scrollIntoView({ block: "center" });
}

async function genereazaLink(campuri, butonApasat) {
  const date = await executa("generare-link-resetare", campuri, butonApasat);

  if (date) {
    arataLinkul(date);
  }
}

function randCerereResetare(cerere) {
  const rand = element("li", "row simple");

  const genereaza = buton(
    "Generează link",
    (apasat) => genereazaLink({ id_resetare: cerere.id_resetare }, apasat),
    true,
  );

  const respinge = buton("Respinge", (apasat) => {
    if (window.confirm("Respingi cererea de resetare pentru " + cerere.nume_complet + "?")) {
      executa("respingere-cerere-resetare", { id_resetare: cerere.id_resetare }, apasat);
    }
  });

  const actiuni = element("div", "row-actions");
  actiuni.append(genereaza, respinge);

  rand.append(
    persoana(cerere.nume_complet, cerere.email, "Cerere din " + cerere.solicitata_la),
    actiuni,
  );

  return rand;
}

document.getElementById("link-copy").addEventListener("click", async () => {
  const camp = document.getElementById("link-value");

  try {
    await navigator.clipboard.writeText(camp.value);
    arataMesaj("Linkul a fost copiat.");
  } catch (eroare) {
    camp.select();
    arataMesaj("Linkul este selectat. Apasă Ctrl+C pentru a-l copia.");
  }
});

// ----- Conturi -----

function randCont(cont) {
  const rand = element("li", cont.activ ? "row" : "row inactive");
  const bloc = persoana(cont.nume, cont.email);

  if (cont.propriu) {
    bloc.firstChild.append(element("span", "badge accent", "Contul tău"));
  }

  if (!cont.activ) {
    bloc.firstChild.append(element("span", "badge", "Dezactivat"));
  }

  const alegere = alegereRolSiDepartament(cont.nume, cont.rol, cont.departament);
  const actiuni = element("div", "row-actions");

  rand.append(bloc, ...alegere.campuri, actiuni);

  // Propriul rol și propriul cont nu pot fi schimbate de aici.
  if (cont.propriu) {
    alegere.rol.disabled = true;
    alegere.departament.disabled = true;

    return rand;
  }

  const salveaza = buton(
    "Salvează",
    (apasat) =>
      executa(
        "actualizare-cont",
        {
          id_utilizator: cont.id,
          rol: alegere.rol.value,
          departament: alegere.departament.value,
        },
        apasat,
      ),
    true,
  );

  // „Salvează” devine activ doar după o schimbare.
  salveaza.disabled = true;

  const laSchimbare = () => {
    salveaza.disabled =
      alegere.rol.value === "" ||
      (alegere.rol.value === cont.rol &&
        alegere.departament.value === (cont.departament || ""));
  };

  alegere.rol.addEventListener("change", laSchimbare);
  alegere.departament.addEventListener("change", laSchimbare);

  actiuni.append(salveaza);

  if (cont.activ) {
    actiuni.append(
      buton("Link de resetare", (apasat) =>
        genereazaLink({ id_utilizator: cont.id }, apasat),
      ),
      buton("Dezactivează", (apasat) => {
        if (window.confirm("Dezactivezi contul pentru " + cont.nume + "?")) {
          executa("stare-cont", { id_utilizator: cont.id, activ: 0 }, apasat);
        }
      }),
    );
  } else {
    actiuni.append(
      buton("Activează", (apasat) =>
        executa("stare-cont", { id_utilizator: cont.id, activ: 1 }, apasat),
      ),
    );
  }

  return rand;
}

// ----- Încărcarea listelor -----

function afiseazaLista(idLista, idNumar, idGol, randuri) {
  document.getElementById(idLista).replaceChildren(...randuri);

  const numar = document.getElementById(idNumar);
  numar.textContent = randuri.length;
  numar.hidden = randuri.length === 0;

  if (idGol) {
    document.getElementById(idGol).hidden = randuri.length > 0;
  }
}

async function incarcaListele() {
  const { reusit, cod, date } = await cereServerului("administrare-conturi-date");

  // Rolul a fost schimbat între timp: pagina nu mai este pentru acest cont.
  if (cod === 403) {
    window.location.replace("index.php?ruta=panou");
    return;
  }

  if (!reusit) {
    arataMesaj(date.mesaj || "Datele nu au putut fi încărcate.", true);
    return;
  }

  roluri = date.roluri;

  afiseazaLista("requests", "requests-count", "no-requests", date.cereri_cont.map(randCerereCont));
  afiseazaLista("resets", "resets-count", "no-resets", date.cereri_resetare.map(randCerereResetare));
  afiseazaLista("accounts", "accounts-count", null, date.conturi.map(randCont));
}

incarcaSesiunea();
incarcaListele();
