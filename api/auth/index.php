<?php
session_start();

$configPath = __DIR__ . '/config.php';
$config = [
    'client_id' => getenv('GITHUB_CLIENT_ID') ?: '',
    'client_secret' => getenv('GITHUB_CLIENT_SECRET') ?: '',
    'redirect_uri' => getenv('GITHUB_REDIRECT_URI') ?: 'https://rv-hard.arnovoyer.com/api/auth',
];

if (file_exists($configPath)) {
    $fileConfig = include $configPath;
    if (is_array($fileConfig)) {
        $config = array_merge($config, $fileConfig);
    }
}

$clientId = trim((string)($config['client_id'] ?? ''));
$clientSecret = trim((string)($config['client_secret'] ?? ''));
$redirectUri = trim((string)($config['redirect_uri'] ?? ''));

function originFromUrl($url) {
    $parts = parse_url((string)$url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }
    $origin = $parts['scheme'] . '://' . $parts['host'];
    if (!empty($parts['port'])) {
        $origin .= ':' . $parts['port'];
    }
    return $origin;
}

$targetOrigin = originFromUrl($redirectUri);

function renderFailure($message) {
        global $targetOrigin;
    header('Content-Type: text/html; charset=utf-8');
        $payload = json_encode((string)$message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $msg = 'authorization:github:error:' . $payload;
        $msgJson = json_encode($msg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $targetJson = json_encode($targetOrigin !== '' ? $targetOrigin : '*', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $safeText = htmlspecialchars((string)$message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<!doctype html><html><body><script>
if (window.opener) {
    try { window.opener.postMessage(' . $msgJson . ', ' . $targetJson . '); } catch (e) {}
    try { window.opener.postMessage(' . $msgJson . ', "*"); } catch (e) {}
    setTimeout(function () { window.close(); }, 250);
}
</script><p>' . $safeText . '</p></body></html>';
    exit;
}

if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
    renderFailure('OAuth ist nicht vollständig konfiguriert.');
}

if ($targetOrigin === '') {
    renderFailure('redirect_uri ist ungültig konfiguriert.');
}

if (!isset($_GET['code'])) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['github_oauth_state'] = $state;

    $authorizeUrl = 'https://github.com/login/oauth/authorize?' . http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'scope' => 'repo',
        'state' => $state,
    ]);

    header('Location: ' . $authorizeUrl);
    exit;
}

$incomingState = $_GET['state'] ?? '';
$sessionState = $_SESSION['github_oauth_state'] ?? '';
unset($_SESSION['github_oauth_state']);

if (!is_string($incomingState) || !is_string($sessionState) || $incomingState === '' || $sessionState === '' || !hash_equals($sessionState, $incomingState)) {
    renderFailure('Ungültiger OAuth-Status.');
}

$code = (string)($_GET['code'] ?? '');
if ($code === '') {
    renderFailure('Kein OAuth-Code erhalten.');
}

$payload = http_build_query([
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'code' => $code,
    'redirect_uri' => $redirectUri,
]);

$ch = curl_init('https://github.com/login/oauth/access_token');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_TIMEOUT => 20,
]);

$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false || $statusCode < 200 || $statusCode >= 300) {
    renderFailure('Token-Anfrage fehlgeschlagen: ' . ($curlError ?: ('HTTP ' . $statusCode)));
}

$data = json_decode($response, true);
$token = is_array($data) ? (string)($data['access_token'] ?? '') : '';

if ($token === '') {
    $errorText = is_array($data) ? (string)($data['error_description'] ?? ($data['error'] ?? 'Unbekannter Fehler')) : 'Ungültige Antwort von GitHub.';
    renderFailure('Kein Access Token: ' . $errorText);
}

header('Content-Type: text/html; charset=utf-8');
$payload = json_encode(['token' => $token], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$messageJson = json_encode('authorization:github:success:' . $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$targetJson = json_encode($targetOrigin, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
echo '<!doctype html><html><body><script>
if (window.opener) {
    try { window.opener.postMessage(' . $messageJson . ', ' . $targetJson . '); } catch (e) {}
    try { window.opener.postMessage(' . $messageJson . ', "*"); } catch (e) {}
    setTimeout(function () { window.close(); }, 250);
}
</script><p>Authentifizierung erfolgreich. Dieses Fenster kann geschlossen werden.</p></body></html>';
