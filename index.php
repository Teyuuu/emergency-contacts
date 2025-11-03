<?php 
// Prevent browser caching of this page to ensure fresh data
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require __DIR__ . '/contacts.php'; 
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

		<?php $sheetCount = is_array($contacts) ? count($contacts) : 0; ?>
		<div class="save-all-section" style="margin-top:0;">
			<div class="save-all-text" style="font-size:0.95rem;">
				Loaded <?php echo (int)$sheetCount; ?> contact<?php echo $sheetCount===1?'':'s'; ?> from Google Sheet
			</div>
		</div>


		<!-- Priority Emergency (first contact shown prominently) -->
		<?php if (!empty($contacts)): $priority = $contacts[0]; ?>
			<div class="contact-card priority-contact-card">
				<div class="priority-emergency-badge">🚨 PRIORITY EMERGENCY</div>
				<div class="contact-header">
					<img src="<?php echo htmlspecialchars($priority['logo']); ?>" alt="Logo" class="contact-logo">
					<div class="contact-info">
						<div class="contact-name">
							<?php echo htmlspecialchars($priority['label']); ?>
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
		<div class="contacts-grid priority-included">
			<?php foreach (array_slice($contacts, 1) as $contact): ?>
				<div class="contact-card">
					<div class="contact-header">
						<img src="<?php echo htmlspecialchars($contact['logo']); ?>" alt="Logo" class="contact-logo">
						<div class="contact-info">
							<div class="contact-name">
								<?php echo htmlspecialchars($contact['label']); ?>
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

</body>
</html>


