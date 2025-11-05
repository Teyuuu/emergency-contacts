<?php

/**
 * Configuration - Set your Google Sheet published CSV URL
 */

define('SITE_URL',  'http://localhost/emergency-contacts/');
define('VERSION', '1.2');

/**
 * Global fetch key for manual cache refresh
 * Generated secure random key
 */
define('FETCH_KEY', '6881a6e86a89a471f683a8c4f29c39b7ae7167f5be008932acc0ac78ff358de0');

/**
 * Configuration - Set your Google Sheet published CSV URL
 */
$GOOGLE_SHEET_CSV_URL = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vQ8TWW6KM_rKOMdl15PmHBhqOIrfOO-SfKUTGOeigwfzFpqsaSIGhbiIiF_stxyM4IT1WfBxdIIkPaO/pub?gid=0&single=true&output=csv';

if (!empty($GOOGLE_SHEET_CSV_URL)) {
    // Must be from Google Sheets domain only
    if (strpos($GOOGLE_SHEET_CSV_URL, 'docs.google.com/spreadsheets/') === false) {
        // Invalid URL - set to empty to prevent access
        error_log('SECURITY: Invalid Google Sheet URL in config.php - must be from docs.google.com');
        $GOOGLE_SHEET_CSV_URL = '';
    }
    // Prevent dangerous protocols
    $dangerous = ['file://', 'ftp://', 'javascript:', 'data:', 'vbscript:', '\\\\'];
    foreach ($dangerous as $danger) {
        if (stripos($GOOGLE_SHEET_CSV_URL, $danger) !== false) {
            error_log('SECURITY: Dangerous protocol detected in Google Sheet URL');
            $GOOGLE_SHEET_CSV_URL = '';
            break;
        }
    }
}