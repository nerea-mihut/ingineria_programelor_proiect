<?php

// Rolurile aplicatiei si ce vede fiecare dupa autentificare (RF-002 din SRS).
//
// 'eticheta'     = numele afisat in interfata
// 'departamente' = departamentele permise pentru rol (lista goala = fara departament)
// 'pagina_start' = ruta deschisa imediat dupa autentificare
// 'module'       = modulele rolului; 'ruta' => null inseamna ca pagina nu exista inca
//
// Cand un coleg termina o pagina, pune aici numele rutei ei in loc de null.
$moduleOperator = [
    [
        'titlu' => 'Raportare defecțiune',
        'descriere' => 'Semnalezi o problemă la echipamentul pe care lucrezi.',
        'ruta' => null,
    ],
    [
        'titlu' => 'Opriri și reluări',
        'descriere' => 'Înregistrezi și consulți opririle și reluările producției.',
        'ruta' => null,
    ],
];

$moduleMecanic = [
    [
        'titlu' => 'Hartă și sesizări',
        'descriere' => 'Vezi echipamentele pe hartă și lista sesizărilor.',
        'ruta' => null,
    ],
    [
        'titlu' => 'Intervenții',
        'descriere' => 'Preiei lucrări și consemnezi constatările și piesele folosite.',
        'ruta' => null,
    ],
    [
        'titlu' => 'Consultare inventar',
        'descriere' => 'Cauți piese după cod sau denumire.',
        'ruta' => null,
    ],
];

$moduleAdministratorMentenanta = [
    [
        'titlu' => 'Catalog de articole',
        'descriere' => 'Adaugi, modifici și arhivezi articole.',
        'ruta' => null,
        'departament' => 'Mentenanță',
    ],
    [
        'titlu' => 'Stocuri și mișcări',
        'descriere' => 'Înregistrezi recepții, corecții și eliminări.',
        'ruta' => null,
        'departament' => 'Mentenanță',
    ],
    [
        'titlu' => 'Priorități echipamente',
        'descriere' => 'Stabilești prioritatea fiecărui echipament.',
        'ruta' => null,
        'departament' => 'Mentenanță',
    ],
    [
        'titlu' => 'Rapoarte de inventar',
        'descriere' => 'Generezi și filtrezi rapoarte despre stoc.',
        'ruta' => null,
        'departament' => 'Mentenanță',
    ],
];

$moduleAdministratorGeneral = [
    [
        'titlu' => 'Administrare conturi',
        'descriere' => 'Aprobi conturi, stabilești roluri și resetezi parole.',
        'ruta' => 'administrare-conturi',
    ],
];

return [
    'departamente' => ['Mentenanță', 'Producție'],

    'roluri' => [
        'operator_cnc' => [
            'eticheta' => 'Operator CNC',
            'departamente' => ['Producție'],
            'pagina_start' => 'panou',
            'module' => $moduleOperator,
        ],
        'mecanic' => [
            'eticheta' => 'Mecanic',
            'departamente' => ['Mentenanță'],
            'pagina_start' => 'panou',
            'module' => $moduleMecanic,
        ],
        'administrator_departament' => [
            'eticheta' => 'Administrator de departament',
            'departamente' => ['Mentenanță', 'Producție'],
            'pagina_start' => 'panou',
            'module' => $moduleAdministratorMentenanta,
        ],
        // Administratorul general are acces la toate modulele.
        'administrator_general' => [
            'eticheta' => 'Administrator general',
            'departamente' => [],
            'pagina_start' => 'panou',
            'module' => array_merge(
                $moduleAdministratorGeneral,
                $moduleAdministratorMentenanta,
                $moduleMecanic,
                $moduleOperator
            ),
        ],
    ],
];
