<?php
namespace qnap;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'not here' );
}

class QNAP_Import_Content {

	public static function execute( $params ) {

		// Set archive bytes offset
		if ( isset( $params['archive_bytes_offset'] ) ) {
			$archive_bytes_offset = (int) $params['archive_bytes_offset'];
		} else {
			$archive_bytes_offset = 0;
		}

		// Set file bytes offset
		if ( isset( $params['file_bytes_offset'] ) ) {
			$file_bytes_offset = (int) $params['file_bytes_offset'];
		} else {
			$file_bytes_offset = 0;
		}

		// Get processed files size
		if ( isset( $params['processed_files_size'] ) ) {
			$processed_files_size = (int) $params['processed_files_size'];
		} else {
			$processed_files_size = 0;
		}

		// Get total files size
		if ( isset( $params['total_files_size'] ) ) {
			$total_files_size = (int) $params['total_files_size'];
		} else {
			$total_files_size = 1;
		}

		// Get total files count
		if ( isset( $params['total_files_count'] ) ) {
			$total_files_count = (int) $params['total_files_count'];
		} else {
			$total_files_count = 1;
		}

		// Read blogs.json file
		$handle = qnap_open( qnap_blogs_path( $params ), 'r' );

		// Parse blogs.json file
		$blogs = qnap_read( $handle, filesize( qnap_blogs_path( $params ) ) );
		$blogs = json_decode( $blogs, true );

		// Close handle
		qnap_close( $handle );

		// What percent of files have we processed?
		$progress = (int) min( ( $processed_files_size / $total_files_size ) * 100, 100 );

		// Set progress
		QNAP_Status::info( sprintf( __( 'Restoring %d files...<br />%d%% complete', QNAP_PLUGIN_NAME ), $total_files_count, $progress ) );

		// Flag to hold if file data has been processed
		$completed = true;

		// Start time
		$start = microtime( true );

		// Open the archive file for reading
		$archive = new QNAP_Extractor( qnap_archive_path( $params ) );

		// Set the file pointer to the one that we have saved
		$archive->set_file_pointer( $archive_bytes_offset );

		$old_paths = array();
		$new_paths = array();

		// Set extract paths
		foreach ( $blogs as $blog ) {
			if ( qnap_is_mainsite( $blog['Old']['BlogID'] ) === false ) {
				if ( defined( 'UPLOADBLOGSDIR' ) ) {
					// Old files dir style
					$old_paths[] = qnap_blog_files_relpath( $blog['Old']['BlogID'] );
					$new_paths[] = qnap_blog_files_abspath( $blog['New']['BlogID'] );

					// Old blogs.dir style
					$old_paths[] = qnap_blog_blogsdir_relpath( $blog['Old']['BlogID'] );
					$new_paths[] = qnap_blog_blogsdir_abspath( $blog['New']['BlogID'] );

					// New sites dir style
					$old_paths[] = qnap_blog_sites_relpath( $blog['Old']['BlogID'] );
					$new_paths[] = qnap_blog_files_abspath( $blog['New']['BlogID'] );
				} else {
					// Old files dir style
					$old_paths[] = qnap_blog_files_relpath( $blog['Old']['BlogID'] );
					$new_paths[] = qnap_blog_sites_abspath( $blog['New']['BlogID'] );

					// Old blogs.dir style
					$old_paths[] = qnap_blog_blogsdir_relpath( $blog['Old']['BlogID'] );
					$new_paths[] = qnap_blog_sites_abspath( $blog['New']['BlogID'] );

					// New sites dir style
					$old_paths[] = qnap_blog_sites_relpath( $blog['Old']['BlogID'] );
					$new_paths[] = qnap_blog_sites_abspath( $blog['New']['BlogID'] );
				}
			}
		}

		// Set base site extract paths (should be added at the end of arrays)
		foreach ( $blogs as $blog ) {
			if ( qnap_is_mainsite( $blog['Old']['BlogID'] ) === true ) {
				if ( defined( 'UPLOADBLOGSDIR' ) ) {
					// Old files dir style
					$old_paths[] = qnap_blog_files_relpath( $blog['Old']['BlogID'] );
					$new_paths[] = qnap_blog_files_abspath( $blog['New']['BlogID'] );

					// Old blogs.dir style
					$old_paths[] = qnap_blog_blogsdir_relpath( $blog['Old']['BlogID'] );
					$new_paths[] = qnap_blog_blogsdir_abspath( $blog['New']['BlogID'] );

					// New sites dir style
					$old_paths[] = qnap_blog_sites_relpath( $blog['Old']['BlogID'] );
					$new_paths[] = qnap_blog_files_abspath( $blog['New']['BlogID'] );
				} else {
					// Old files dir style
					$old_paths[] = qnap_blog_files_relpath( $blog['Old']['BlogID'] );
					$new_paths[] = qnap_blog_sites_abspath( $blog['New']['BlogID'] );

					// Old blogs.dir style
					$old_paths[] = qnap_blog_blogsdir_relpath( $blog['Old']['BlogID'] );
					$new_paths[] = qnap_blog_sites_abspath( $blog['New']['BlogID'] );

					// New sites dir style
					$old_paths[] = qnap_blog_sites_relpath( $blog['Old']['BlogID'] );
					$new_paths[] = qnap_blog_sites_abspath( $blog['New']['BlogID'] );
				}
			}
		}

		$old_paths[] = qnap_blog_sites_relpath();
		$new_paths[] = qnap_blog_sites_abspath();

		while ( $archive->has_not_reached_eof() ) {
			$file_bytes_written = 0;

			// Exclude WordPress files
			$exclude_files = array_keys( _get_dropins() );

			// Exclude plugin files
			$exclude_files = array_merge(
				$exclude_files,
				array(
					QNAP_PACKAGE_NAME,
					QNAP_MULTISITE_NAME,
					QNAP_DATABASE_NAME,
					QNAP_MUPLUGINS_NAME,
				)
			);

			// Exclude Elementor files
			$exclude_files = array_merge( $exclude_files, array( QNAP_ELEMENTOR_CSS_NAME ) );

			// Exclude content extensions
			$exclude_extensions = array( QNAP_LESS_CACHE_NAME );

			// Extract a file from archive to WP_CONTENT_DIR
			if ( ( $completed = $archive->extract_one_file_to( WP_CONTENT_DIR, $exclude_files, $exclude_extensions, $old_paths, $new_paths, $file_bytes_written, $file_bytes_offset ) ) ) {
				$file_bytes_offset = 0;
			}

			// Get archive bytes offset
			$archive_bytes_offset = $archive->get_file_pointer();

			// Increment processed files size
			$processed_files_size += $file_bytes_written;

			// What percent of files have we processed?
			$progress = (int) min( ( $processed_files_size / $total_files_size ) * 100, 100 );

			// Set progress
			QNAP_Status::info( sprintf( __( 'Restoring %d files...<br />%d%% complete', QNAP_PLUGIN_NAME ), $total_files_count, $progress ) );

			// More than 10 seconds have passed, break and do another request
			if ( ( $timeout = apply_filters( 'qnap_completed_timeout', 10 ) ) ) {
				if ( ( microtime( true ) - $start ) > $timeout ) {
					$completed = false;
					break;
				}
			}
		}

		// End of the archive?
		if ( $archive->has_reached_eof() ) {

			// Unset archive bytes offset
			unset( $params['archive_bytes_offset'] );

			// Unset file bytes offset
			unset( $params['file_bytes_offset'] );

			// Unset processed files size
			unset( $params['processed_files_size'] );

			// Unset total files size
			unset( $params['total_files_size'] );

			// Unset total files count
			unset( $params['total_files_count'] );

			// Unset completed flag
			unset( $params['completed'] );

		} else {

			// Set archive bytes offset
			$params['archive_bytes_offset'] = $archive_bytes_offset;

			// Set file bytes offset
			$params['file_bytes_offset'] = $file_bytes_offset;

			// Set processed files size
			$params['processed_files_size'] = $processed_files_size;

			// Set total files size
			$params['total_files_size'] = $total_files_size;

			// Set total files count
			$params['total_files_count'] = $total_files_count;

			// Set completed flag
			$params['completed'] = $completed;
		}

		// Close the archive file
		$archive->close();

		return $params;
	}
}
