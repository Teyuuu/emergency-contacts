<?php

function normalizeGoogleSheetUrl(string $url): string {
	$url = trim($url);
	if ($url === '') return '';
	// Accept common publish links and coerce to CSV output
	if (strpos($url, 'docs.google.com/spreadsheets/') !== false) {
		// Prefer the export CSV endpoint when possible
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
	$csvUrl = normalizeGoogleSheetUrl(trim($csvUrl));
	if ($csvUrl === '') {
		return [];
	}

	$data = '';
	// Prefer cURL if available for better compatibility
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
		// Relax SSL verification only on localhost to support Windows/XAMPP
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
		return [];
	}

	// If we accidentally received an HTML page (not CSV), bail out
	$snippet = substr(ltrim($data), 0, 200);
	if (stripos($snippet, '<!DOCTYPE html') !== false || stripos($snippet, '<html') !== false) {
		// Retry once with an extra cache-buster
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
		if ($data === '' || stripos($snippet, '<html') !== false) return [];
	}

	$lines = preg_split("/\r\n|\r|\n/", $data);
	if (!$lines || count($lines) === 0) {
		return [];
	}

	$rows = [];
	foreach ($lines as $line) {
		if ($line === '') continue;
		$rows[] = str_getcsv($line);
	}
	// Fallback: sometimes the published CSV can arrive as a single line
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
	// Remove possible UTF-8 BOM from the first header cell
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
		// Support either "Logo" or "Logo File" header names
		$logoRaw = $assoc['logo file'] ?? ($assoc['logo'] ?? '');
		$logo = normalizeLogoPath($logoRaw);
		$label = $assoc['label'] ?? '';
		
		if ($name === '' || $number === '') continue;
		$contacts[] = [
			'number' => $number,
			'name' => $name,
			'logo' => $logo,
			'label' => $label !== '' ? $label : $name,
		];
	}

	if (!$contacts) return [];

	// Return contacts in original order - first contact is automatically priority
	return $contacts;
}