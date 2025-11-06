<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sheets.php';

// Load contacts exclusively from Google Sheet; no preloaded defaults
$contacts = [];
if (!empty($GOOGLE_SHEET_CSV_URL)) {
	$contacts = loadContactsFromGoogleSheet($GOOGLE_SHEET_CSV_URL) ?: [];
}

// Merge BACOOR EMERGENCY and BACOOR DISASTER RISK REDUCTION AND MANAGEMENT OFFICE
$emergencyIndex = null;
$bddrmoIndex = null;

foreach ($contacts as $index => $contact) {
	$nameUpper = strtoupper(trim($contact['name']));
	
	// Find BACOOR EMERGENCY
	if ($emergencyIndex === null && (
		$nameUpper === 'BACOOR EMERGENCY' || 
		$nameUpper === 'EMERGENCY' ||
		strpos($nameUpper, 'BACOOR EMERGENCY') !== false
	)) {
		$emergencyIndex = $index;
	}
	
	// Find BACOOR DISASTER RISK REDUCTION AND MANAGEMENT OFFICE
	if ($bddrmoIndex === null && (
		$nameUpper === 'BACOOR DISASTER RISK REDUCTION AND MANAGEMENT OFFICE' ||
		$nameUpper === 'BDDRMO' ||
		strpos($nameUpper, 'DISASTER RISK') !== false ||
		strpos($nameUpper, 'BDDRMO') !== false
	)) {
		$bddrmoIndex = $index;
	}
}

// If both contacts found, merge them
if ($emergencyIndex !== null && $bddrmoIndex !== null) {
	$emergency = $contacts[$emergencyIndex];
	$bddrmo = $contacts[$bddrmoIndex];
	
	// Combine phone numbers (remove duplicates)
	$emergencyNumbers = explode(',', $emergency['number']);
	$bddrmoNumbers = explode(',', $bddrmo['number']);
	$allNumbers = array_merge($emergencyNumbers, $bddrmoNumbers);
	$allNumbers = array_map('trim', $allNumbers);
	$allNumbers = array_unique($allNumbers); // Remove duplicates
	$allNumbers = array_filter($allNumbers); // Remove empty
	
	// Prefer BDDRMO Messenger link if available, otherwise use Emergency's
	$messengerLink = $emergency['messenger'] ?? '';
	if (isset($MESSENGER_LINKS) && is_array($MESSENGER_LINKS)) {
		// Check for BDDRMO Messenger link in config
		if (isset($MESSENGER_LINKS['BDDRMO']) && !empty($MESSENGER_LINKS['BDDRMO'])) {
			$messengerLink = $MESSENGER_LINKS['BDDRMO'];
		} elseif (isset($MESSENGER_LINKS[$bddrmo['name']]) && !empty($MESSENGER_LINKS[$bddrmo['name']])) {
			$messengerLink = $MESSENGER_LINKS[$bddrmo['name']];
		} elseif (!empty($bddrmo['messenger'] ?? '')) {
			$messengerLink = $bddrmo['messenger'];
		} elseif (!empty($emergency['messenger'] ?? '')) {
			$messengerLink = $emergency['messenger'];
		}
	}
	
	// Merge into emergency contact (keep emergency's name, label, logo)
	$contacts[$emergencyIndex] = [
		'number' => implode(',', $allNumbers),
		'name' => $emergency['name'],
		'logo' => $emergency['logo'],
		'label' => $emergency['label'],
		'messenger' => $messengerLink,
	];
	
	// Remove BDDRMO contact
	unset($contacts[$bddrmoIndex]);
	$contacts = array_values($contacts); // Re-index array
}

