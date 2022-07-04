<?php
/*
 * Plugin name: Deploy Download
 * Description: Plugin to deploy a plugin build to this server.
 */

// Nothing to upload.
if ( empty( $_POST['deploy_upload_id'] ) ) {
	return;
}

add_action( 'wp', function () {
	$wp_plugin_deploy = new WP_Plugin_Deploy();
	try {
		$wp_plugin_deploy->upload( $_POST );
	} catch ( Exception $e ) {
		wp_die( $e->getMessage() );
	}
} );

class WP_Plugin_Deploy {
	// Set the same 64 character secret as in "deploy-upload.php" here:
	protected $expected_secret = '';

	// Set the slug of the plugin to be deployed here.
	protected $plugin_slug = '';

	protected WP_Filesystem_Base $filesystem;
	protected $deploy_dir;
	protected $zip_name;

	public function upload( array $post ) {
		global $wp_filesystem;

		require_once wp_normalize_path( ABSPATH . '/wp-admin/includes/file.php' );

		if ( ! $wp_filesystem ) {
			WP_Filesystem();
		}

		$this->filesystem = $wp_filesystem;

		if ( ! isset( $post['deploy_upload_id'], $post['current_chunk'], $post['total_chunks'], $post['chunk'], $post['deploy_secret'], $post['expected_size'] ) ) {
			throw new UnexpectedValueException( 'Invalid request.' );
		}

		/*
		 * Validate data
		 */
		$deploy_upload_id = $post['deploy_upload_id'];
		if ( ! wp_is_uuid( $deploy_upload_id ) ) {
			throw new UnexpectedValueException( 'Invalid upload ID' );
		}

		$expected_size = absint( $post['expected_size'] );
		if ( empty( $expected_size ) ) {
			throw new UnexpectedValueException( 'Expected size cannot be empty' );
		}

		$current_chunk = absint( $post['current_chunk'] );
		$total_chunks  = absint( $post['total_chunks'] );
		if ( empty( $current_chunk ) || empty( $total_chunks ) ) {
			throw new UnexpectedValueException( 'Chunk count cannot be empty' );
		}

		$this->deploy_dir = trailingslashit( wp_upload_dir( null, false )['basedir'] ) . 'deploy';
		if ( ! $this->filesystem->exists( $this->deploy_dir ) && ! $this->filesystem->mkdir( $this->deploy_dir ) ) {
			throw new RuntimeException( 'Deploy folder does not exist and could not be created.' );
		}

		$this->zip_name = strtolower( sanitize_file_name( $deploy_upload_id ) ) . '.zip';

		$this->validate_secret( $post['deploy_secret'] );

		/*
		 * Write chunk to file
		 */
		$file = new SplFileObject( trailingslashit( $this->deploy_dir ) . $this->zip_name, 'a' );
		$file->fwrite( base64_decode( $post['chunk'] ) );

		if ( $current_chunk === $total_chunks ) {
			clearstatcache();
			if ( $this->filesystem->size( $file->getPathname() ) !== $expected_size ) {
				throw new RuntimeException( sprintf( 'Final file size does not match. Expected size: %d Actual size: %d', $expected_size, $file->getSize() ) );
			}
			try {
				$this->finished_upload( $file->getPathname() );
				$this->cleanup_deploy_folder();
			} catch ( Exception $e ) {
				$this->cleanup_deploy_folder();
				throw $e;
			}
		}
	}

	protected function cleanup_deploy_folder() {
		$this->filesystem->rmdir( $this->deploy_dir, true );
		$this->filesystem->mkdir( $this->deploy_dir );

		if ( ! $this->filesystem->touch( trailingslashit( $this->deploy_dir ) . 'index.php' ) ) {
			throw new RuntimeException( 'Could not create index.php in deploy folder.' );
		}
	}

	protected function finished_upload( $zip_path ) {
		$moved = $this->move_dir( trailingslashit( WP_PLUGIN_DIR ) . $this->plugin_slug, trailingslashit( WP_PLUGIN_DIR ) . $this->plugin_slug . '-bk' );

		if ( $moved !== true ) {
			throw new RuntimeException( 'Could not move plugin folder.' );
		}

		$unzipped = unzip_file( $zip_path, WP_PLUGIN_DIR );

		if ( is_wp_error( $unzipped ) ) {
			throw new RuntimeException( $unzipped->get_error_message() );
		}

		if ( ! $this->filesystem->rmdir( trailingslashit( WP_PLUGIN_DIR ) . $this->plugin_slug . '-bk', true ) ) {
			throw new RuntimeException( 'Could not cleanup temporary folder after deploy.' );
		}
	}

	protected function validate_secret( $given_secret ) {
		// Early bail: Empty secret on server.
		if ( empty( $this->expected_secret ) || strlen( $this->expected_secret ) !== 64 ) {
			throw new UnexpectedValueException( 'Could not find a valid Deploy Secret.' );
		}

		// Early bail: Not HTTPS.
		if ( ! is_ssl() && ! in_array( wp_get_environment_type(), [ 'local', 'development' ], true ) ) {
			throw new UnexpectedValueException( 'Upload cannot happen in unencrypted connections. Please use HTTPS/SSL.' );
		}

		// Early bail: Incorrect secret provided.
		if ( empty( $given_secret ) || $given_secret !== $this->expected_secret ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			throw new UnexpectedValueException( 'Invalid secret.' );
		}
	}

	/**
	 * move_dir was introduced in WordPres 5.9, which is very recent.
	 * We port it here to increase compatibility.
	 * @see \move_dir()
	 */
	protected function move_dir( $from, $to ) {
		$this->filesystem->rmdir( $to );
		if ( @rename( $from, $to ) ) {
			return true;
		}

		$this->filesystem->mkdir( $to );
		$result = copy_dir( $from, $to );

		return $result;
	}
}