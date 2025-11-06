<?php

/**
 * Configuration - Set your Google Sheet published CSV URL
 */

define('SITE_URL',  'http://localhost/emergency-contacts/');
define('VERSION', '1.2');

/**
 * Facebook Messenger link (default/fallback)
 * Replace with your Facebook page username or page ID
 * Format: https://m.me/your-page-username
 * Or: https://www.facebook.com/messages/t/your-page-id
 */
define('MESSENGER_URL', 'https://m.me/194115273982761');

/**
 * Contact-specific Messenger links
 * Map contact names to their Messenger URLs
 * Format: 'CONTACT_NAME' => 'https://m.me/page-username'
 * 
 * Use the exact contact name from your Google Sheet (case-sensitive)
 * If a contact name is not found here, it will use the default MESSENGER_URL above
 * 
 * NOTE: BACOOR EMERGENCY and BDDRMO (BACOOR DISASTER RISK REDUCTION AND MANAGEMENT OFFICE) 
 * are automatically merged into a single contact. The merged contact will use the BDDRMO 
 * Messenger link if available, or the Emergency link if BDDRMO is not found.
 * 
 * Example:
 * $MESSENGER_LINKS = [
 *     'EMERGENCY' => 'https://m.me/emergencypage',
 *     'PNP' => 'https://m.me/pnppage',
 *     'BFP' => 'https://m.me/bfppage',
 * ];
 */
$MESSENGER_LINKS = [
	// Add your contact-specific Messenger links here
	// Format: 'Contact Name' => 'https://m.me/your-page-username'
	
	// Merged contact: BACOOR EMERGENCY (merged with BDDRMO)
	// The BDDRMO Messenger link will be used for the merged Emergency contact
	'BDDRMO' => 'https://m.me/176929895684568',
	'BACOOR EMERGENCY' => 'https://m.me/176929895684568', // Also mapped for clarity
	
	// Other contacts
    'PNP' => 'https://m.me/475067236359439',
    'BFP' => 'https://m.me/123679242715544',
    'CIO' => 'https://m.me/194115273982761',
    'Other Bacoor City Hotlines' => 'https://m.me/194115273982761',
    'NATIONAL' => 'https://m.me/194115273982761',
];

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