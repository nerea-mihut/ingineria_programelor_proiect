<?php

use App\Http\Controllers\AdministrareConturiController;
use App\Http\Controllers\AutentificareController;
use App\Http\Controllers\ContController;
use App\Http\Controllers\PanouController;
use Illuminate\Support\Facades\Route;

// Rutele aplicatiei M.E.S.H.
// O pagina noua se adauga aici, cu controllerul ei.

Route::get('/', fn () => redirect()->route('login'));

// ----- Pagini publice -----
Route::get('/login', [AutentificareController::class, 'afiseazaLogin'])->name('login');
Route::get('/creare-cont', [ContController::class, 'afiseazaCreareCont'])->name('creare-cont');
Route::get('/recuperare-parola', [ContController::class, 'afiseazaRecuperareParola'])->name('recuperare-parola');
Route::get('/parola-noua', [ContController::class, 'afiseazaParolaNoua'])->name('parola-noua');

// ----- Operatii publice, apelate din JavaScript -----
Route::post('/autentificare', [AutentificareController::class, 'autentifica'])->name('autentificare');
Route::post('/creare-cont', [ContController::class, 'solicitaCont']);
Route::post('/recuperare-parola', [ContController::class, 'solicitaResetare']);
Route::get('/verificare-link', [ContController::class, 'verificaLink'])->name('verificare-link');
Route::post('/parola-noua', [ContController::class, 'seteazaParolaNoua']);

// ----- Dupa autentificare, pentru orice rol -----
Route::middleware('autentificat')->group(function () {
    Route::get('/panou', [PanouController::class, 'afiseaza'])->name('panou');
    Route::get('/sesiune', [AutentificareController::class, 'sesiuneCurenta'])->name('sesiune');
    Route::post('/deconectare', [AutentificareController::class, 'deconecteaza'])->name('deconectare');
    Route::get('/schimbare-parola', [ContController::class, 'afiseazaSchimbareParola'])->name('schimbare-parola');
    Route::post('/schimbare-parola', [ContController::class, 'schimbaParola']);
});

// ----- Doar pentru administratorul general -----
Route::middleware(['autentificat', 'rol:administrator_general'])->group(function () {
    Route::get('/administrare-conturi', [AdministrareConturiController::class, 'afiseaza'])->name('administrare-conturi');
    Route::get('/administrare-conturi-date', [AdministrareConturiController::class, 'date']);
    Route::post('/aprobare-cerere-cont', [AdministrareConturiController::class, 'aprobaCerereCont']);
    Route::post('/respingere-cerere-cont', [AdministrareConturiController::class, 'respingeCerereCont']);
    Route::post('/actualizare-cont', [AdministrareConturiController::class, 'actualizeazaCont']);
    Route::post('/stare-cont', [AdministrareConturiController::class, 'schimbaStareCont']);
    Route::post('/generare-link-resetare', [AdministrareConturiController::class, 'genereazaLinkResetare']);
    Route::post('/respingere-cerere-resetare', [AdministrareConturiController::class, 'respingeCerereResetare']);
});

// ----- Paginile colegilor -----
// Exemplu pentru pagina operatorului CNC (se trece apoi numele rutei in config/roluri.php):
// Route::middleware(['autentificat', 'rol:operator_cnc'])->group(function () {
//     Route::get('/raportare-defectiune', [OperatorController::class, 'afiseaza'])->name('raportare-defectiune');
// });
