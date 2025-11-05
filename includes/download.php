<?php
if (ob_get_level()) {
	ob_clean();
}

if (session_status() === PHP_SESSION_NONE) {
	// Configure secure session settings
	ini_set('session.cookie_httponly', '1');
	ini_set('session.cookie_samesite', 'Lax');
	ini_set('session.use_strict_mode', '1');
	session_start();
}

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/contacts.php';
require_once __DIR__ . '/functions.php';

$clientIP = getClientIP();
if (!checkRateLimit($clientIP, 30, 60)) {
	http_response_code(429);
	header('Retry-After: 60');
	echo 'Too many requests. Please try again later.';
	exit;
}

$type = isset($_GET['type']) ? strtolower(trim((string)$_GET['type'])) : '';

if ($type === 'single') {
	$contactId = isset($_GET['id']) ? trim((string)$_GET['id']) : '';

	if ($contactId === '') {
		http_response_code(400);
		echo 'Missing contact ID.';
		exit;
	}

	// Validate contact ID format (should be 16-character hex string from hash)
	if (!preg_match('/^[a-f0-9]{16}$/i', $contactId) || strlen($contactId) !== 16) {
		http_response_code(400);
		echo 'Invalid contact ID format.';
		exit;
	}

	if (!isset($_SESSION['contact_store'][$contactId])) {
		http_response_code(404);
		echo 'Contact not found. Please try again.';
		exit;
	}

	if (isset($_SESSION['contact_store_time']) && (time() - $_SESSION['contact_store_time']) > 3600) {
		unset($_SESSION['contact_store']);
		unset($_SESSION['contact_store_time']);
		http_response_code(410);
		echo 'Session expired. Please refresh the page and try again.';
		exit;
	}

	$contact = $_SESSION['contact_store'][$contactId];
	$contactName = !empty($contact['label']) ? $contact['label'] : $contact['name'];
	$number = $contact['number'];
	$nameValid = validateName($contactName);
	if ($nameValid === false) {
		$contactName = $contact['name'];
		$nameValid = validateName($contactName);
		if ($nameValid === false) {
			http_response_code(400);
			echo 'Invalid name format.';
			exit;
		}
	}

	$nameSafe = sanitizeText($nameValid);
	
	$numberParts = explode(',', $number);
	$numberParts = array_map('trim', $numberParts);
	$numberParts = array_filter($numberParts);
	
	$vcard = "BEGIN:VCARD\r\n" .
		"VERSION:3.0\r\n" .
		"FN:" . $nameSafe . "\r\n";
	
	foreach ($numberParts as $numPart) {
		$numberSafe = sanitizeText($numPart);
		$numberType = (strlen(preg_replace('/[^0-9]/', '', $numberSafe)) >= 8 && strlen(preg_replace('/[^0-9]/', '', $numberSafe)) <= 10) ? 'WORK' : 'CELL';
		$vcard .= "TEL;TYPE=" . $numberType . ":" . $numberSafe . "\r\n";
	}
	
	$vcard .= "END:VCARD\r\n";

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
		$contactName = !empty($c['label']) ? $c['label'] : $c['name'];
		$nameValid = validateName($contactName);
		if ($nameValid === false) {
			$contactName = $c['name'];
			$nameValid = validateName($contactName);
		}
		$nameSafe = $nameValid !== false ? sanitizeText($nameValid) : sanitizeText($contactName);
		
		$numberParts = explode(',', $c['number']);
		$numberParts = array_map('trim', $numberParts);
		$numberParts = array_filter($numberParts);
		
		$all .= "BEGIN:VCARD\r\n" .
			"VERSION:3.0\r\n" .
			"FN:" . $nameSafe . "\r\n";
		
		foreach ($numberParts as $numPart) {
			$numberSafe = sanitizeText($numPart);
			$numberType = (strlen(preg_replace('/[^0-9]/', '', $numberSafe)) >= 8 && strlen(preg_replace('/[^0-9]/', '', $numberSafe)) <= 10) ? 'WORK' : 'CELL';
			$all .= "TEL;TYPE=" . $numberType . ":" . $numberSafe . "\r\n";
		}
		
		$all .= "END:VCARD\r\n";
	}
	outputVCard('Emergency_Contacts_All.vcf', $all);
}

http_response_code(400);
echo 'Invalid request.';

