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
	$nameNField = formatNameForVCard($nameSafe);
	
	$numberParts = explode(',', $number);
	$numberParts = array_map('trim', $numberParts);
	$numberParts = array_filter($numberParts);
	
	$vcard = "BEGIN:VCARD\r\n" .
		"VERSION:3.0\r\n" .
		"N:" . $nameNField . "\r\n" .
		"FN:" . $nameSafe . "\r\n";
	
	foreach ($numberParts as $numPart) {
		// Clean phone number to remove carrier labels (Globe, Smart, etc.)
		$cleanedNumber = cleanPhoneNumber($numPart);
		$numberSafe = sanitizeText($cleanedNumber);
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
	
	// Find BACOOR EMERGENCY contact (first contact by default)
	$bacoorEmergency = null;
	$bacoorEmergencyIndex = -1;
	
	foreach ($contacts as $index => $c) {
		$nameUpper = strtoupper(trim($c['name'] ?? ''));
		$labelUpper = strtoupper(trim($c['label'] ?? ''));
		
		// Find BACOOR EMERGENCY contact
		if ($bacoorEmergency === null && (
			$nameUpper === 'BACOOR EMERGENCY' || 
			$nameUpper === 'EMERGENCY' ||
			$labelUpper === 'BACOOR EMERGENCY' ||
			strpos($nameUpper, 'BACOOR EMERGENCY') !== false ||
			strpos($labelUpper, 'BACOOR EMERGENCY') !== false
		)) {
			$bacoorEmergency = $c;
			$bacoorEmergencyIndex = $index;
			break;
		}
	}
	
	// If BACOOR EMERGENCY not found, use first contact
	if ($bacoorEmergency === null && !empty($contacts)) {
		$bacoorEmergency = $contacts[0];
		$bacoorEmergencyIndex = 0;
	}
	
	if ($bacoorEmergency === null || empty($bacoorEmergency['name'])) {
		http_response_code(404);
		echo 'BACOOR EMERGENCY contact not found.';
		exit;
	}
	
	// Get BACOOR EMERGENCY contact details
	$contactName = !empty($bacoorEmergency['label']) ? $bacoorEmergency['label'] : $bacoorEmergency['name'];
	$nameValid = validateName($contactName);
	if ($nameValid === false) {
		$contactName = $bacoorEmergency['name'];
		$nameValid = validateName($contactName);
	}
	
	if ($nameValid === false) {
		http_response_code(400);
		echo 'Invalid contact name format.';
		exit;
	}
	
	$nameSafe = sanitizeText($nameValid);
	$nameNField = formatNameForVCard($nameSafe);
	
	// Format N: field for iOS (exactly 5 semicolon-separated parts)
	$nParts = explode(';', $nameNField);
	while (count($nParts) < 5) {
		$nParts[] = '';
	}
	$nameNField = implode(';', array_slice($nParts, 0, 5));
	
	// Collect all phone numbers from all contacts
	$phoneNumbersWithLabels = [];
	$mobileNumber = null;
	
	// Process all contacts to collect phone numbers with labels
	foreach ($contacts as $index => $c) {
		if (empty($c['number'])) {
			continue;
		}
		
		// Determine default label based on contact
		$defaultLabel = ($index === $bacoorEmergencyIndex) 
			? 'BACOOR EMERGENCY' 
			: (!empty($c['label']) ? $c['label'] : (!empty($c['name']) ? $c['name'] : 'Emergency Contact'));
		$defaultLabel = sanitizeText($defaultLabel);
		
		$numberParts = explode(',', $c['number']);
		$numberParts = array_map('trim', $numberParts);
		$numberParts = array_filter($numberParts);
		
		foreach ($numberParts as $numPart) {
			$labelToUse = $defaultLabel;
			
			// Extract label from number if present (format: "number | label")
			if (strpos($numPart, '|') !== false) {
				$parts = explode('|', $numPart, 2);
				$numPart = trim($parts[0]);
				$extractedLabel = trim($parts[1] ?? '');
				if (!empty($extractedLabel)) {
					$labelToUse = sanitizeText($extractedLabel);
				}
			}
			
			$cleanedNumber = cleanPhoneNumber($numPart);
			$numberSafe = sanitizeText($cleanedNumber);
			
			if (empty($numberSafe)) {
				continue;
			}
			
			$digitsOnly = preg_replace('/[^0-9]/', '', $numberSafe);
			
			// Short numbers (like 161) are treated as mobile
			if (strlen($digitsOnly) <= 3) {
				if ($mobileNumber === null) {
					$mobileNumber = $numberSafe;
				}
			} else {
				// Avoid duplicates by number
				$isDuplicate = false;
				foreach ($phoneNumbersWithLabels as $existing) {
					if ($existing['number'] === $numberSafe) {
						$isDuplicate = true;
						break;
					}
				}
				if (!$isDuplicate) {
					$phoneNumbersWithLabels[] = [
						'number' => $numberSafe,
						'label' => $labelToUse
					];
				}
			}
		}
	}
	
	$vcard = "BEGIN:VCARD\r\n" .
		"VERSION:3.0\r\n" .
		"FN:" . $nameSafe . "\r\n" .
		"N:" . $nameNField . "\r\n";
	
	// Add mobile number with custom label
	if ($mobileNumber !== null) {
		$mobileLabelEscaped = str_replace('"', '\\"', $nameSafe);
		$vcard .= "TEL;TYPE=\"" . $mobileLabelEscaped . "\":" . $mobileNumber . "\r\n";
	}
	
	// Add all other numbers with their labels
	foreach ($phoneNumbersWithLabels as $phoneData) {
		$labelEscaped = str_replace('"', '\\"', $phoneData['label']);
		$vcard .= "TEL;TYPE=\"" . $labelEscaped . "\":" . $phoneData['number'] . "\r\n";
	}
	
	$vcard .= "END:VCARD\r\n";
	
	outputVCard('Emergency_Contacts_All.vcf', $vcard);
}

http_response_code(400);
echo 'Invalid request.';

