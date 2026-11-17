<?php
// Receives website enquiries and emails them to the Domestic Glass team.
header('Content-Type: application/json');

$TO = 'hello@domesticglass.in';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Hidden "website" field: real visitors leave it empty, spam bots fill it.
if (!empty($_POST['website'])) {
    echo json_encode(['ok' => true]);
    exit;
}

function clean($key, $max = 2000) {
    $v = isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
    $v = str_replace(["\r", "\0"], '', $v);
    return mb_substr($v, 0, $max);
}

$fields = [
    'Name'         => clean('name', 200),
    'Phone'        => clean('phone', 50),
    'Email'        => clean('email', 200),
    'Project type' => clean('type', 200),
    'Location'     => clean('location', 200),
    'Architect'    => clean('architect', 200),
    'Firm'         => clean('firm', 200),
    'Message'      => clean('message', 5000),
];
$source = clean('source', 100) ?: 'Website';

if ($fields['Name'] === '' || $fields['Phone'] === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Name and phone are required']);
    exit;
}

$body = "New enquiry from the website ($source form)\n\n";
foreach ($fields as $label => $value) {
    if ($value !== '') {
        $body .= str_pad($label . ':', 14) . $value . "\n";
    }
}
$body .= "\nSent: " . date('d M Y, h:i A') . " (server time)\n";
$body .= "Page: " . clean('page', 300) . "\n";

$host = preg_replace('/^www\./', '', preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'domesticglass.in'));
$subjectName = preg_replace('/[^\p{L}\p{N} .\-]/u', '', $fields['Name']);
$subject = '=?UTF-8?B?' . base64_encode("New enquiry: $subjectName ({$fields['Project type']})") . '?=';

$headers  = "From: Domestic Glass Website <noreply@$host>\r\n";
$replyTo = filter_var($fields['Email'], FILTER_VALIDATE_EMAIL);
if ($replyTo) {
    $headers .= "Reply-To: $replyTo\r\n";
}
$headers .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";

$sent = mail($TO, $subject, $body, $headers, "-fnoreply@$host");

if ($sent) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not send email']);
}
