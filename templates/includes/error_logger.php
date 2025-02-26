<!-- Error Logger Script -->
<script src="/js/error-logger.js"></script>
<script>
// Initialize the error logger
document.addEventListener('DOMContentLoaded', function() {
    // Configure and initialize error logger if available
    if (window.ErrorLogger) {
        ErrorLogger.init({
            endpoint: '<?= getenv('APP_URL') ?>/api/errors/js',
            enabled: true,
            throttleLimit: 5,
            captureConsoleErrors: <?= getenv('APP_ENV') === 'production' ? 'false' : 'true' ?>,
            tags: [
                '<?= getenv('APP_ENV') ?>',
                '<?= isset($_SESSION['user_id']) ? "user-" . $_SESSION['user_id'] : "guest" ?>'
            ],
            version: '<?= getenv('APP_VERSION') ?? '1.0.0' ?>'
        });
        
        console.log('Error logging initialized');
    }
});
</script> 