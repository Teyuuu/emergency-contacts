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
	
	// Ensure proper encoding for mobile devices
	if (function_exists('mb_convert_encoding')) {
		$content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
	}
	
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

