<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Ainult POST lubatud']);
    exit();
}

$body = json_decode(file_get_contents('php://input'), true);

if (!$body) {
    http_response_code(400);
    echo json_encode(['error' => 'Vigane JSON']);
    exit();
}

$messages   = $body['messages']  ?? [];
$position   = $body['position']  ?? 'tarkvaraarendaja';
$level      = $body['level']     ?? 'mid';
$language   = $body['language']  ?? 'eesti';

// Süsteemprompt — intervjueerija roll
$systemPrompt = "Sa oled kogenud värbamisspetsialist ja tehniline intervjueerija, kes viib läbi {$level}-taseme {$position} ametikoha intervjuud.

Sinu ülesanne:
1. Esita kandidaadile üks küsimus korraga — tehniline ja/või käitumuslik.
2. Kuula vastust tähelepanelikult.
3. Anna lühike (1-2 lause) tagasiside vastusele ENNE järgmist küsimust.
4. Pärast 6–8 küsimust ütle, et intervjuu on lõppenud, ja anna struktureeritud lõpphinnang (tugevused, nõrkused, soovitus).
5. Ole professionaalne aga sõbralik.

Räägi alati " . ($language === 'eesti' ? "eesti keeles" : "inglise keeles") . ".

Alusta intervjuu sissejuhatusega ja esimese küsimusega.";

// Lisa süsteemisõnum massiivi algusesse
$apiMessages = array_merge(
    [['role' => 'system', 'content' => $systemPrompt]],
    $messages
);

// OpenAI API päring
$payload = json_encode([
    'model'                 => OPENAI_MODEL,
    'messages'              => $apiMessages,
    'max_completion_tokens' => 600,
]);

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY,
    ],
    CURLOPT_TIMEOUT        => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(['error' => 'cURL viga: ' . $curlError]);
    exit();
}

$data = json_decode($response, true);

if ($httpCode !== 200) {
    $errMsg = $data['error']['message'] ?? 'OpenAI API viga';
    http_response_code($httpCode);
    echo json_encode(['error' => $errMsg]);
    exit();
}

$reply = $data['choices'][0]['message']['content'] ?? '';
echo json_encode(['reply' => $reply]);
