<?php
/* ============================================================
 *  RV HARD – BILDER-UPLOAD KONFIGURATION
 *  Hier kannst du E-Mail und ntfy Push einstellen!
 * ============================================================ */

return [
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
