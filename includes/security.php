<?php

/**
 * Security helper functions
 */

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

// Validate and sanitize URL for safe use in href attributes
function validateUrl(string $url, array $allowedSchemes = ['http', 'https']): string {
    if (empty($url)) {
        return '#';
    }
    
    // Remove any whitespace
    $url = trim($url);
    
    // Check for dangerous protocols
    $dangerous = ['javascript:', 'data:', 'vbscript:', 'file://', 'ftp://'];
    foreach ($dangerous as $danger) {
        if (stripos($url, $danger) === 0) {
            error_log("SECURITY: Dangerous URL scheme detected: $url");
            return '#';
        }
    }
    
    // Validate URL format
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        // Check if it's a relative URL
        if (strpos($url, '/') === 0 || strpos($url, './') === 0) {
            // Allow relative URLs but validate they don't contain dangerous patterns
            if (strpos($url, '..') !== false || strpos($url, chr(0)) !== false) {
                error_log("SECURITY: Dangerous relative URL detected: $url");
                return '#';
            }
            return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        }
        error_log("SECURITY: Invalid URL format: $url");
        return '#';
    }
    
    // Parse URL to check scheme
    $parsed = parse_url($url);
    if ($parsed === false || !isset($parsed['scheme'])) {
        error_log("SECURITY: URL parse failed: $url");
        return '#';
    }
    
    // Check if scheme is allowed
    if (!in_array(strtolower($parsed['scheme']), $allowedSchemes, true)) {
        error_log("SECURITY: Disallowed URL scheme: " . $parsed['scheme']);
        return '#';
    }
    
    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}

