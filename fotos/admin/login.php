<?php
/* =============================================================
 *  SBS GALERIE ADMIN – LOGIN SEITE (STANDALONE, KEIN LOOP!)
 * ============================================================= */

require_once(__DIR__ . '/auth2.php');

/* Eingeloggt? Dann auf Admin-Hauptseite (index.php).
 * Das ist KEIN Loop, weil index.php nicht auf login.php verlinkt!
 * (Index.php rendert das Formular INLINE wenn nicht eingeloggt.)
 */
if (fg2_logged()) {
    header('Location: ./index.php');
    exit;
}

/* Session Msg holen und löschen (flash message) */
$msg  = ''; $mType = 'err';
if (!empty($_SESSION['fg2_msg']))  { $msg = $_SESSION['fg2_msg'];  $mType = ($_SESSION['fg2_mtype'] ?? 'err'); unset($_SESSION['fg2_msg'], $_SESSION['fg2_mtype']); }

/* Einfache Form ausgeben (100% ohne Weiterleitung!) */
fg2_render_login_page($msg, $mType);
exit;
