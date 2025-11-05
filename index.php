<?php
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

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

echo 'here';
die();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
	<title>Emergency Contacts | City of Bacoor</title>

	<!-- Favicon -->
	<link rel="icon" type="image/x-icon" href="images/favicon.ico">
	<link rel="apple-touch-icon" href="images/favicon.ico">

	<!-- SEO and Indexing -->
	<meta name="robots" content="index, follow">
	<meta name="googlebot" content="index, follow">
	<meta name="author" content="E-GOV Department Office">
	<meta name="description" content="Access the official emergency contact numbers for Bacoor City. Call or save important hotlines for firefighters, police, BDRRMO, rescue units, and city services. Quick access to emergency services 24/7.">
	<meta name="keywords" content="Bacoor emergency contacts, Bacoor hotline, LGU Bacoor, Bacoor rescue, Bacoor police, Bacoor fire protection, BDRRMO, emergency numbers Bacoor, Bacoor City emergency, Cavite emergency contacts">
	
	<!-- Open Graph (Facebook, Messenger, WhatsApp) -->
	<meta property="og:type" content="website">
	<meta property="og:site_name" content="City of Bacoor">
	<meta property="og:title" content="Emergency Contacts | City of Bacoor">
	<meta property="og:description" content="Quick access to Bacoor City emergency contacts — BDRRMO, police, fire protection, and more. Stay prepared and save lives.">
	<meta property="og:image" content="<?= SITE_URL ?>images/bacoor-logo.jpg">
	<meta property="og:image:alt" content="Bacoor City Logo">
	<meta property="og:url" content="<?= SITE_URL ?>emergency-contacts">
	<meta property="og:locale" content="en_PH">

	<!-- Twitter Card -->
	<meta name="twitter:card" content="summary_large_image">
	<meta name="twitter:title" content="Emergency Contacts | City of Bacoor">
	<meta name="twitter:description" content="Official Bacoor City emergency hotlines. Call or save important numbers easily. Available 24/7.">
	<meta name="twitter:image" content="<?= SITE_URL ?>images/bacoor-logo.jpg">
	<meta name="twitter:image:alt" content="Bacoor City Logo">
	
	<!-- Additional SEO -->
	<meta name="application-name" content="Bacoor Emergency Contacts">
	<meta name="theme-color" content="#1e3a8a">
	<link rel="canonical" href="<?= SITE_URL ?>emergency-contacts">

	<!-- Stylesheet -->
	<link href="https://fonts.googleapis.com/css?family=Rubik:300,400,500,600%7CIBM+Plex+Sans:300,400,500,600,700" rel="stylesheet">
	<link rel="stylesheet" href="css/contact.css?<?= VERSION ?>">
</head>
<body>
	<div class="container">
		<div class="header">
			<img src="images/bacoor-logo.jpg" alt="Bacoor Logo" class="city-logo">
			<h1>Emergency Contacts</h1>
			<small>E-GOV Department Office</small>
		</div>

		<!-- Priority Emergency -->
		<?php if (!empty($contacts)): 
			$priority = $contacts[0];
			$priorityHash = $contactHashes[0];
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
							<span class="icon">☎</span> Call
						</button>
					<?php else: ?>
						<a class="btn btn-call" href="tel:<?php echo rawurlencode($priorityNumbers[0]); ?>" role="button">
							<span class="icon">☎</span> Call
						</a>
					<?php endif; ?>
					
					<a class="btn btn-save" href="download.php?type=single&amp;id=<?php echo htmlspecialchars($priorityHash); ?>" role="button">
						<span class="icon">✓</span> Save
					</a>
				</div>
			</div>
		<?php endif; ?>

		<!-- Contact Cards Grid -->
		<?php if (!empty($contacts)): ?>
		<div class="contacts-grid priority-included">
			<?php foreach (array_slice($contacts, 1) as $offset => $contact): 
				$contactIndex = $offset + 1;
				$contactHash = $contactHashes[$contactIndex];
				$numbers = explode(',', $contact['number']);
				$numbers = array_map('trim', $numbers);
				$numbers = array_filter($numbers);
				$hasAlternatives = count($numbers) > 1;
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
								<span class="icon">☎</span> Call
							</button>
						<?php else: ?>
							<a class="btn btn-call" href="tel:<?php echo rawurlencode($numbers[0]); ?>" role="button">
								<span class="icon">☎</span> Call
							</a>
						<?php endif; ?>
						
						<a class="btn btn-save" href="download.php?type=single&amp;id=<?php echo htmlspecialchars($contactHash); ?>" role="button">
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
				<a class="btn-save-all" href="download.php?type=all" role="button">Save All Contacts</a>
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

	<script>
		function parseNumberWithLabel(numberString) {
			const parts = numberString.split('|').map(s => s.trim());
			return {
				number: parts[0],
				label: parts[1] || null
			};
		}

		function openModal(numbersJson, contactName) {
			const modal = document.getElementById('numberModal');
			const modalTitle = document.getElementById('modalTitle');
			const modalNumberList = document.getElementById('modalNumberList');
			let numbers;
			try {
				numbers = JSON.parse(numbersJson);
			} catch (e) {
				console.error('Failed to parse numbers:', e);
				return;
			}
			
			modalTitle.textContent = contactName;
			modalNumberList.innerHTML = '';
			
			numbers.forEach((numberString, index) => {
				const parsed = parseNumberWithLabel(numberString);
				
				const numberItem = document.createElement('div');
				numberItem.className = 'modal-number-item';
				
				const label = document.createElement('div');
				label.className = 'modal-number-label';
				if (parsed.label) {
					label.textContent = parsed.label;
				} else {
					label.textContent = index === 0 ? 'Primary Number' : `Alternative ${index}`;
				}
				
				const numberDisplay = document.createElement('div');
				numberDisplay.className = 'modal-number-display';
				numberDisplay.textContent = parsed.number;
				
				const callBtn = document.createElement('a');
				callBtn.className = 'modal-call-btn';
				callBtn.href = 'tel:' + encodeURIComponent(parsed.number);
				callBtn.innerHTML = '<span class="icon">☎</span> Call This Number';
				
				numberItem.appendChild(label);
				numberItem.appendChild(numberDisplay);
				numberItem.appendChild(callBtn);
				
				modalNumberList.appendChild(numberItem);
			});
			
			modal.classList.add('active');
			document.body.style.overflow = 'hidden';
		}
		
		function closeModal() {
			const modal = document.getElementById('numberModal');
			modal.classList.remove('active');
			document.body.style.overflow = '';
		}
		
		document.getElementById('numberModal').addEventListener('click', function(e) {
			if (e.target === this) {
				closeModal();
			}
		});
		
		document.addEventListener('keydown', function(e) {
			if (e.key === 'Escape') {
				closeModal();
			}
		});
	</script>
</body>
</html>