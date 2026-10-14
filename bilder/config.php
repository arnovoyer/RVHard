<?php
/* ============================================================
 *  RV HARD – BILDER-UPLOAD KONFIGURATION
 *  Hier kannst du E-Mail, ntfy Push und Pfade einstellen!
 * ============================================================ */

return [
    /* ===== ADMIN-LOGIN FÜR DEN BILDER-ADMIN =====
     * Eigenes Passwort (unabhängig vom Galerie-Admin, weil andere Subdomain).
     * Ändere das Passwort! */
    'admin_password' => 'rvhard2024',

    /* PFAD ZUR GALERIE-ADMIN AUTH (auth2.php)
     * Wird NICHT MEHR für den Login verwendet (Cross-Subdomain-Problem),
     * bleibt aber als Fallback erhalten. */
    'auth_path'   => '/www/htdocs/w007ee49/fotos.rv-hard.at/admin/auth2.php',

    /* Benachrichtigungs-Empfänger */
    'email_to'    => 'vorstand@rv-hard.at',   /* ← DEINE E-Mail-Adresse */
    'email_from'  => 'noreply@rv-hard.at',

    /* ntfy.sh Push-Benachrichtigung */
    'ntfy_topic'  => 'rvhard-bildereingang',   /* ← Topic-Name (in der ntfy App abonnieren) */
    'ntfy_server' => 'https://ntfy.sh',

    /* Upload-Limits */
    'max_files'   => 20,
    'max_img_mb'  => 15,
    'max_vid_mb'  => 50,
];
