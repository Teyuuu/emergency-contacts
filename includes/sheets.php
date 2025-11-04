<?php

require_once __DIR__ . '/security.php';

// Cache directory for storing JSON data
define('CACHE_DIR', __DIR__ . '/../cache');
define('CACHE_FILE', CACHE_DIR . '/contacts.json');
define('CACHE_META_FILE', CACHE_DIR . '/cache_meta.json');
define('CACHE_CHECK_INTERVAL', 60);

/**
 * Get the base URL without cache-busting parameters for consistent hash comparison
 */
function getBaseGoogleSheetUrl(string $url): string {
	$url = trim($url);
	if ($url === '') return '';
	
	if (strpos($url, 'docs.google.com/spreadsheets/') !== false) {
		// Remove cache-busting parameters
		$url = preg_replace('/[?&]cachebust=[^&]*/', '', $url);
		$url = preg_replace('/[?&]r=[^&]*/', '', $url);
	}
	return $url;
}

/**
 * Ensure cache directory exists
 */
function ensureCacheDirectory(): bool {
	if (!file_exists(CACHE_DIR)) {
		if (!mkdir(CACHE_DIR, 0755, true)) {
			error_log("Failed to create cache directory: " . CACHE_DIR);
			return false;
		}
		$htaccess = "# Deny direct access to cache files\n";
		$htaccess .= "Order Deny,Allow\n";
		$htaccess .= "Deny from all\n";
		file_put_contents(CACHE_DIR . '/.htaccess', $htaccess);
	}
	return true;
}

/**
 * Load contacts from cache if available and valid
 */
function loadContactsFromCache(): ?array {
	if (!file_exists(CACHE_FILE) || !file_exists(CACHE_META_FILE)) {
		return null;
	}
	
	$meta = @json_decode(file_get_contents(CACHE_META_FILE), true);
	if (!$meta || !isset($meta['timestamp'])) {
		return null;
	}
	
	$contacts = @json_decode(file_get_contents(CACHE_FILE), true);
	return is_array($contacts) ? $contacts : null;
}

/**
 * Save contacts to cache
 */
function saveContactsToCache(array $contacts, string $dataHash): bool {
	if (!ensureCacheDirectory()) {
		return false;
	}
	
	$json = json_encode($contacts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	$meta = [
		'timestamp' => time(),
		'data_hash' => $dataHash,
		'contact_count' => count($contacts)
	];
	
	$jsonWritten = @file_put_contents(CACHE_FILE, $json);
	$metaWritten = @file_put_contents(CACHE_META_FILE, json_encode($meta));
	
	return $jsonWritten !== false && $metaWritten !== false;
}

/**
 * Check if cache needs to be refreshed by comparing data hash
 */
function shouldRefreshCache(string $csvUrl): bool {
	if (!file_exists(CACHE_META_FILE)) {
		return true;
	}
	
	$meta = @json_decode(file_get_contents(CACHE_META_FILE), true);
	if (!$meta || !isset($meta['timestamp'])) {
		return true;
	}
	
	$cacheAge = time() - $meta['timestamp'];
	if ($cacheAge < CACHE_CHECK_INTERVAL) {
		return false;
	}
	
	$baseUrl = getBaseGoogleSheetUrl($csvUrl);
	$checkUrl = normalizeGoogleSheetUrlWithoutCacheBust($baseUrl);
	$checkUrl .= (strpos($checkUrl, '?') !== false ? '&' : '?') . 'cachebust=' . time();
	
	$currentHash = getGoogleSheetDataHash($checkUrl);
	if ($currentHash === false) {
		return false;
	}
	
	return !isset($meta['data_hash']) || $meta['data_hash'] !== $currentHash;
}

/**
 * Get hash of Google Sheet data for change detection
 */
function getGoogleSheetDataHash(string $csvUrl) {
	$data = '';
	if (function_exists('curl_init')) {
		$ch = curl_init($csvUrl);
		$curlOptions = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT => 8,
			CURLOPT_USERAGENT => 'EmergencyContacts/1.0',
			CURLOPT_HTTPHEADER => [
				'Accept: text/csv, */*;q=0.8',
			],
		];
		$serverName = isset($_SERVER['SERVER_NAME']) ? strtolower((string)$_SERVER['SERVER_NAME']) : '';
		if ($serverName === 'localhost' || $serverName === '127.0.0.1') {
			$curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
			$curlOptions[CURLOPT_SSL_VERIFYHOST] = false;
		}
		curl_setopt_array($ch, $curlOptions);
		$data = (string)curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		
		if ($httpCode !== 200 || $data === '') {
			return false;
		}
	} else {
		$context = stream_context_create([
			'http' => [
				'timeout' => 8,
				'ignore_errors' => true,
			],
		]);
		$data = @file_get_contents($csvUrl, false, $context) ?: '';
		if ($data === '') {
			return false;
		}
	}
	return hash('sha256', $data);
}

/**
 * Normalize Google Sheet URL without cache-busting
 */
function normalizeGoogleSheetUrlWithoutCacheBust(string $url): string {
	$url = trim($url);
	if ($url === '') return '';
	
	if (strpos($url, 'docs.google.com/spreadsheets/') !== false) {
		$gid = null;
		if (preg_match('/[?&]gid=([^&]+)/', $url, $m)) {
			$gid = $m[1];
		}
		$usesDExport = (strpos($url, '/spreadsheets/d/e/') === false);
		if ($usesDExport && preg_match('#/spreadsheets/d/([a-zA-Z0-9-_]+)#', $url, $m)) {
			$docId = $m[1];
			$exp = 'https://docs.google.com/spreadsheets/d/' . $docId . '/export?format=csv';
			if ($gid !== null) $exp .= '&gid=' . $gid;
			return $exp;
		}
		$url = preg_replace('#/pubhtml#', '/pub', $url);
		if (strpos($url, 'output=csv') === false) {
			$url .= (strpos($url, '?') !== false ? '&' : '?') . 'output=csv';
		}
		$url = preg_replace('/[?&]cachebust=[^&]*/', '', $url);
	}
	return $url;
}

function normalizeGoogleSheetUrl(string $url): string {
	$url = trim($url);
	if ($url === '') return '';
	if (strpos($url, 'docs.google.com/spreadsheets/') !== false) {
		$gid = null;
		if (preg_match('/[?&]gid=([^&]+)/', $url, $m)) {
			$gid = $m[1];
		}
		$usesDExport = (strpos($url, '/spreadsheets/d/e/') === false);
		if ($usesDExport && preg_match('#/spreadsheets/d/([a-zA-Z0-9-_]+)#', $url, $m)) {
			$docId = $m[1];
			$exp = 'https://docs.google.com/spreadsheets/d/' . $docId . '/export?format=csv';
			if ($gid !== null) $exp .= '&gid=' . $gid;
			return $exp . '&cachebust=' . time() . '_' . mt_rand(1000, 9999);
		}
		$url = preg_replace('#/pubhtml#', '/pub', $url);
		if (strpos($url, 'output=csv') === false) {
			$url .= (strpos($url, '?') !== false ? '&' : '?') . 'output=csv';
		}
		$url = preg_replace('/[?&]cachebust=[^&]*/', '', $url);
		$url .= (strpos($url, '?') !== false ? '&' : '?') . 'cachebust=' . time() . '_' . mt_rand(1000, 9999);
	}
	return $url;
}

function normalizeLogoPath(string $raw, string $defaultLogo = 'images/default-logo.png'): string {
	$raw = trim($raw);
	if ($raw === '') return $defaultLogo;
	
	// If it's already a local path, validate and return it
	if (strpos($raw, 'http') !== 0) {
		// Clean the path
		$raw = str_replace('\\', '/', $raw);
		$raw = ltrim($raw, '/');
		
		// Security: prevent directory traversal
		if (strpos($raw, '..') !== false) {
			return $defaultLogo;
		}
		
		// Check if file exists in the project
		$fullPath = __DIR__ . '/../' . $raw;
		if (file_exists($fullPath)) {
			return $raw;
		} else {
			// File doesn't exist, return default
			error_log("Logo file not found: $raw");
			return $defaultLogo;
		}
	}
	
	// If it's a URL (external source), log warning and return default
	error_log("External logo URL detected (not allowed): $raw");
	return $defaultLogo;
}

function loadContactsFromGoogleSheet(string $csvUrl): array {
	$originalUrl = trim($csvUrl);
	if ($originalUrl === '') {
		return [];
	}
	
	// Try to load from cache first
	$cachedContacts = loadContactsFromCache();
	
	if ($cachedContacts !== null) {
		$meta = @json_decode(file_get_contents(CACHE_META_FILE), true);
		if ($meta && isset($meta['timestamp'])) {
			$cacheAge = time() - $meta['timestamp'];
			if ($cacheAge < 30) {
				return $cachedContacts;
			}
		}
	}
	
	if ($cachedContacts === null || shouldRefreshCache($originalUrl)) {
		$csvUrl = normalizeGoogleSheetUrl($originalUrl);
		if ($csvUrl === '') {
			if ($cachedContacts !== null) {
				return $cachedContacts;
			}
			return [];
		}

		$data = '';
		if (function_exists('curl_init')) {
			$ch = curl_init($csvUrl);
			$curlOptions = [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_CONNECTTIMEOUT => 5,
				CURLOPT_TIMEOUT => 8,
				CURLOPT_USERAGENT => 'EmergencyContacts/1.0',
				CURLOPT_HTTPHEADER => [
					'Accept: text/csv, */*;q=0.8',
					'Cache-Control: no-cache',
					'Pragma: no-cache',
				],
			];
			$serverName = isset($_SERVER['SERVER_NAME']) ? strtolower((string)$_SERVER['SERVER_NAME']) : '';
			if ($serverName === 'localhost' || $serverName === '127.0.0.1') {
				$curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
				$curlOptions[CURLOPT_SSL_VERIFYHOST] = false;
			}
			curl_setopt_array($ch, $curlOptions);
			$data = (string)curl_exec($ch);
			curl_close($ch);
		} else {
			$context = stream_context_create([
				'http' => [
					'timeout' => 8,
					'ignore_errors' => true,
				],
			]);
			$data = @file_get_contents($csvUrl, false, $context) ?: '';
		}
		if ($data === false || $data === '') {
			if ($cachedContacts !== null) {
				return $cachedContacts;
			}
			return [];
		}

		$snippet = substr(ltrim($data), 0, 200);
		if (stripos($snippet, '<!DOCTYPE html') !== false || stripos($snippet, '<html') !== false) {
			$retryUrl = $csvUrl . ((strpos($csvUrl, '?') !== false) ? '&' : '?') . 'r=' . mt_rand();
			if (function_exists('curl_init')) {
				$ch = curl_init($retryUrl);
				curl_setopt_array($ch, $curlOptions);
				$data = (string)curl_exec($ch);
				curl_close($ch);
			} else {
				$context = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
				$data = @file_get_contents($retryUrl, false, $context) ?: '';
			}
			$snippet = substr(ltrim((string)$data), 0, 200);
			if ($data === '' || stripos($snippet, '<html') !== false) {
				if ($cachedContacts !== null) {
					return $cachedContacts;
				}
				return [];
			}
		}

		$rawDataHash = hash('sha256', $data);

		$lines = preg_split("/\r\n|\r|\n/", $data);
		if (!$lines || count($lines) === 0) {
			return [];
		}

		$rows = [];
		foreach ($lines as $line) {
			if ($line === '') continue;
			$rows[] = str_getcsv($line);
		}
		if (count($rows) < 2) {
			$flat = str_getcsv($data);
			if ($flat && count($flat) >= 5) {
				$header = array_slice($flat, 0, 5);
				$rows = [$header];
				for ($i = 5; $i < count($flat); $i += 5) {
					$rows[] = array_slice($flat, $i, 5);
				}
			} else {
				return [];
			}
		}

		$header = array_map('trim', $rows[0]);
		if (isset($header[0])) {
			$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
		}
		$contacts = [];
		for ($i = 1; $i < count($rows); $i++) {
			$row = $rows[$i];
			if (count($row) === 1 && trim($row[0]) === '') continue;
			$assoc = [];
			foreach ($header as $idx => $key) {
				$assoc[strtolower($key)] = isset($row[$idx]) ? trim($row[$idx]) : '';
			}
			$name = $assoc['name'] ?? '';
			$number = $assoc['number'] ?? '';
			$logoRaw = $assoc['logo file'] ?? ($assoc['logo'] ?? '');
			$logo = normalizeLogoPath($logoRaw);
			$label = $assoc['label'] ?? '';
			
			if ($name === '' || $number === '') continue;
			
			$numberParts = explode(',', $number);
			$cleanedNumbers = [];
			foreach ($numberParts as $numPart) {
				$numPart = trim($numPart);
				if ($numPart !== '') {
					$cleanedNumbers[] = $numPart;
				}
			}
			
			if (empty($cleanedNumbers)) {
				error_log("Skipping contact '{$name}' - no phone numbers found");
				continue;
			}
			
			$finalNumber = implode(',', $cleanedNumbers);
			
			$contacts[] = [
				'number' => $finalNumber,
				'name' => $name,
				'logo' => $logo,
				'label' => $label !== '' ? $label : $name,
			];
		}

		if (!$contacts) {
			if ($cachedContacts !== null) {
				return $cachedContacts;
			}
			return [];
		}

		saveContactsToCache($contacts, $rawDataHash);
		return $contacts;
	}
	
	return $cachedContacts;
}