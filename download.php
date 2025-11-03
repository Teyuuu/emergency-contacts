<?php
require __DIR__ . '/contacts.php';

// Helper to emit a vCard file download
function outputVCard(string $filename, string $content): void {
	header('Content-Type: text/vcard; charset=utf-8');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
	header('Pragma: no-cache');
	echo $content;
	exit;
}

// Sanitize simple text for vCard
function sanitizeText(string $text): string {
	return str_replace(["\r", "\n"], [' ', ' '], trim($text));
}

$type = isset($_GET['type']) ? strtolower((string)$_GET['type']) : '';

if ($type === 'single') {
	$number = isset($_GET['number']) ? (string)$_GET['number'] : '';
	$name = isset($_GET['name']) ? (string)$_GET['name'] : '';

	if ($number === '' || $name === '') {
		http_response_code(400);
		echo 'Missing number or name.';
		exit;
	}

	$nameSafe = sanitizeText(urldecode($name));
	$numberSafe = sanitizeText(urldecode($number));

	$vcard = "BEGIN:VCARD\n" .
		"VERSION:3.0\n" .
		"FN:" . $nameSafe . "\n" .
		"TEL;TYPE=CELL:" . $numberSafe . "\n" .
		"END:VCARD\n";

	$filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', str_replace(' ', '_', $nameSafe)) . '.vcf';
	outputVCard($filename, $vcard);
}

if ($type === 'all') {
	if (empty($contacts)) {
		http_response_code(404);
		echo 'No contacts available.';
		exit;
	}
	$all = '';
	foreach ($contacts as $c) {
		$nameSafe = sanitizeText($c['name']);
		$numberSafe = sanitizeText($c['number']);
		$all .= "BEGIN:VCARD\n" .
			"VERSION:3.0\n" .
			"FN:" . $nameSafe . "\n" .
			"TEL;TYPE=CELL:" . $numberSafe . "\n" .
			"END:VCARD\n";
	}
	outputVCard('Emergency_Contacts_All.vcf', $all);
}

http_response_code(400);
echo 'Invalid request.';


