@php
    // cod, eticheta, stanga %, sus %, latime %, inaltime %
    $utilaje = [
        ['FLEX_FST',    'Flexomat FST',     6.3, 10.3, 14.0, 15.4],
        ['FLEX_FSG',    'Flexomat FSG',    39.1, 10.3, 15.6, 15.4],
        ['ROBOT_CNC',   'CNC/robot mic',   85.9,  7.8,  9.4, 11.5],
        ['ELECTROD',    'Electrod',        85.9, 19.3,  9.4, 11.5],
        ['SIEMENS_FSG', 'Siemens FSG',      3.1, 33.5, 18.8, 60.4],
        ['PRESA',       'Presă automată',  24.9, 48.9,  9.5, 11.5],
        ['LASER',       'Laser gravură',   24.9, 61.0,  9.5, 11.5],
        ['UNS_CLEME',   'Uns cleme',       24.9, 73.1,  9.5, 11.5],
        ['AAP11_U13',   'AAP 11 U 13',     40.7, 32.2, 54.7, 40.2],
        ['AEB35',       'AEB 35',          73.5, 59.1, 23.5, 17.9],
        ['SIEMENS_FST', 'Siemens FST',     54.7, 77.0, 14.1, 15.4],
    ];
@endphp
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hala · M.E.S.H.</title>
    <link rel="stylesheet" href="{{ asset('css/harta.css') }}">
    <script src="{{ asset('js/harta.js') }}" defer></script>
</head>
<body>
    <header class="bara">
        <div class="marca">
            <span class="logo" aria-hidden="true">M</span>
            <div>
                <strong>M.E.S.H.</strong>
                <span>Monitor hală</span>
            </div>
        </div>

        <ul class="legenda">
            <li><i class="punct verde"></i>Funcțional</li>
            <li><i class="punct galben"></i>Intervenție în lucru</li>
            <li><i class="punct rosu"></i>Oprit, nepreluat</li>
        </ul>

        <div class="stare-conexiune" id="stare-conexiune">Se încarcă…</div>
    </header>

    <main class="scena">
        <div class="hala" id="hala" data-url="{{ route('harta-date', [], false) }}">
            @foreach ($utilaje as [$cod, $eticheta, $stanga, $sus, $latime, $inaltime])
                <div class="utilaj stare-necunoscut" data-cod="{{ $cod }}"
                     style="left: {{ $stanga }}%; top: {{ $sus }}%; width: {{ $latime }}%; height: {{ $inaltime }}%;">
                    <span>{{ $eticheta }}</span>
                </div>
            @endforeach
        </div>
    </main>
</body>
</html>
