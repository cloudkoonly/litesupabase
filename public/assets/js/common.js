// Function to show message
function showMessage(message, type = 'info') {
    const container = document.getElementById('message-container');
    const messageText = document.getElementById('message-text');
    messageText.textContent = message;
    messageText.className = `p-4 rounded-lg text-sm shadow-xl border border-border/50 backdrop-blur-sm font-medium ${type === 'error' ? 'bg-destructive/10 text-destructive border-destructive/20' : type === 'success' ? 'bg-green-500/10 text-green-600 border-green-500/20' : 'bg-primary/10 text-primary border-primary/20'}`;

    // Clear any existing timeouts
    if (window.messageTimeout) {
        clearTimeout(window.messageTimeout);
    }

    // Show message
    container.classList.remove('translate-x-full', 'opacity-0');

    // Set timeout to hide message
    window.messageTimeout = setTimeout(() => {
        container.classList.add('translate-x-full', 'opacity-0');
    }, 5000);
}