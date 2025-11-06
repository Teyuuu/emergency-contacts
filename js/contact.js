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
		// Validate JSON before parsing to prevent JSON injection
		if (typeof numbersJson !== 'string' || numbersJson.length > 5000) {
			console.error('Invalid input data');
			return;
		}
		numbers = JSON.parse(numbersJson);
		// Validate that it's an array
		if (!Array.isArray(numbers)) {
			console.error('Invalid data format');
			return;
		}
		// Limit array size to prevent DoS
		if (numbers.length > 50) {
			numbers = numbers.slice(0, 50);
		}
	} catch (e) {
		console.error('Failed to parse numbers:', e);
		return;
	}
	
	// Sanitize contact name (already escaped from PHP, but double-check)
	modalTitle.textContent = contactName || 'Select Number';
	modalNumberList.innerHTML = '';
	
	numbers.forEach((numberString, index) => {
		if (typeof numberString !== 'string' || numberString.length > 100) {
			return; // Skip invalid entries
		}
		
		const parsed = parseNumberWithLabel(numberString);
		
		const numberItem = document.createElement('div');
		numberItem.className = 'modal-number-item';
		
		const label = document.createElement('div');
		label.className = 'modal-number-label';
		if (parsed.label) {
			label.textContent = parsed.label.substring(0, 50); // Limit length
		} else {
			label.textContent = index === 0 ? 'Primary Number' : `Alternative ${index}`;
		}
		
		const numberDisplay = document.createElement('div');
		numberDisplay.className = 'modal-number-display';
		numberDisplay.textContent = parsed.number.substring(0, 50); // Limit length
		
		const callBtn = document.createElement('a');
		callBtn.className = 'modal-call-btn';
		callBtn.href = 'tel:' + encodeURIComponent(parsed.number.substring(0, 50));
		callBtn.textContent = 'Call This Number'; // Use textContent instead of innerHTML to prevent XSS
		
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

document.addEventListener('DOMContentLoaded', function() {
	const numberModal = document.getElementById('numberModal');
	if (numberModal) {
		numberModal.addEventListener('click', function(e) {
			if (e.target === this) {
				closeModal();
			}
		});
	}
	
	document.addEventListener('keydown', function(e) {
		if (e.key === 'Escape') {
			closeModal();
		}
	});
});

