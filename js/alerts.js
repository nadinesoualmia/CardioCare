// Alerts Filter Function
function filterAlerts(filter) {
    // Update active tab
    const tabs = document.querySelectorAll('.filter-tab');
    tabs.forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Find the clicked tab and add active class
    const clickedTab = Array.from(tabs).find(tab => tab.getAttribute('data-filter') === filter || tab.innerText.includes(filter));
    if (clickedTab) {
        clickedTab.classList.add('active');
    }
    
    // Show/hide cards based on filter
    const cards = document.querySelectorAll('.alert-card');
    cards.forEach(card => {
        const type = card.getAttribute('data-type');
        const status = card.getAttribute('data-status');
        
        let show = false;
        if (filter === 'all') {
            show = true;
        } else if (filter === 'Active') {
            show = status === 'Active';
        } else if (filter === 'Acknowledged') {
            show = status === 'Acknowledged';
        } else if (filter === 'Critical' || filter === 'Warning') {
            show = type === filter;
        }
        
        card.style.display = show ? '' : 'none';
    });
}

// Add click event listeners to filter tabs
document.addEventListener('DOMContentLoaded', function() {
    const filterTabs = document.querySelectorAll('.filter-tab');
    filterTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const filter = this.getAttribute('data-filter');
            if (filter) {
                filterAlerts(filter);
            } else {
                // For tabs without data-filter, use text content
                const text = this.innerText;
                if (text.includes('All')) filterAlerts('all');
                else if (text.includes('Critical')) filterAlerts('Critical');
                else if (text.includes('Warning')) filterAlerts('Warning');
                else if (text.includes('Active')) filterAlerts('Active');
                else if (text.includes('Acknowledged')) filterAlerts('Acknowledged');
            }
        });
    });
});