<?php
/* ================================================================
 *  RV HARD FOTO-GALERIE – ADMIN KONFIGURATION (config.php)
 *
 *  ACHTUNG!
 *  - Diese Datei enthält GEHEIME Passwort-Hashes!
 *  - Sie ist in .gitignore eingetragen und wird NICHT ins Git geladen!
 *  - Kopiere diese Datei VOR dem Upload und benenne sie um nach
 *    👉 config.php
 *  - Setze den bcrypt Passwort-Hash für RVHardAdmin (siehe unten).
 *
 *  BENUTZERNAME (User Wunsch!):    RVHardAdmin
 *  Passwort Hash generieren: admin/generate-password-hash.php
 * ================================================================ */

return [

    /* ---- ZUGANGSDATEN ADMIN BEREICH ---- */
    'users' => [

        'RVHardAdmin' => [
            'displayName' => 'Foto-Galerie Administrator',

            /* ===== ERSETZE DEN HASH UNTEN DURCH DEINEN EIGENEN! =====
             *
             * So geht's:
             * 1. Öffne: admin/generate-password-hash.php
             * 2. Trage dort DEIN Wunsch-Passwort bei $password = '...' ein
             * 3. Lade die Datei auf den Server, öffne sie im Browser
             * 4. Kopiere den bcrypt Hash (fängt mit $2y$10$ an)
             * 5. Füge ihn unten zwischen die ' Anführungszeichen ein:
             */
            'hash' => '',

            /* ↓ HIER KOMMT DEIN HASH REIN, BEISPIEL: ↓
            'hash' => '$2y$10$CwTycUXWue0Thq9StjUM0uJ8bD8n25iLgH50Kx40a0bq93qHtT7hy',
            */
        ],

        /* Optional: Weitere Benutzer hier ergänzen
        'ZweiterUser' => [
            'displayName' => '2. Admin',
            'hash' => '$2y$10$...'
        ]
        */
    ],

    /* ---- OPTIONAL: Sicherheitseinstellungen ---- */
    'security' => [
        'session_name' => 'RVHARD_FOTO_ADMIN',
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'require_https'  => true   /* Im Live-Betrieb auf TRUE lassen! */
    ]
];
