<?php

namespace App\Models;

// MODEL: regulile de acces pe roluri (RF-002 din SRS).
// Citeste config/roluri.php si raspunde la intrebarea "ce are voie acest utilizator?".
class Drepturi
{
    public const ADMINISTRATOR_GENERAL = 'administrator_general';

    private static function roluri(): array
    {
        return config('roluri.roluri', []);
    }

    public static function rolCunoscut(string $rol): bool
    {
        return isset(self::roluri()[$rol]);
    }

    // Numele rolului asa cum se afiseaza in interfata.
    public static function eticheta(string $rol): string
    {
        return self::roluri()[$rol]['eticheta'] ?? $rol;
    }

    public static function paginaStart(string $rol): string
    {
        return self::roluri()[$rol]['pagina_start'] ?? 'panou';
    }

    public static function departamentePermise(string $rol): array
    {
        return self::roluri()[$rol]['departamente'] ?? [];
    }

    // Un rol fara departamente permise primeste null. Altfel departamentul
    // trebuie sa fie unul dintre cele permise rolului.
    public static function departamentValid(string $rol, ?string $departament): bool
    {
        $permise = self::departamentePermise($rol);

        if ($permise === []) {
            return $departament === null;
        }

        return $departament !== null && in_array($departament, $permise, true);
    }

    // Lista folosita de pagina de administrare pentru cele doua liste derulante.
    public static function roluriPentruInterfata(): array
    {
        $roluri = [];

        foreach (self::roluri() as $cod => $rol) {
            $roluri[] = [
                'cod' => $cod,
                'eticheta' => $rol['eticheta'],
                'departamente' => $rol['departamente'],
            ];
        }

        return $roluri;
    }

    // Modulele pe care le vede utilizatorul pe pagina principala.
    public static function module(string $rol, ?string $departament): array
    {
        $module = [];

        foreach (self::roluri()[$rol]['module'] ?? [] as $modul) {
            $departamentCerut = $modul['departament'] ?? null;
            $areAcces = $rol === self::ADMINISTRATOR_GENERAL
                || $departamentCerut === null
                || $departamentCerut === $departament;

            if ($areAcces) {
                $module[] = [
                    'titlu' => $modul['titlu'],
                    'descriere' => $modul['descriere'],
                    'ruta' => $modul['ruta'],
                ];
            }
        }

        return $module;
    }

    // Verifica daca utilizatorul are unul dintre rolurile cerute.
    // Daca se cere si un departament, trebuie sa fie departamentul utilizatorului.
    // Administratorul general are acces la toate modulele.
    public static function areAcces(array $utilizator, array $roluri, ?string $departament = null): bool
    {
        if ($utilizator['rol'] === self::ADMINISTRATOR_GENERAL) {
            return true;
        }

        if (!in_array($utilizator['rol'], $roluri, true)) {
            return false;
        }

        return $departament === null || $utilizator['departament'] === $departament;
    }
}
