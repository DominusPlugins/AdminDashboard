<?php

/**
 * Admin Dashboard - A comprehensive admin dashboard for MediaWiki
 *
 * @file
 * @ingroup Extensions
 * @author Tyler
 * @license GPL-2.0-or-later
 */

// phpcs:disable Generic.Files.LineLength.TooLong -- Long URLs and others
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'AdminDashboard' );
	// Keep i18n globals so merging with LocalSettings still works
	$wgMessagesDirs['AdminDashboard'] = __DIR__ . '/i18n';
	wfWikiLoad( 'AdminDashboard', __DIR__ . '/AdminDashboard.php' );
} else {
	die( 'This version of the AdminDashboard extension requires MediaWiki 1.35+' );
}
