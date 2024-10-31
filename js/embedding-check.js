document.addEventListener('DOMContentLoaded', function() {
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('embedding_failed')) {
        alert('Failed to generate embedding for the content. Please try submitting again.');
    }

    var sitemapForm = document.getElementById('mxchat-sitemap-form');
    
    // Only proceed if the sitemapForm element exists
    if (sitemapForm) {
        var loadingSpinner = document.getElementById('mxchat-sitemap-loading');
        var loadingText = document.getElementById('mxchat-loading-text');
        var sitemapUrlField = document.getElementById('sitemap_url');
        var submitButton = sitemapForm.querySelector('input[type="submit"]');

        sitemapForm.addEventListener('submit', function() {
            // Hide the form fields
            sitemapUrlField.style.display = 'none';
            submitButton.style.display = 'none';

            // Show the loading spinner and text
            loadingSpinner.style.display = 'flex';
            loadingText.style.display = 'block';
        });
    }
});
