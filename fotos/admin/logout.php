<?php
require_once(__DIR__ . '/auth.php');
/* Session killen (auch wenn nicht eingeloggt – für Sauberkeit) */
fg_admin_logout();
/* NUR RELATIVER REDIRECT, damit kein Loop bei Reverse Proxy! */
header('Location: ./login.php?bye');
exit;
