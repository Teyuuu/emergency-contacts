<?php

function normalizeGoogleSheetUrl(string $url): string {
	$url = trim($url);
	if ($url === '') return '';
	// Accept common publish links and coerce to CSV output
	// Examples accepted:
	// - https://docs.google.com/spreadsheets/d/<ID>/pubhtml?gid=0&single=true
	// - https://docs.google.com/spreadsheets/d/e/<ID>/pubhtml?gid=0&single=true
	// - .../pub?gid=0&single=true&output=csv (already CSV)
	if (strpos($url, 'docs.google.com/spreadsheets/') !== false) {
		// Prefer the export CSV endpoint when possible
		$gid = null;
		if (preg_match('/[?&]gid=([^&]+)/', $url, $m)) {
			$gid = $m[1];
		}
		// Avoid mis-parsing published /d/e/<ID> URLs where the token after /d/ is the literal 'e'
		$usesDExport = (strpos($url, '/spreadsheets/d/e/') === false);
		if ($usesDExport && preg_match('#/spreadsheets/d/([a-zA-Z0-9-_]+)#', $url, $m)) {
			$docId = $m[1];
			$exp = 'https://docs.google.com/spreadsheets/d/' . $docId . '/export?format=csv';
			if ($gid !== null) $exp .= '&gid=' . $gid;
			// add cache buster to avoid stale responses
			return $exp . '&cachebust=' . time() . '_' . mt_rand(1000, 9999);
		}
		// Fallback to pub csv
		$url = preg_replace('#/pubhtml#', '/pub', $url);
		if (strpos($url, 'output=csv') === false) {
			$url .= (strpos($url, '?') !== false ? '&' : '?') . 'output=csv';
		}
		// Remove existing cachebust if any, then add fresh one
		$url = preg_replace('/[?&]cachebust=[^&]*/', '', $url);
		$url .= (strpos($url, '?') !== false ? '&' : '?') . 'cachebust=' . time() . '_' . mt_rand(1000, 9999);
	}
	return $url;
}

function normalizeLogoUrl(string $raw): string {
	$raw = trim($raw);
	if ($raw === '') return '';
	// Handle IMAGE("url", ...) formulas
	if ($raw[0] === '=' && preg_match('/^=\s*IMAGE\((.+)\)\s*$/i', $raw, $m)) {
		$params = $m[1];
		// First param may be quoted or unquoted
		// Split by comma but respect quotes
		$parts = str_getcsv($params);
		$first = isset($parts[0]) ? trim($parts[0]) : '';
		$raw = trim($first, " \"'\t\n\r");
	}
	// Convert Google Drive share links to direct view links
	if (preg_match('#https?://drive\.google\.com/file/d/([a-zA-Z0-9_-]+)#', $raw, $m)) {
		return 'https://drive.google.com/uc?export=view&id=' . $m[1];
	}
	return $raw;
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
		// Retry once with an extra cache-buster; published CSV endpoints can be briefly stale
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
	// Fallback: sometimes the published CSV can arrive as a single line
	// (headers immediately followed by the first row). In that case,
	// parse the entire payload and reconstruct rows in fixed-size chunks.
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
	// Remove possible UTF-8 BOM from the first header cell so keys match
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
		// Support either "Logo" or "Logo URL" header names
		$logo = normalizeLogoUrl($assoc['logo url'] ?? ($assoc['logo'] ?? ''));
		$label = $assoc['label'] ?? '';
		// Priority column is no longer needed - first contact in sheet is automatically priority
		if ($name === '' || $number === '') continue;
		$contacts[] = [
			'number' => $number,
			'name' => $name,
			'logo' => $logo !== '' ? $logo : 'images/bacoor-logo.jpg',
			'label' => $label !== '' ? $label : $name,
		];
	}

	if (!$contacts) return [];

	// Return contacts in original order - first contact is automatically priority
	return $contacts;
}


