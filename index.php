<?php
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (session_status() === PHP_SESSION_NONE) {
	// Configure secure session settings
	ini_set('session.cookie_httponly', '1');
	ini_set('session.cookie_samesite', 'Lax');
	ini_set('session.use_strict_mode', '1');
	
	// Regenerate session ID periodically to prevent session fixation
	if (isset($_SESSION['created'])) {
		$sessionLifeTime = 3600; // 1 hour
		if (time() - $_SESSION['created'] > $sessionLifeTime) {
			session_regenerate_id(true);
			$_SESSION['created'] = time();
		}
	} else {
		session_start();
		$_SESSION['created'] = time();
	}
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/contacts.php';

$_SESSION['contact_store'] = [];
$_SESSION['contact_store_time'] = time();
$contactHashes = [];
foreach ($contacts as $index => $contact) {
	$hash = hash('sha256', $contact['name'] . '|' . $contact['number'] . '|' . $index);
	$shortHash = substr($hash, 0, 16);
	$_SESSION['contact_store'][$shortHash] = $contact;
	$contactHashes[$index] = $shortHash;
} 

// SVG Icons
function getCallIcon() {
	return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;"><path d="M20.01 15.38C18.78 15.38 17.59 15.18 16.48 14.82C16.13 14.7 15.74 14.79 15.47 15.06L13.9 17.03C11.07 15.68 8.42 13.13 7.01 10.2L8.96 8.54C9.23 8.26 9.31 7.87 9.2 7.52C8.83 6.41 8.64 5.22 8.64 3.99C8.64 3.45 8.19 3 7.65 3H4.19C3.65 3 3 3.24 3 3.99C3 13.28 10.73 21 20.01 21C20.76 21 21 20.37 21 19.83V16.37C21 15.83 20.55 15.38 20.01 15.38Z" fill="currentColor"/></svg>';
}

function getMessengerIcon() {
	return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;"><path d="M12 2C6.477 2 2 6.477 2 12C2 14.42 3.08 16.57 4.82 18.03L3.5 22L7.97 20.68C9.24 20.89 10.59 21 12 21C17.523 21 22 16.523 22 11C22 5.477 17.523 1 12 1V2Z" fill="currentColor"/><path d="M12 8L8 12L12 16L16 12L12 8Z" fill="white"/></svg>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
	<title>Emergency Hotlines | City of Bacoor</title>

	<!-- Favicon -->
	<link rel="icon" type="image/x-icon" href="images/favicon.ico">
	<link rel="apple-touch-icon" href="images/favicon.ico">

	<!-- SEO and Indexing -->
	<meta name="robots" content="index, follow">
	<meta name="googlebot" content="index, follow">
	<meta name="author" content="E-GOV Department Office">
	<meta name="description" content="Access the official emergency contact numbers for Bacoor City. Call or save important hotlines for firefighters, police, BDRRMO, rescue units, and city services. Quick access to emergency services 24/7.">
	<meta name="keywords" content="Bacoor emergency Hotlines, Bacoor hotline, LGU Bacoor, Bacoor rescue, Bacoor police, Bacoor fire protection, BDRRMO, emergency numbers Bacoor, Bacoor City emergency, Cavite emergency contacts">
	
	<!-- Open Graph (Facebook, Messenger, WhatsApp) -->
	<meta property="og:type" content="website">
	<meta property="og:site_name" content="City of Bacoor">
	<meta property="og:title" content="Emergency Hotlines | City of Bacoor">
	<meta property="og:description" content="Quick access to Bacoor City emergency Hotlines — BDRRMO, police, fire protection, and more. Stay prepared and save lives.">
	<meta property="og:image" content="<?= SITE_URL ?>images/bacoor-logo.jpg">
	<meta property="og:image:alt" content="Bacoor City Logo">
	<meta property="og:url" content="<?= SITE_URL ?>">
	<meta property="og:locale" content="en_PH">

	<!-- Twitter Card -->
	<meta name="twitter:card" content="summary_large_image">
	<meta name="twitter:title" content="Emergency Hotlines | City of Bacoor">
	<meta name="twitter:description" content="Official Bacoor City emergency hotlines. Call or save important numbers easily. Available 24/7.">
	<meta name="twitter:image" content="<?= SITE_URL ?>images/bacoor-logo.jpg">
	<meta name="twitter:image:alt" content="Bacoor City Logo">
	
	<!-- Additional SEO -->
	<meta name="application-name" content="Bacoor Emergency Hotlines">
	<meta name="theme-color" content="#1e3a8a">
	<link rel="canonical" href="<?= SITE_URL ?>">

	<!-- Stylesheet -->
	<link href="https://fonts.googleapis.com/css?family=Rubik:300,400,500,600%7CIBM+Plex+Sans:300,400,500,600,700" rel="stylesheet">
	<link rel="stylesheet" href="css/contact.css?<?= VERSION ?>">
</head>
<body>
	<div class="container">
		<div class="header">
			<img src="images/logo-125.png" alt="Bacoor Logo" class="city-logo">
			<h1>Emergency Hotlines</h1>
			<small>E-GOV Department Office</small>
		</div>

		<!-- Priority Emergency -->
		<?php if (!empty($contacts)): 
			$priority = $contacts[0];
			// Get Messenger URL from config (contact-specific or default)
			$priorityMessenger = MESSENGER_URL;
			if (isset($MESSENGER_LINKS) && is_array($MESSENGER_LINKS) && isset($MESSENGER_LINKS[$priority['name']])) {
				$priorityMessenger = $MESSENGER_LINKS[$priority['name']];
			} elseif (!empty($priority['messenger'])) {
				$priorityMessenger = $priority['messenger'];
			}
			// Validate URL to prevent XSS/javascript: protocol attacks
			$priorityMessenger = validateUrl($priorityMessenger);
		?>
			<div class="contact-card priority-contact-card">
				<div class="priority-emergency-badge">🚨 PRIORITY EMERGENCY</div>
				<div class="contact-header">
					<img src="<?php echo htmlspecialchars($priority['logo']); ?>" alt="Logo" class="contact-logo">
					<div class="contact-info">
						<div class="contact-name">
							<?php echo htmlspecialchars($priority['label']); ?>
						</div>
						<div class="contact-number-display">
							<?php echo htmlspecialchars($priority['number']); ?>
						</div>
					</div>
				</div>
				<div class="button-group">
					<?php 
					$priorityNumbers = explode(',', $priority['number']);
					$priorityNumbers = array_map('trim', $priorityNumbers);
					$priorityNumbers = array_filter($priorityNumbers);
					
					if (count($priorityNumbers) > 1): 
					?>
						<button class="btn btn-call" onclick="openModal('<?php echo htmlspecialchars(json_encode($priorityNumbers), ENT_QUOTES); ?>', '<?php echo htmlspecialchars($priority['name'], ENT_QUOTES); ?>')">
							<?php echo getCallIcon(); ?>
							Call
						</button>
					<?php else: ?>
						<a class="btn btn-call" href="tel:<?php echo rawurlencode($priorityNumbers[0]); ?>" role="button">
							<?php echo getCallIcon(); ?>
							Call
						</a>
					<?php endif; ?>
					
					<a class="btn btn-messenger" href="<?php echo $priorityMessenger; ?>" target="_blank" rel="noopener noreferrer" role="button">
						<?php echo getMessengerIcon(); ?>
						Messenger
					</a>
				</div>
			</div>
		<?php endif; ?>

		<!-- Contact Cards Grid -->
		<?php if (!empty($contacts)): ?>
		<div class="contacts-grid priority-included">
			<?php foreach (array_slice($contacts, 1) as $offset => $contact): 
				$numbers = explode(',', $contact['number']);
				$numbers = array_map('trim', $numbers);
				$numbers = array_filter($numbers);
				$hasAlternatives = count($numbers) > 1;
				// Get Messenger URL from config (contact-specific or default)
				$contactMessenger = MESSENGER_URL;
				if (isset($MESSENGER_LINKS) && is_array($MESSENGER_LINKS) && isset($MESSENGER_LINKS[$contact['name']])) {
					$contactMessenger = $MESSENGER_LINKS[$contact['name']];
				} elseif (!empty($contact['messenger'])) {
					$contactMessenger = $contact['messenger'];
				}
				// Validate URL to prevent XSS/javascript: protocol attacks
				$contactMessenger = validateUrl($contactMessenger);
			?>
				<div class="contact-card">
					<div class="contact-header">
						<img src="<?php echo htmlspecialchars($contact['logo']); ?>" alt="Logo" class="contact-logo">
						<div class="contact-info">
							<div class="contact-name">
								<?php echo htmlspecialchars($contact['label']); ?>
							</div>
							<?php if ($hasAlternatives): ?>
								<div class="contact-alt-label">+ <?php echo count($numbers) - 1; ?> alternative</div>
							<?php endif; ?>
						</div>
					</div>
					<div class="button-group">
						<?php if ($hasAlternatives): ?>
							<button class="btn btn-call" onclick="openModal('<?php echo htmlspecialchars(json_encode($numbers), ENT_QUOTES); ?>', '<?php echo htmlspecialchars($contact['name'], ENT_QUOTES); ?>')">
								<?php echo getCallIcon(); ?>
								Call
							</button>
						<?php else: ?>
							<a class="btn btn-call" href="tel:<?php echo rawurlencode($numbers[0]); ?>" role="button">
								<?php echo getCallIcon(); ?>
								Call
							</a>
						<?php endif; ?>
						
						<a class="btn btn-messenger" href="<?php echo $contactMessenger; ?>" target="_blank" rel="noopener noreferrer" role="button">
							<?php echo getMessengerIcon(); ?>
							Messenger
						</a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php else: ?>
			<div class="save-all-section">
				<div class="save-all-text">No contacts found. Update your Google Sheet to populate this page.</div>
			</div>
		<?php endif; ?>

		<!-- Save All Contacts -->
		<?php if (!empty($contacts)): ?>
			<div class="save-all-section">
				<div class="save-all-text">Click if you want to</div>
				<a class="btn-save-all" href="includes/download.php?type=all" role="button">Save All Contacts</a>
			</div>
		<?php endif; ?>
	</div>

	<!-- Modal for selecting phone number -->
	<div id="numberModal" class="modal">
		<div class="modal-content">
			<button class="modal-close" onclick="closeModal()">&times;</button>
			<h2 class="modal-title" id="modalTitle">Select Number to Call</h2>
			<div class="modal-number-list" id="modalNumberList">
				<!-- Numbers will be populated here by JavaScript -->
			</div>
		</div>
	</div>

	<!-- JavaScript -->
	<script src="js/contact.js?<?= VERSION ?>"></script>
</body>
</html>