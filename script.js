function toggleDropdown() {
    const dropdownMenu = document.getElementById('dropdownMenu');
    if (dropdownMenu) dropdownMenu.classList.toggle('active');
}

// Attach handlers after DOM is ready instead of relying on inline onclick
document.addEventListener('DOMContentLoaded', function() {
    const userBtn = document.querySelector('.user-btn');
    if (userBtn) {
        userBtn.addEventListener('click', function(event) {
            // Prevent the document click handler from immediately closing the menu
            event.stopPropagation();
            toggleDropdown();
        });
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const userProfile = document.querySelector('.user-profile');
        const dropdownMenu = document.getElementById('dropdownMenu');
        if (userProfile && dropdownMenu && !userProfile.contains(event.target)) {
            dropdownMenu.classList.remove('active');
        }
    });

    // Keep existing .btn click logging
    document.querySelectorAll('.btn').forEach(button => {
        button.addEventListener('click', function() {
            console.log('Button clicked:', this.textContent);
        });
    });
});