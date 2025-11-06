<?php

// Helper to emit a vCard file download
function outputVCard(string $filename, string $content): void {
	// Clear any previous output
	if (ob_get_level()) {
		ob_clean();
	}
	
	// Set proper headers for mobile phone compatibility and secure downloads
	header('Content-Type: text/vcard; charset=utf-8');
	
	// Use proper filename encoding for Content-Disposition (RFC 5987)
	// This helps browsers handle the filename securely
	$encodedFilename = rawurlencode($filename);
	header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"; filename*=UTF-8\'\'' . $encodedFilename);
	
	header('Content-Transfer-Encoding: binary');
	header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
	header('Pragma: no-cache');
	header('Expires: 0');
	header('Content-Length: ' . strlen($content));
	
	// Additional headers for secure downloads
	header('X-Content-Type-Options: nosniff');
	header('X-Download-Options: noopen');
	
	echo $content;
	exit;
}

// Sanitize simple text for vCard
function sanitizeText(string $text): string {
	$text = trim($text);
	// Remove control characters and normalize line breaks
	$text = str_replace(["\r", "\n"], [' ', ' '], $text);
	// Remove any remaining control characters
	$text = preg_replace('/[\x00-\x1F\x7F]/', '', $text);
	// Limit length to prevent abuse
	if (strlen($text) > 500) {
		$text = substr($text, 0, 500);
	}
	return $text;
}

// Clean phone number by removing carrier labels
// This ensures clean phone numbers for all devices (iOS, Android, etc.)
function cleanPhoneNumber(string $number): string {
	$number = trim($number);
	
	// If number contains pipe separator (format: number|label), extract only the number part
	if (strpos($number, '|') !== false) {
		$parts = explode('|', $number, 2);
		$number = trim($parts[0]); // Take only the number part before the pipe
	}
	
	// Remove common carrier labels (case-insensitive) from anywhere in the string
	$carriers = ['Globe', 'Smart', 'Sun', 'TNT', 'TM', 'DITO', 'PLDT'];
	foreach ($carriers as $carrier) {
		// Remove carrier name at the end (with or without space)
		$number = preg_replace('/\s*' . preg_quote($carrier, '/') . '\s*$/i', '', $number);
		// Remove carrier name at the beginning
		$number = preg_replace('/^\s*' . preg_quote($carrier, '/') . '\s*/i', '', $number);
		// Remove carrier name anywhere in the string (in case it's embedded)
		$number = preg_replace('/\s*' . preg_quote($carrier, '/') . '\s*/i', '', $number);
	}
	
	// Remove parentheses - they should not be in phone numbers
	$number = str_replace(['(', ')'], '', $number);
	
	// Remove any remaining non-digit characters except allowed formatting (spaces, hyphens, plus)
	// Keep only digits, spaces, hyphens, and plus sign for phone formatting
	$number = preg_replace('/[^\d\s\-\+]/', '', $number);
	
	// Extract only digits to count and format properly
	$digitsOnly = preg_replace('/[^\d]/', '', $number);
	$digitCount = strlen($digitsOnly);
	
	// Format 12-digit numbers as "0977 752 0819" (no parentheses)
	if ($digitCount === 12) {
		// Format: XXXX XXX XXXX (first 4 digits, space, next 3 digits, space, last 4 digits)
		$number = substr($digitsOnly, 0, 4) . ' ' . substr($digitsOnly, 4, 3) . ' ' . substr($digitsOnly, 7, 4);
	} else {
		// For other lengths, clean up multiple spaces
		$number = preg_replace('/\s+/', ' ', $number);
		$number = trim($number);
	}
	
	return trim($number);
}

// Format name for vCard N field (vCard 3.0 standard - compatible with iOS, Android, and all devices)
// vCard N format: N:LastName;FirstName;MiddleName;Prefix;Suffix
// Both N: and FN: fields are included for maximum compatibility across all platforms
function formatNameForVCard(string $fullName): string {
	$fullName = trim($fullName);
	if (empty($fullName)) {
		return ';;;;';
	}
	
	// Split name into parts (assuming format: FirstName LastName or just Name)
	$parts = preg_split('/\s+/', $fullName, 2);
	
	if (count($parts) === 2) {
		// Has first and last name: N:LastName;FirstName;MiddleName;Prefix;Suffix
		return $parts[1] . ';' . $parts[0] . ';;;';
	} else {
		// Single name - put in last name field (iPhone prefers this)
		return $fullName . ';;;;';
	}
}

