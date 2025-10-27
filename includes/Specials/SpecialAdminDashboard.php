<?php

namespace MediaWiki\Extension\AdminDashboard\Specials;

use SpecialPage;
use MediaWiki\MediaWikiServices;

/**
 * Special page for Admin Dashboard
 */
class SpecialAdminDashboard extends SpecialPage {

	public function __construct() {
		parent::__construct( 'AdminDashboard', 'adminboard' );
	}

	public function execute( $par ) {
		$this->setHeaders();
		$this->checkPermissions();

		$out = $this->getOutput();
		$out->addModuleStyles( 'ext.AdminDashboard.styles' );
		$out->addModules( 'ext.AdminDashboard.scripts' );

		$action = $par ?? 'overview';

		try {
			switch ( $action ) {
				case 'users':
					$this->showUsers();
					break;
				case 'pages':
					$this->showPages();
					break;
				case 'permissions':
					$this->showPermissions();
					break;
				case 'statistics':
					$this->showStatistics();
					break;
				default:
					$this->showOverview();
			}
		} catch ( \Exception $e ) {
			$out->addHTML( '<div class="error">Error: ' . htmlspecialchars( $e->getMessage() ) . '</div>' );
		}
	}

	private function showOverview() {
		$out = $this->getOutput();
		$out->setPageTitle( 'Admin Dashboard' );

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		// Get statistics
		$users = $dbr->selectField( 'user', 'COUNT(*)', [], __METHOD__ );
		$pages = $dbr->selectField( 'page', 'COUNT(*)', [ 'page_namespace' => 0 ], __METHOD__ );
		$edits = $dbr->selectField( 'revision', 'COUNT(*)', [], __METHOD__ );

		$html = '<div class="admin-dashboard">';
		$html .= '<h1>Admin Dashboard</h1>';
		$html .= '<div class="dashboard-grid">';

		// Users card
		$html .= '<div class="dashboard-card">';
		$html .= '<h3>Users</h3>';
		$html .= '<p class="stat-value">' . intval( $users ) . '</p>';
		$html .= '<a href="' . $this->getTitleUrl( 'users' ) . '">View Users</a>';
		$html .= '</div>';

		// Pages card
		$html .= '<div class="dashboard-card">';
		$html .= '<h3>Pages</h3>';
		$html .= '<p class="stat-value">' . intval( $pages ) . '</p>';
		$html .= '<a href="' . $this->getTitleUrl( 'pages' ) . '">View Pages</a>';
		$html .= '</div>';

		// Edits card
		$html .= '<div class="dashboard-card">';
		$html .= '<h3>Edits</h3>';
		$html .= '<p class="stat-value">' . intval( $edits ) . '</p>';
		$html .= '<a href="' . $this->getTitleUrl( 'statistics' ) . '">View Statistics</a>';
		$html .= '</div>';

		$html .= '</div>';
		$html .= $this->makeNav();
		$html .= '</div>';

		$out->addHTML( $html );
	}

	private function showUsers() {
		$out = $this->getOutput();
		$out->setPageTitle( 'User Management' );

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$result = $dbr->select( 'user', [ 'user_name', 'user_registration', 'user_touched' ], [], __METHOD__, [ 'LIMIT' => 100 ] );

		$html = '<div class="admin-section">';
		$html .= '<h1>Users</h1>';
		$html .= $this->makeNav();
		$html .= '<table class="wikitable sortable"><tr><th>Username</th><th>Registered</th><th>Last Active</th></tr>';

		foreach ( $result as $row ) {
			$html .= '<tr><td>' . htmlspecialchars( $row->user_name ) . '</td>';
			$html .= '<td>' . substr( $row->user_registration, 0, 10 ) . '</td>';
			$html .= '<td>' . substr( $row->user_touched, 0, 10 ) . '</td></tr>';
		}

		$html .= '</table></div>';
		$out->addHTML( $html );
	}

	private function showPages() {
		$out = $this->getOutput();
		$out->setPageTitle( 'Page Management' );

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		
		// Try to get the most recent revision for each page
		$result = $dbr->select(
			[ 'page', 'revision' ],
			[ 'page_title', 'page_namespace', 'rev_timestamp', 'rev_user' ],
			[],
			__METHOD__,
			[ 'ORDER BY' => 'rev_timestamp DESC', 'LIMIT' => 50 ],
			[ 'revision' => [ 'INNER JOIN', 'page_id = rev_page' ] ]
		);

		$html = '<div class="admin-section">';
		$html .= '<h1>Pages</h1>';
		$html .= $this->makeNav();
		$html .= '<table class="wikitable sortable"><tr><th>Title</th><th>Last Modified</th><th>By</th></tr>';

		foreach ( $result as $row ) {
			$title = \Title::makeTitleSafe( $row->page_namespace, $row->page_title );
			if ( $title ) {
				// Get username from rev_user if available
				$userName = 'Unknown';
				if ( $row->rev_user ) {
					$user = \User::newFromId( $row->rev_user );
					$userName = $user ? $user->getName() : 'Unknown';
				}
				
				$html .= '<tr><td><a href="' . htmlspecialchars( $title->getFullURL() ) . '">' . htmlspecialchars( $title->getPrefixedText() ) . '</a></td>';
				$html .= '<td>' . substr( $row->rev_timestamp, 0, 10 ) . '</td>';
				$html .= '<td>' . htmlspecialchars( $userName ) . '</td></tr>';
			}
		}

		$html .= '</table></div>';
		$out->addHTML( $html );
	}

	private function showPermissions() {
		$out = $this->getOutput();
		$out->setPageTitle( 'Permission Management' );

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$result = $dbr->select( 'user_groups', [ 'ug_group' ], [], __METHOD__, [ 'GROUP BY' => 'ug_group' ] );

		$html = '<div class="admin-section">';
		$html .= '<h1>User Groups</h1>';
		$html .= $this->makeNav();
		$html .= '<table class="wikitable"><tr><th>Group</th><th>Members</th></tr>';

		foreach ( $result as $row ) {
			$count = $dbr->selectField( 'user_groups', 'COUNT(*)', [ 'ug_group' => $row->ug_group ], __METHOD__ );
			$html .= '<tr><td>' . htmlspecialchars( $row->ug_group ) . '</td><td>' . intval( $count ) . '</td></tr>';
		}

		$html .= '</table></div>';
		$out->addHTML( $html );
	}

	private function showStatistics() {
		$out = $this->getOutput();
		$out->setPageTitle( 'Statistics' );

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		$stats = [
			'Users' => $dbr->selectField( 'user', 'COUNT(*)', [], __METHOD__ ),
			'Pages' => $dbr->selectField( 'page', 'COUNT(*)', [ 'page_namespace' => 0 ], __METHOD__ ),
			'Edits' => $dbr->selectField( 'revision', 'COUNT(*)', [], __METHOD__ ),
			'Files' => $dbr->selectField( 'image', 'COUNT(*)', [], __METHOD__ ),
		];

		$html = '<div class="admin-section">';
		$html .= '<h1>Statistics</h1>';
		$html .= $this->makeNav();
		$html .= '<div class="statistics-grid">';

		foreach ( $stats as $label => $value ) {
			$html .= '<div class="stat-box"><h3>' . htmlspecialchars( $label ) . '</h3><p>' . intval( $value ) . '</p></div>';
		}

		$html .= '</div></div>';
		$out->addHTML( $html );
	}

	private function makeNav() {
		$nav = '<nav class="admin-nav">';
		$nav .= '<ul>';
		$nav .= '<li><a href="' . $this->getTitleUrl( 'overview' ) . '">Overview</a></li>';
		$nav .= '<li><a href="' . $this->getTitleUrl( 'users' ) . '">Users</a></li>';
		$nav .= '<li><a href="' . $this->getTitleUrl( 'pages' ) . '">Pages</a></li>';
		$nav .= '<li><a href="' . $this->getTitleUrl( 'permissions' ) . '">Permissions</a></li>';
		$nav .= '<li><a href="' . $this->getTitleUrl( 'statistics' ) . '">Statistics</a></li>';
		$nav .= '</ul>';
		$nav .= '</nav>';
		return $nav;
	}

	private function getTitleUrl( $section ) {
		if ( $section === 'overview' ) {
			$title = \SpecialPage::getTitleFor( 'AdminDashboard' );
		} else {
			$title = \SpecialPage::getTitleFor( 'AdminDashboard', $section );
		}
		return htmlspecialchars( $title->getFullURL() );
	}
}
