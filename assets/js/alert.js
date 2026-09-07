    function showAlert(type, message) {
        const alertContainer = document.getElementById('alertContainer');
        
        // Map type to Bootstrap alert class
        let alertClass;
        switch(type) {
            case 'success':
                alertClass = 'alert-success';
                break;
            case 'error':
                alertClass = 'alert-danger';
                break;
            case 'warning':
                alertClass = 'alert-warning';
                break;
            default:
                alertClass = 'alert-info';
        }
        
        // Create alert
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert ${alertClass} alert-dismissible alert-fade`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        // Add to container
        alertContainer.appendChild(alertDiv);
        
        // Auto dismiss after 5 seconds
        setTimeout(() => {
            alertDiv.style.opacity = '0';
            setTimeout(() => {
                alertContainer.removeChild(alertDiv);
            }, 500);
        }, 10000);
    }