<?php
// Prevent any output before headers
if (ob_get_level()) {
	ob_clean();
}

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/contacts.php';
require_once __DIR__ . '/includes/functions.php';

// Rate limiting - prevent abuse
$clientIP = getClientIP();
if (!checkRateLimit($clientIP, 30, 60)) {
	http_response_code(429);
	header('Retry-After: 60');
	echo 'Too many requests. Please try again later.';
	exit;
}

$type = isset($_GET['type']) ? strtolower(trim((string)$_GET['type'])) : '';

if ($type === 'single') {
	$number = isset($_GET['number']) ? trim((string)$_GET['number']) : '';
	$name = isset($_GET['name']) ? trim((string)$_GET['name']) : '';

	if ($number === '' || $name === '') {
		http_response_code(400);
		echo 'Missing number or name.';
		exit;
	}

	// Validate inputs
	$nameValid = validateName($name);
	if ($nameValid === false) {
		http_response_code(400);
		echo 'Invalid name format.';
		exit;
	}

	if (!validatePhoneNumber($number)) {
		http_response_code(400);
		echo 'Invalid phone number format.';
		exit;
	}

	$nameSafe = sanitizeText($nameValid);
	$numberSafe = sanitizeText($number);
	
	// Detect if number is landline (Philippine format: typically starts with specific area codes or has less digits)
	// For better compatibility, we'll use WORK for longer numbers (likely landlines) and CELL for others
	$numberType = (strlen(preg_replace('/[^0-9]/', '', $numberSafe)) >= 8 && strlen(preg_replace('/[^0-9]/', '', $numberSafe)) <= 10) ? 'WORK' : 'CELL';
	
	$vcard = "BEGIN:VCARD\r\n" .
		"VERSION:3.0\r\n" .
		"FN:" . $nameSafe . "\r\n" .
		"TEL;TYPE=" . $numberType . ":" . $numberSafe . "\r\n" .
		"END:VCARD\r\n";

	$filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', str_replace(' ', '_', $nameSafe)) . '.vcf';
	outputVCard($filename, $vcard);
}

if ($type === 'all') {
	if (empty($contacts)) {
		http_response_code(404);
		echo 'No contacts available.';
		exit;
	}
	$all = '';
	foreach ($contacts as $c) {
		$nameSafe = sanitizeText($c['name']);
		$numberSafe = sanitizeText($c['number']);
		
		// Detect if number is landline (Philippine format: typically starts with specific area codes or has less digits)
		$numberType = (strlen(preg_replace('/[^0-9]/', '', $numberSafe)) >= 8 && strlen(preg_replace('/[^0-9]/', '', $numberSafe)) <= 10) ? 'WORK' : 'CELL';
		
		$all .= "BEGIN:VCARD\r\n" .
			"VERSION:3.0\r\n" .
			"FN:" . $nameSafe . "\r\n" .
			"TEL;TYPE=" . $numberType . ":" . $numberSafe . "\r\n" .
			"END:VCARD\r\n";
	}
	outputVCard('Emergency_Contacts_All.vcf', $all);
}

http_response_code(400);
echo 'Invalid request.';


