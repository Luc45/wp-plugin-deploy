### WP Plugin Deploy (Under Development :construction:)

99% chance you don't need this.

### Who is it for, then:

- You have a WordPress site running on a remote server.
- You have a plugin that you develop locally, and that you want to push to this server easily.

The deploy is made using $_POST, therefore SSH access it not needed, which is especially useful to do deploys on restricted servers, such as WordPress.com, where the usual Git deploy strategy cannot be used.

### How it works:

This deploy consists of two parts: `deploy-upload.php` and `deploy-download.php`.

- `deploy-download.php` lives as a WordPress plugin in the remote server, listening for connections.
- `deploy-upload.php` lives in the local development environment of a developer.

Assuming you have a zip file of the plugin you want to deploy, `deploy-upload.php` will send your `plugin.zip` to the remote server using $_POST requests. The requests are sent in chunks of 512kb (configurable), to bypass any kind of post_max_size limitations on the remote server. The deploy requires a secret key, that must be the same between the two files. When the upload is complete, we unzip the plugin using the same mechanism that WordPress Core uses to unpack plugins, and we replace the existing plugin in the remote server with the newly deployed one.