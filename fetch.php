<?php
/**
 * Manual cache refresh endpoint
 * Usage: fetch.php?key={FETCH_KEY}
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/sheets.php';

header('Content-Type: application/json');

// Get the key from query parameter
$providedKey = $_GET['key'] ?? '';

// Validate the key
if (empty($providedKey) || $providedKey !== FETCH_KEY) {
	http_response_code(401);
	echo json_encode([
		'success' => false,
		'error' => 'Invalid or missing key'
	]);
	exit;
}

// Rate limiting check
if (!checkRateLimit('fetch_' . getClientIP(), 5, 60)) {
	http_response_code(429);
	echo json_encode([
		'success' => false,
		'error' => 'Too many requests. Please try again later.'
	]);
	exit;
}

try {
	// Check if Google Sheet URL is configured
	if (empty($GOOGLE_SHEET_CSV_URL)) {
		http_response_code(400);
		echo json_encode([
			'success' => false,
			'error' => 'Google Sheet URL not configured'
		]);
		exit;
	}

	// Check if cache exists and if there are changes
	$hasChanges = shouldRefreshCache($GOOGLE_SHEET_CSV_URL, true); // checkHash = true for manual refresh
	
	if (!$hasChanges && file_exists(CACHE_META_FILE)) {
		// No changes detected, cache is still valid
		$meta = @json_decode(file_get_contents(CACHE_META_FILE), true);
		$cachedContacts = loadContactsFromCache();
		
		http_response_code(200);
		echo json_encode([
			'success' => true,
			'message' => 'No changes detected. Cache is up to date.',
			'contacts_count' => $cachedContacts ? count($cachedContacts) : 0,
			'timestamp' => $meta['timestamp'] ?? time(),
			'data_hash' => $meta['data_hash'] ?? null,
			'cache_used' => true
		], JSON_PRETTY_PRINT);
		exit;
	}

	// Changes detected or cache doesn't exist - fetch fresh data
	$contacts = loadContactsFromGoogleSheet($GOOGLE_SHEET_CSV_URL, true);
	
	// Get cache metadata
	$meta = null;
	if (file_exists(CACHE_META_FILE)) {
		$meta = @json_decode(file_get_contents(CACHE_META_FILE), true);
	}

	http_response_code(200);
	echo json_encode([
		'success' => true,
		'message' => 'Cache refreshed successfully',
		'contacts_count' => count($contacts),
		'timestamp' => $meta['timestamp'] ?? time(),
		'data_hash' => $meta['data_hash'] ?? null,
		'cache_used' => false
	], JSON_PRETTY_PRINT);
	
} catch (Exception $e) {
	http_response_code(500);
	echo json_encode([
		'success' => false,
		'error' => 'Failed to refresh cache: ' . $e->getMessage()
	]);
}

