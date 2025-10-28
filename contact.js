// Function to initiate a phone call
function callNumber(number) {
    window.location.href = `tel:${number}`;
}

// Function to save a single contact
function saveContact(number, name) {
    // Create vCard format
    const vCard = `BEGIN:VCARD
VERSION:3.0
FN:${name}
TEL;TYPE=CELL:${number}
END:VCARD`;

    // Create blob and download link
    const blob = new Blob([vCard], { type: 'text/vcard' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${name.replace(/\s+/g, '_')}.vcf`;
    
    // Trigger download
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
    
    // Show confirmation
    alert(`${name} has been saved! Check your downloads folder for the contact file.`);
}

// Function to save all contacts at once
function saveAllContacts() {
    // Array of all emergency contacts
    const contacts = [
        { number: '161', name: 'Emergency Alert 161' },
        { number: '046-417-0727', name: 'Bacoor BDRRMO' },
        { number: '046-417-6366', name: 'PNP Bacoor City' },
        { number: '046-417-6060', name: 'BFP Bacoor City' }
    ];

    // Create multiple vCards in one file
    let allVCards = '';
    contacts.forEach(contact => {
        allVCards += `BEGIN:VCARD
        VERSION:3.0
        FN:${contact.name}
        TEL;TYPE=CELL:${contact.number}
        END:VCARD
        `;
    });

    // Create blob and download link
    const blob = new Blob([allVCards], { type: 'text/vcard' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'Emergency_Contacts_All.vcf';
    
    // Trigger download
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
    
    // Show confirmation
    alert('All emergency contacts have been saved! Check your downloads folder for the contact file.');
}