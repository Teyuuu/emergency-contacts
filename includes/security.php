<?php

/**
 * Security helper functions
 */

// Sanitize input to prevent XSS (general purpose)
function sanitizeInput(string $input, int $maxLength = 1000): string {
	$input = trim($input);
	if (strlen($input) > $maxLength) {
		$input = substr($input, 0, $maxLength);
	}
	return htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
}

// Validate phone number format
function validatePhoneNumber(string $number): bool {
    // Allow digits, spaces, hyphens, parentheses, plus sign
    $pattern = '/^[\d\s\-\+\(\)]+$/';
    $cleaned = preg_replace('/[\s\-\(\)\+]/', '', $number);
    // Phone numbers should be 3-15 digits (supports emergency numbers like 191, 911, and short landlines like 321123)
    return preg_match($pattern, $number) && strlen($cleaned) >= 3 && strlen($cleaned) <= 15;
}

// Validate and sanitize name
function validateName(string $name) {
    $name = trim($name);
    if (strlen($name) < 1 || strlen($name) > 200) {
        return false;
    }
    // Allow letters, numbers, spaces, and basic punctuation
    if (!preg_match('/^[\p{L}\p{N}\s\-\'\.\(\)]+$/u', $name)) {
        return false;
    }
    return htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
}

// Rate limiting (simple in-memory, reset on server restart)
function checkRateLimit(string $identifier, int $maxRequests = 10, int $timeWindow = 60): bool {
    static $requests = [];
    $now = time();
    $key = $identifier . '_' . floor($now / $timeWindow);
    
    if (!isset($requests[$key])) {
        $requests[$key] = 0;
    }
    
    $requests[$key]++;
    
    // Clean old entries (keep only last 5 time windows)
    $oldestKey = $identifier . '_' . floor(($now - ($timeWindow * 5)) / $timeWindow);
    unset($requests[$oldestKey]);
    
    return $requests[$key] <= $maxRequests;
}

// Get client IP address with improved security
function getClientIP(): string {
    $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            $headerValue = $_SERVER[$key];
            // Sanitize header value to prevent injection
            $headerValue = preg_replace('/[^0-9a-fA-F:\.,\s]/', '', $headerValue);
            
            foreach (explode(',', $headerValue) as $ip) {
                $ip = trim($ip);
                // Remove port if present
                if (strpos($ip, ':') !== false && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
                    $ip = strtok($ip, ':');
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    // Fallback to REMOTE_ADDR and sanitize
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (filter_var($remoteAddr, FILTER_VALIDATE_IP) !== false) {
        return $remoteAddr;
    }
    
    return '0.0.0.0';
}

// Validate Google Sheet URL format
function validateGoogleSheetUrl(string $url): bool {
    if (empty($url)) {
        return false;
    }
    
    // Must be a valid URL
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    
    // Must be from Google Sheets domain
    if (strpos($url, 'docs.google.com/spreadsheets/') === false) {
        return false;
    }
    
    // Prevent access to local files or dangerous protocols
    $dangerous = ['file://', 'ftp://', 'javascript:', 'data:', 'vbscript:'];
    foreach ($dangerous as $danger) {
        if (stripos($url, $danger) !== false) {
            return false;
        }
    }
    
    return true;
}

