<?php 
// Prevent browser caching of this page to ensure fresh data
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/includes/contacts.php'; 
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

		<!-- Priority Emergency (first contact shown prominently with number) -->
		<?php if (!empty($contacts)): $priority = $contacts[0]; ?>
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
					// Check if priority contact has alternative numbers
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
					
					<a class="btn btn-save" href="download.php?type=single&amp;number=<?php echo rawurlencode($priority['number']); ?>&amp;name=<?php echo rawurlencode($priority['name']); ?>" role="button">
						<span class="icon">✓</span> Save
					</a>
				</div>
			</div>
		<?php endif; ?>

		<!-- Contact Cards Grid (numbers hidden, only shown in modal) -->
		<?php if (!empty($contacts)): ?>
		<div class="contacts-grid priority-included">
			<?php foreach (array_slice($contacts, 1) as $contact): 
				// Parse alternative numbers
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
			// Split by | to separate number and label
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
			
			// Parse the numbers
			let numbers;
			try {
				numbers = JSON.parse(numbersJson);
			} catch (e) {
				console.error('Failed to parse numbers:', e);
				return;
			}
			
			// Update modal title
			modalTitle.textContent = contactName;
			
			// Clear previous numbers
			modalNumberList.innerHTML = '';
			
			// Add each number to the modal
			numbers.forEach((numberString, index) => {
				const parsed = parseNumberWithLabel(numberString);
				
				const numberItem = document.createElement('div');
				numberItem.className = 'modal-number-item';
				
				const label = document.createElement('div');
				label.className = 'modal-number-label';
				// Use custom label if provided, otherwise use default
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
			
			// Show modal
			modal.classList.add('active');
			document.body.style.overflow = 'hidden';
		}
		
		function closeModal() {
			const modal = document.getElementById('numberModal');
			modal.classList.remove('active');
			document.body.style.overflow = '';
		}
		
		// Close modal when clicking outside
		document.getElementById('numberModal').addEventListener('click', function(e) {
			if (e.target === this) {
				closeModal();
			}
		});
		
		// Close modal with Escape key
		document.addEventListener('keydown', function(e) {
			if (e.key === 'Escape') {
				closeModal();
			}
		});
	</script>
</body>
</html>