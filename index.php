<?php 
// Prevent browser caching of this page to ensure fresh data
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

require __DIR__ . '/includes/contacts.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
	<title>Emergency Contacts</title>
	<link rel="stylesheet" href="css/contact.css?<?php echo time(); ?>">
</head>
<body>
	<div class="container">
		<div class="header">
			<img src="images/bacoor-logo.jpg" alt="Bacoor Logo" class="city-logo">
			<h1>Emergency Contacts</h1>
		</div>

		<!-- Priority Emergency (first contact shown prominently) -->
		<?php if (!empty($contacts)): $priority = $contacts[0]; ?>
			<div class="contact-card priority-contact-card">
				<div class="priority-emergency-badge">🚨 PRIORITY EMERGENCY</div>
				<div class="contact-header">
					<img src="<?php echo htmlspecialchars($priority['logo']); ?>" alt="Logo" class="contact-logo" data-logo-src="<?php echo htmlspecialchars($priority['logo']); ?>" data-contact-name="<?php echo htmlspecialchars($priority['label']); ?>">
					<div class="contact-info">
						<div class="contact-name">
							<?php echo htmlspecialchars($priority['label']); ?>
						</div>
						<div class="contact-number">
							<?php echo htmlspecialchars($priority['number']); ?>
						</div>
					</div>
				</div>
				<div class="button-group">
					<a class="btn btn-call" href="tel:<?php echo rawurlencode($priority['number']); ?>" role="button">
						<span class="icon">☎</span> Call
					</a>
					<a class="btn btn-save" href="download.php?type=single&amp;number=<?php echo rawurlencode($priority['number']); ?>&amp;name=<?php echo rawurlencode($priority['name']); ?>" role="button">
						<span class="icon">✓</span> Save
					</a>
				</div>
			</div>
		<?php endif; ?>

		<!-- Contact Cards Grid -->
		<?php if (!empty($contacts)): ?>
		<div class="contacts-grid">
			<?php foreach (array_slice($contacts, 1) as $contact): ?>
				<div class="contact-card">
					<div class="contact-header">
						<img src="<?php echo htmlspecialchars($contact['logo']); ?>" alt="Logo" class="contact-logo" data-logo-src="<?php echo htmlspecialchars($contact['logo']); ?>" data-contact-name="<?php echo htmlspecialchars($contact['label']); ?>">
						<div class="contact-info">
							<div class="contact-name">
								<?php echo htmlspecialchars($contact['label']); ?>
							</div>
							<div class="contact-number">
								<?php echo htmlspecialchars($contact['number']); ?>
							</div>
						</div>
					</div>
					<div class="button-group">
						<a class="btn btn-call" href="tel:<?php echo rawurlencode($contact['number']); ?>" role="button">
							<span class="icon">☎</span> Call
						</a>
						<a class="btn btn-save" href="download.php?type=single&amp;number=<?php echo rawurlencode($contact['number']); ?>&amp;name=<?php echo rawurlencode($contact['name']); ?>" role="button">
							<span class="icon">✓</span> Save
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

		<!-- Save All -->
		<?php if (!empty($contacts)): ?>
			<div class="save-all-section">
				<div class="save-all-text">Click if you want to</div>
				<a class="btn-save-all" href="download.php?type=all" role="button">Save All</a>
			</div>
		<?php endif; ?>
	</div>

	<script>
		// Auto-fallback to thumbnail URL if image fails to load
		const logoImages = document.querySelectorAll('.contact-logo');
		
		logoImages.forEach((img) => {
			img.addEventListener('error', function() {
				// Try alternative thumbnail URL if original failed
				const urlMatch = this.src.match(/[?&]id=([a-zA-Z0-9_-]+)/);
				if (urlMatch && urlMatch[1]) {
					const fileId = urlMatch[1];
					const thumbnailUrl = 'https://drive.google.com/thumbnail?id=' + fileId + '&sz=w1000';
					// Update the image source to thumbnail URL
					img.src = thumbnailUrl;
				}
			});
		});
	</script>

</body>
</html>


