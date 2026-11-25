<?php

// Reguli de securitate pentru autentificare. Valorile pot fi schimbate aici,
// fara a modifica restul codului.
return [
    // Dupa cate minute fara activitate se inchide sesiunea.
    'minute_inactivitate' => 30,

    // Limitarea incercarilor de autentificare nereusite.
    'incercari_pe_email' => 5,
    'incercari_pe_ip' => 20,
    'minute_blocare' => 15,

    // Cate cereri de cont sau de resetare poate trimite aceeasi adresa IP intr-o ora.
    'cereri_pe_ora' => 10,

    // Cat timp este valabil un link de resetare a parolei.
    'minute_valabilitate_link' => 60,

    // Parola: lungimea minima. Maximul de 72 vine de la algoritmul bcrypt.
    'lungime_minima_parola' => 8,
    'lungime_maxima_parola' => 72,
];
