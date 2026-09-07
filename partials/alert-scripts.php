<div class="alert-container" id="alertContainer"></div>
<script>
// Flash message handler
document.addEventListener('DOMContentLoaded', function() {
    <?php if(isset($_SESSION['flash'])): ?>
        showAlert('<?= $_SESSION['flash']['type'] ?>', '<?= addslashes($_SESSION['flash']['message']) ?>');
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    // One-time query param alerts (e.g., after logout)
    const url = new URL(window.location.href);
    if (url.searchParams.get('logout') === '1') {
        showAlert('success', 'You have successfully logged out.');
        url.searchParams.delete('logout');
        window.history.replaceState({}, document.title, url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : ''));
    }
});
</script>