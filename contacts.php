<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sheets.php';

// Load contacts exclusively from Google Sheet; no preloaded defaults
$contacts = [];
if (!empty($GOOGLE_SHEET_CSV_URL)) {
	$contacts = loadContactsFromGoogleSheet($GOOGLE_SHEET_CSV_URL) ?: [];
}

