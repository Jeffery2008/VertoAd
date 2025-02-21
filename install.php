<!DOCTYPE html>
<html>
<head>
    <title>Ad Server Installation</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container">
        <h1>Ad Server Installation</h1>
        <form id="install-form">
            <h2>Database Configuration</h2>
            <div class="form-group">
                <label for="database_host">Database Host</label>
                <input type="text" class="form-control" id="database_host" name="database_host" value="localhost" required>
            </div>
            <div class="form-group">
                <label for="database_name">Database Name</label>
                <input type="text" class="form-control" id="database_name" name="database_name" value="ad_system" required>
            </div>
            <div class="form-group">
                <label for="database_username">Database Username</label>
                <input type="text" class="form-control" id="database_username" name="database_username" value="root" required>
            </div>
            <div class="form-group">
                <label for="database_password">Database Password</label>
                <input type="password" class="form-control" id="database_password" name="database_password">
            </div>

            <h2>General Configuration</h2>
            <div class="form-group">
                <label for="base_url">Base URL</label>
                <input type="url" class="form-control" id="base_url" name="base_url" value="http://localhost/ad-system" required>
            </div>
            <div class="form-group">
                <label for="app_name">Application Name</label>
                <input type="text" class="form-control" id="app_name" name="app_name" value="Ad System" required>
            </div>
            <div class="form-group">
                <label for="jwt_secret">JWT Secret Key</label>
                <input type="text" class="form-control" id="jwt_secret" name="jwt_secret" required>
                <small class="form-text text-muted">Generate a strong, random secret key.</small>
            </div>
            <div class="form-group">
                <label for="password_salt">Password Salt</label>
                <input type="text" class="form-control" id="password_salt" name="password_salt" required>
                <small class="form-text text-muted">Generate a strong, random salt value.</small>
            </div>

            <button type="submit" class="btn btn-primary">Install</button>
        </form>
        <div id="install-status"></div>
    </div>

    <script>
        document.querySelector('#install-form').addEventListener('submit', function(event) {
            event.preventDefault();

            const formData = new FormData(this);
            const installStatusDiv = document.getElementById('install-status');
            installStatusDiv.innerHTML = 'Installing...';

            fetch('api/v1/install_api.php', { // Updated API endpoint URL
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    installStatusDiv.className = 'alert alert-success';
                    installStatusDiv.innerHTML = data.message;
                     // Disable form after successful installation
                    document.querySelector('#install-form').style.display = 'none';
                } else {
                    installStatusDiv.className = 'alert alert-danger';
                    installStatusDiv.innerHTML = 'Installation failed: ' + data.error;
                }
            })
            .catch(error => {
                installStatusDiv.className = 'alert alert-danger';
                installStatusDiv.innerHTML = 'Installation failed: ' + error.message;
            });
        });
    </script>
</body>
</html>
