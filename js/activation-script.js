document.getElementById('mxchat-activation-form').addEventListener('submit', function(e) {
    e.preventDefault(); // Prevent the form from submitting the normal way

    var spinner = document.getElementById('mxchat-spinner');
    var submitButton = document.getElementById('activate_license_button');
    var licenseStatus = document.getElementById('mxchat-license-status');

    // Show the spinner and disable the submit button
    spinner.style.display = 'inline-block';
    submitButton.disabled = true;

    // Gather form data
    var formData = new FormData(this);

    // Send the AJAX request
    var xhr = new XMLHttpRequest();
    xhr.open('POST', mxchatChat.ajax_url, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onload = function() {
        // Hide the spinner and re-enable the submit button
        spinner.style.display = 'none';
        submitButton.disabled = false;

        if (xhr.status >= 200 && xhr.status < 400) {
            var response = JSON.parse(xhr.responseText);
            if (response.success) {
                // Update the license status to active
                licenseStatus.textContent = 'Active';
                licenseStatus.classList.remove('inactive');
                licenseStatus.classList.add('active');
            } else {
                // Handle the error case
                alert(response.data.message || 'There was an error activating the license.');
            }
        } else {
            // Handle server error
            alert('Server error. Please try again.');
        }
    };

    xhr.onerror = function() {
        // Handle connection error
        alert('Request failed. Please check your internet connection.');
    };

    xhr.send(new URLSearchParams(formData).toString()); // Send the form data
});
