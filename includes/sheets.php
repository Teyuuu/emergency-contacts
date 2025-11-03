<?php

function normalizeGoogleSheetUrl(string $url): string {
	$url = trim($url);
	if ($url === '') return '';
	
	if (strpos($url, 'docs.google.com/spreadsheets/') !== false) {
		// Prefer the export CSV endpoint when possible
		$gid = null;
		if (preg_match('/[?&]gid=([a-zA-Z0-9]+)/', $url, $m)) {
			$gid = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
		}
		$usesDExport = (strpos($url, '/spreadsheets/d/e/') === false);
		if ($usesDExport && preg_match('#/spreadsheets/d/([a-zA-Z0-9-_]+)#', $url, $m)) {
			$docId = preg_replace('/[^a-zA-Z0-9_-]/', '', $m[1]);
			if (empty($docId)) {
				return '';
			}
		$exp = 'https://docs.google.com/spreadsheets/d/' . $docId . '/export?format=csv';
		if ($gid !== null && preg_match('/^[a-zA-Z0-9]+$/', $gid)) {
			$exp .= '&gid=' . $gid;
		}
		// More aggressive cache-busting with microtime for better freshness
		return $exp . '&t=' . time() . '&_=' . mt_rand(10000, 99999) . '_' . substr(md5(microtime(true)), 0, 8);
		}
		
		$url = preg_replace('#/pubhtml#', '/pub', $url);
		if (strpos($url, 'output=csv') === false) {
			$url .= (strpos($url, '?') !== false ? '&' : '?') . 'output=csv';
		}
		// Remove any existing cache-busting parameters
		$url = preg_replace('/[?&](cachebust|t|_)=[^&]*/', '', $url);
		// More aggressive cache-busting with microtime for better freshness
		$url .= (strpos($url, '?') !== false ? '&' : '?') . 't=' . time() . '&_=' . mt_rand(10000, 99999) . '_' . substr(md5(microtime(true)), 0, 8);
	}
	return $url;
}

function normalizeLogoUrl(string $raw): string {
	$raw = trim($raw);
	if ($raw === '') return '';
	
	$originalRaw = $raw;
	
	// Handle IMAGE("url", ...) formulas
	if ($raw[0] === '=' && preg_match('/^=\s*IMAGE\((.+)\)\s*$/i', $raw, $m)) {
		$params = $m[1];
		// First param may be quoted or unquoted
		// Split by comma but respect quotes
		$parts = str_getcsv($params);
		$first = isset($parts[0]) ? trim($parts[0]) : '';
		$raw = trim($first, " \"'\t\n\r");
		error_log("IMAGE formula detected. Extracted URL: " . $raw);
	}
	
	// Extract file ID from various Google Drive link formats
	$fileId = null;
	$matchedFormat = 'none';
	
	// Format 1: https://drive.google.com/file/d/FILE_ID/view or /view?usp=sharing
	if (preg_match('#https?://drive\.google\.com/file/d/([a-zA-Z0-9_-]+)#', $raw, $m)) {
		$fileId = $m[1];
		$matchedFormat = 'file/d format';
	}
	// Format 2: https://drive.google.com/open?id=FILE_ID
	elseif (preg_match('#https?://drive\.google\.com/open\?.*id=([a-zA-Z0-9_-]+)#', $raw, $m)) {
		$fileId = $m[1];
		$matchedFormat = 'open format';
	}
	// Format 3: https://drive.google.com/uc?id=FILE_ID&export=download
	elseif (preg_match('#https?://drive\.google\.com/uc\?.*id=([a-zA-Z0-9_-]+)#', $raw, $m)) {
		$fileId = $m[1];
		$matchedFormat = 'uc format';
	}
	// Format 4: https://drive.google.com/thumbnail?id=FILE_ID
	elseif (preg_match('#https?://drive\.google\.com/thumbnail\?.*id=([a-zA-Z0-9_-]+)#', $raw, $m)) {
		$fileId = $m[1];
		$matchedFormat = 'thumbnail format';
	}
	
	// Log the processing
	error_log("Logo URL Processing - Original: " . $originalRaw . " | After formula handling: " . $raw . " | Matched format: " . $matchedFormat . " | File ID: " . ($fileId ?? 'null'));
	
	// If we found a file ID, convert to direct image link
	if ($fileId !== null) {
		// Use thumbnail method as primary - it's more reliable for publicly shared files
		// The thumbnail endpoint works better when files are shared with "Anyone with the link"
		$convertedUrl = 'https://drive.google.com/thumbnail?id=' . $fileId . '&sz=w1000';
		
		// Alternative: uc?export=view (kept for reference but thumbnail is more reliable)
		// $convertedUrl = 'https://drive.google.com/uc?export=view&id=' . $fileId;
		
		error_log("Logo URL Converted: " . $convertedUrl);
		return $convertedUrl;
	}
	
	error_log("Logo URL Not converted (returning original): " . $raw);
	return $raw;
}

function loadContactsFromGoogleSheet(string $csvUrl): array {
	require_once __DIR__ . '/security.php';
	
	if (!validateGoogleSheetUrl($csvUrl)) {
		error_log('SECURITY: Invalid Google Sheet URL attempted');
		return [];
	}
	
	$csvUrl = normalizeGoogleSheetUrl(trim($csvUrl));
	if ($csvUrl === '' || !validateGoogleSheetUrl($csvUrl)) {
		return [];
	}

	$data = '';
	// Prepare cURL options for reuse in retry
	$curlOptions = [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_TIMEOUT => 8,
		CURLOPT_USERAGENT => 'EmergencyContacts/1.0',
		CURLOPT_HTTPHEADER => [
			'Accept: text/csv, */*;q=0.8',
			'Cache-Control: no-cache, no-store, must-revalidate',
			'Pragma: no-cache',
			'Expires: 0',
		],
		CURLOPT_FRESH_CONNECT => true,
	];
	// Relax SSL verification only on localhost to support Windows/XAMPP
	$serverName = isset($_SERVER['SERVER_NAME']) ? strtolower((string)$_SERVER['SERVER_NAME']) : '';
	if ($serverName === 'localhost' || $serverName === '127.0.0.1') {
		$curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
		$curlOptions[CURLOPT_SSL_VERIFYHOST] = false;
	}
	
	// Prefer cURL if available for better compatibility
	if (function_exists('curl_init')) {
		$ch = curl_init($csvUrl);
		curl_setopt_array($ch, $curlOptions);
		$data = (string)curl_exec($ch);
		curl_close($ch);
	} else {
		$context = stream_context_create([
			'http' => [
				'timeout' => 8,
				'ignore_errors' => true,
				'header' => "Accept: text/csv, */*;q=0.8\r\nCache-Control: no-cache, no-store, must-revalidate\r\nPragma: no-cache\r\nExpires: 0\r\n",
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
		// Retry once with an extra cache-buster; published CSV endpoints can be briefly stale
		$retryUrl = preg_replace('/[?&](r|t|_)=[^&]*/', '', $csvUrl);
		$retryUrl .= ((strpos($retryUrl, '?') !== false) ? '&' : '?') . 'r=' . time() . '_' . mt_rand(10000, 99999) . '_' . substr(md5(microtime(true)), 0, 8);
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
		// fall through to normal CSV parsing with the retried payload in $data
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
		$logo = normalizeLogoUrl($assoc['logo url'] ?? ($assoc['logo'] ?? ''));
		$label = $assoc['label'] ?? '';
		
		if ($name === '' || $number === '') continue;
		$contacts[] = [
			'number' => $number,
			'name' => $name,
			'logo' => $logo !== '' ? $logo : 'images/bacoor-logo.jpg',
			'label' => $label !== '' ? $label : $name,
		];
	}

	return $contacts ?: [];
}


