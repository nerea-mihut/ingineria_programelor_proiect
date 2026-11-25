# M.E.S.H. — aplicația

Mentenanță, Erori, Stocuri, Hală. Backend în PHP, interfață în HTML/CSS/JavaScript, date în MySQL.

## Pornire pe calculatorul tău (Laragon)

1. În HeidiSQL, rulează pe rând fișierele din `database`:
   - `gestiune_cnc.sql` — creează baza și tabelele;
   - `autentificare.sql` — adaugă rolurile și tabelele pentru cereri de cont, resetarea parolei și limitarea încercărilor (poate fi rulat de mai multe ori);
   - `date_demo.sql` — adaugă conturile demonstrative.
2. În folderul `config`, copiază `configurare.exemplu.php` cu numele `configurare.locala.php` și pune datele tale de conectare din HeidiSQL. Fișierul rămâne doar la tine, nu se urcă în Git.
3. În Laragon: click dreapta → Preferences → Document Root → alege folderul repository-ului (`ingineria_programelor_proiect`). Apoi Stop și Start All.
4. Deschide în browser: http://localhost/app/public/

Dacă ceva nu merge, verifică întâi conexiunea: http://localhost/app/public/test_conexiune.php

Dacă apare mesajul „Baza de date nu este la zi”, rulează `database/autentificare.sql`.

## Conturi demonstrative

Parola pentru toate: `MeshDemo2026!`

| E-mail | Rol | Departament |
|---|---|---|
| operator@mesh.test | Operator CNC | Producție |
| mecanic@mesh.test | Mecanic | Mentenanță |
| admin.mentenanta@mesh.test | Administrator de departament | Mentenanță |
| admin@mesh.test | Administrator general | — |

Conturile sunt doar pentru testare locală.

## Cum funcționează autentificarea

**Autentificare.** E-mail și parolă. După autentificare, fiecare ajunge pe pagina principală, unde vede modulele rolului său. La 5 parole greșite pentru același e-mail, autentificarea se blochează 15 minute. După 30 de minute fără activitate, sesiunea se închide.

**Creare cont.** Formularul nu creează direct un cont, ci trimite o cerere. Administratorul general o vede în „Administrare conturi”, alege rolul și departamentul și o aprobă sau o respinge. Nimeni nu își poate alege singur rolul (RF-001 și RF-002 din SRS).

**Am uitat parola.** Persoana trimite cererea din pagina „Am uitat parola”. Administratorul general generează un link valabil 60 de minute, pentru o singură folosire, și i-l transmite. Cu acel link, persoana își alege singură noua parolă. Aplicația nu trimite e-mailuri.

**Schimbare parolă.** Orice utilizator autentificat își poate schimba parola din pagina principală.

**Administrare conturi** (doar administratorul general): aprobă sau respinge cereri, schimbă rolul și departamentul, dezactivează sau reactivează conturi, generează linkuri de resetare. Schimbările au efect imediat, chiar dacă persoana este autentificată în acel moment.

Reguli pentru rol și departament (`config/roluri.php`):

| Rol | Departament |
|---|---|
| Operator CNC | Producție |
| Mecanic | Mentenanță |
| Administrator de departament | Mentenanță sau Producție |
| Administrator general | fără departament |

Regulile de securitate (număr de încercări, durata sesiunii, lungimea parolei) se schimbă din `config/securitate.php`.

## Cum adaugi o pagină nouă, protejată pe rol

Exemplu: pagina operatorului CNC pentru raportarea unei defecțiuni.

1. Pune pagina în `mvc/Views/operator/raportare.html`, iar stilurile și scriptul în `public/css` și `public/js`.

2. Creează controllerul `mvc/Controllers/OperatorController.php`:

   ```php
   <?php

   declare(strict_types=1);

   final class OperatorController extends Controller
   {
       public function afiseazaRaportare(): void
       {
           // Lasă să treacă doar operatorii CNC. Ceilalți primesc „Acces interzis”,
           // iar cei neautentificați sunt trimiși la pagina de autentificare.
           $utilizator = $this->cereRol(['operator_cnc']);

           if ($utilizator === null) {
               return;
           }

           $this->afiseazaView('operator/raportare.html');
       }
   }
   ```

   `$utilizator` conține `id`, `nume`, `email`, `rol` și `departament`.

   Alte variante:
   - `$this->cereAutentificare()` — orice utilizator autentificat;
   - `$this->cereRol(['mecanic', 'administrator_departament'])` — mai multe roluri;
   - `$this->cereRol(['administrator_departament'], 'Mentenanță')` — rol și departament.

   Administratorul general are acces la toate paginile.

3. În `public/index.php`, adaugă controllerul la lista de `require_once` și ruta în `$rute`:

   ```php
   require_once __DIR__ . '/../mvc/Controllers/OperatorController.php';

   'GET raportare-defectiune' => [OperatorController::class, 'afiseazaRaportare'],
   ```

4. În `config/roluri.php`, la modulul „Raportare defecțiune”, pune `'ruta' => 'raportare-defectiune'` în loc de `null`. Modulul devine buton pe pagina principală. Dacă vrei ca operatorul să ajungă direct acolo după autentificare, schimbă și `'pagina_start'` al rolului.

În JavaScript, pentru cererile către server folosește funcția `cereServerului` din `public/js/comun.js`. Ea trimite antetul cerut de server pentru operațiile POST și te întoarce la autentificare dacă sesiunea a expirat:

```js
const { reusit, date } = await cereServerului("ruta-mea", { camp: "valoare" });
```

În paginile HTML nu pune `<script>` sau `style` direct în pagină: serverul le blochează din motive de securitate. Folosește fișiere separate în `public/js` și `public/css`.

## Structura (MVC)

| Loc | Ce conține |
|---|---|
| `public/index.php` | Punctul de intrare: trimite fiecare cerere la controllerul potrivit. |
| `mvc/Controllers` | Controllere: primesc cererea, folosesc modelul, decid răspunsul. |
| `mvc/Models` | Modele: interogările către baza de date. |
| `mvc/Views` | View-uri: paginile HTML. |
| `public/css`, `public/js` | Stilurile și JavaScript-ul din browser. |
| `config` | Conexiunea la baza de date, sesiunea, rolurile și regulile de securitate. |
| `database` | Scripturile SQL. |

Paginile se deschid prin `index.php?ruta=...`, nu direct din `mvc/Views`.

## Rute existente

| Rută | Metodă | Cine | Ce face |
|---|---|---|---|
| `login` | GET | oricine | Pagina de autentificare. |
| `autentificare` | POST | oricine | Verifică e-mailul și parola, pornește sesiunea. |
| `creare-cont` | GET, POST | oricine | Pagina și trimiterea cererii de cont. |
| `recuperare-parola` | GET, POST | oricine | Pagina și trimiterea cererii de resetare. |
| `parola-noua` | GET, POST | cine are linkul | Alegerea unei parole noi. |
| `verificare-link` | GET | cine are linkul | Verifică dacă linkul de resetare mai este valabil. |
| `panou` | GET | autentificat | Pagina principală, cu modulele rolului. |
| `sesiune` | GET | autentificat | Datele utilizatorului autentificat și modulele lui. |
| `schimbare-parola` | GET, POST | autentificat | Schimbarea propriei parole. |
| `deconectare` | POST | autentificat | Închide sesiunea. |
| `administrare-conturi` | GET | administrator general | Pagina de administrare a conturilor. |
| `administrare-conturi-date` | GET | administrator general | Cererile și conturile, pentru pagina de administrare. |
| `aprobare-cerere-cont`, `respingere-cerere-cont` | POST | administrator general | Aprobă sau respinge o cerere de cont. |
| `actualizare-cont`, `stare-cont` | POST | administrator general | Schimbă rolul și departamentul; activează sau dezactivează contul. |
| `generare-link-resetare`, `respingere-cerere-resetare` | POST | administrator general | Generează linkul de resetare sau respinge cererea. |
