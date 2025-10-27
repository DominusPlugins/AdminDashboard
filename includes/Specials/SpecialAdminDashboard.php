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

		$html = '<div class="mw-body-content">';
		$html .= '<h1>' . $this->msg( 'admindashboard-title' )->text() . '</h1>';
		$html .= '<div class="dashboard-grid">';

		// Users card
		$html .= '<div class="dashboard-card">';
		$html .= '<h3>' . $this->msg( 'admindashboard-users' )->text() . '</h3>';
		$html .= '<p class="stat-value">' . intval( $users ) . '</p>';
		$html .= '<a href="' . htmlspecialchars( $this->getTitleUrl( 'users' ) ) . '" class="mw-ui-button">' . $this->msg( 'admindashboard-view-users' )->text() . '</a>';
		$html .= '</div>';

		// Pages card
		$html .= '<div class="dashboard-card">';
		$html .= '<h3>' . $this->msg( 'admindashboard-pages' )->text() . '</h3>';
		$html .= '<p class="stat-value">' . intval( $pages ) . '</p>';
		$html .= '<a href="' . htmlspecialchars( $this->getTitleUrl( 'pages' ) ) . '" class="mw-ui-button">' . $this->msg( 'admindashboard-view-pages' )->text() . '</a>';
		$html .= '</div>';

		// Edits card
		$html .= '<div class="dashboard-card">';
		$html .= '<h3>' . $this->msg( 'admindashboard-edits' )->text() . '</h3>';
		$html .= '<p class="stat-value">' . intval( $edits ) . '</p>';
		$html .= '<a href="' . htmlspecialchars( $this->getTitleUrl( 'statistics' ) ) . '" class="mw-ui-button">' . $this->msg( 'admindashboard-view-statistics' )->text() . '</a>';
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
		
		// Get search parameter if provided
		$search = $this->getRequest()->getText( 'search', '' );
		$where = [];
		if ( $search ) {
			$where['user_name'] = $dbr->buildLike( $dbr->escapeLike( $search ), $dbr->anyString() );
		}

		// Get users with additional info
		$result = $dbr->select(
			[ 'user', 'user_groups' ],
			[ 'user_id', 'user_name', 'user_registration', 'user_touched', 'user_email', 'ug_group' ],
			$where,
			__METHOD__,
			[ 'LIMIT' => 200, 'ORDER BY' => 'user_name ASC' ],
			[ 'user_groups' => [ 'LEFT JOIN', 'user_id = ug_user' ] ]
		);

		// Process results and group by user
		$users = [];
		foreach ( $result as $row ) {
			if ( !isset( $users[$row->user_id] ) ) {
				$users[$row->user_id] = [
					'id' => $row->user_id,
					'name' => $row->user_name,
					'registration' => $row->user_registration,
					'touched' => $row->user_touched,
					'email' => $row->user_email,
					'groups' => []
				];
			}
			if ( $row->ug_group ) {
				$users[$row->user_id]['groups'][] = $row->ug_group;
			}
		}

		// Get block information (try different table names for compatibility)
		$userIds = array_keys( $users );
		$blockInfo = [];
		if ( !empty( $userIds ) ) {
			try {
				// Try 'block' table first (newer MediaWiki versions)
				$blockResult = $dbr->select(
					'block',
					[ 'bl_user', 'bl_reason', 'bl_expiry' ],
					[ 'bl_user' => $userIds ],
					__METHOD__
				);
				foreach ( $blockResult as $row ) {
					$blockInfo[$row->bl_user] = [ 'reason' => $row->bl_reason, 'expiry' => $row->bl_expiry ];
				}
			} catch ( \Exception $e ) {
				try {
					// Try 'ipblocks' table (older MediaWiki versions)
					$blockResult = $dbr->select(
						'ipblocks',
						[ 'ipb_user', 'ipb_reason', 'ipb_expiry' ],
						[ 'ipb_user' => $userIds ],
						__METHOD__
					);
					foreach ( $blockResult as $row ) {
						$blockInfo[$row->ipb_user] = [ 'reason' => $row->ipb_reason, 'expiry' => $row->ipb_expiry ];
					}
				} catch ( \Exception $e ) {
					// If neither table exists, just assume no blocks
					$blockInfo = [];
				}
			}
		}

		$html = '<div class="mw-body-content">';
		$html .= '<h1>' . $this->msg( 'admindashboard-users-title' )->text() . '</h1>';
		$html .= $this->makeNav();

		// Search form
		$html .= '<form method="GET" class="mw-ui-form" style="margin-bottom: 20px;">';
		$html .= '<fieldset>';
		$html .= '<legend>' . $this->msg( 'admindashboard-search' )->text() . '</legend>';
		$html .= '<div class="mw-ui-form-field">';
		$html .= '<label for="search-input">' . $this->msg( 'admindashboard-search-users' )->text() . ':</label>';
		$html .= '<input type="text" id="search-input" name="search" value="' . htmlspecialchars( $search ) . '" class="mw-ui-input" style="flex: 1; min-width: 250px;">';
		$html .= '<button type="submit" class="mw-ui-button mw-ui-button-primary">' . $this->msg( 'admindashboard-search' )->text() . '</button>';
		if ( $search ) {
			$html .= '<a href="' . htmlspecialchars( $this->getTitleUrl( 'users' ) ) . '" class="mw-ui-button">' . $this->msg( 'admindashboard-clear' )->text() . '</a>';
		}
		$html .= '</div>';
		$html .= '</fieldset>';
		$html .= '</form>';

		// Bulk actions form
		$html .= '<form method="POST" class="mw-ui-form" style="margin-bottom: 20px;">';
		$html .= '<fieldset>';
		$html .= '<legend>' . $this->msg( 'admindashboard-bulk-actions' )->text() . '</legend>';
		$html .= '<div class="mw-ui-form-field">';
		$html .= '<label for="bulk-action-select">' . $this->msg( 'admindashboard-select-action' )->text() . ':</label>';
		$html .= '<select id="bulk-action-select" name="bulk_action" class="mw-ui-select">';
		$html .= '<option value="">' . $this->msg( 'admindashboard-select-action' )->text() . '</option>';
		$html .= '<option value="promote">' . $this->msg( 'admindashboard-promote-sysop' )->text() . '</option>';
		$html .= '<option value="demote">' . $this->msg( 'admindashboard-remove-sysop' )->text() . '</option>';
		$html .= '<option value="block">' . $this->msg( 'admindashboard-block-users' )->text() . '</option>';
		$html .= '</select>';
		$html .= '<button type="submit" class="mw-ui-button mw-ui-button-destructive">' . $this->msg( 'admindashboard-apply' )->text() . '</button>';
		$html .= '</div>';
		$html .= '</fieldset>';
		$html .= '</form>';

		$html .= '<table class="wikitable sortable" style="width: 100%;">';
		$html .= '<tr><th><input type="checkbox" id="select-all"></th><th>' . $this->msg( 'admindashboard-username' )->text() . '</th><th>' . $this->msg( 'admindashboard-groups' )->text() . '</th><th>' . $this->msg( 'admindashboard-registered' )->text() . '</th><th>' . $this->msg( 'admindashboard-last-active' )->text() . '</th><th>' . $this->msg( 'admindashboard-email' )->text() . '</th><th>' . $this->msg( 'admindashboard-status' )->text() . '</th></tr>';

		foreach ( $users as $userId => $user ) {
			$blocked = isset( $blockInfo[$userId] ) ? 'Blocked' : 'Active';
			$blockClass = $blocked === 'Blocked' ? ' class="mw-ui-destructive"' : '';
			
			$userLink = $GLOBALS['wgScriptPath'] . '/index.php/User:' . urlencode( $user['name'] );
			$groups = !empty( $user['groups'] ) ? implode( ', ', array_map( 'htmlspecialchars', $user['groups'] ) ) : $this->msg( 'admindashboard-none' )->text();
			
			$html .= '<tr' . $blockClass . '>';
			$html .= '<td><input type="checkbox" name="user_ids[]" value="' . intval( $userId ) . '"></td>';
			$html .= '<td><a href="' . htmlspecialchars( $userLink ) . '">' . htmlspecialchars( $user['name'] ) . '</a></td>';
			$html .= '<td>' . $groups . '</td>';
			$html .= '<td>' . substr( $user['registration'], 0, 10 ) . '</td>';
			$html .= '<td>' . substr( $user['touched'], 0, 10 ) . '</td>';
			$html .= '<td>' . htmlspecialchars( $user['email'] ?? $this->msg( 'admindashboard-na' )->text() ) . '</td>';
			$html .= '<td>';
			if ( $blocked === 'Blocked' && isset( $blockInfo[$userId] ) ) {
				$html .= '<span class="mw-ui-destructive">' . $this->msg( 'admindashboard-blocked' )->text() . '</span>';
				if ( $blockInfo[$userId]['reason'] ) {
					$html .= '<br><small>' . htmlspecialchars( $blockInfo[$userId]['reason'] ) . '</small>';
				}
			} else {
				$html .= '<span class="mw-ui-progressive">' . $this->msg( 'admindashboard-active' )->text() . '</span>';
			}
			$html .= '</td>';
			$html .= '</tr>';
		}

		$html .= '</table>';
		$html .= '</div>';

		// Add JavaScript for checkbox handling
		$html .= '<script>
		document.getElementById("select-all").addEventListener("change", function() {
			var checkboxes = document.querySelectorAll("input[name=\"user_ids[]\"]");
			checkboxes.forEach(function(checkbox) {
				checkbox.checked = document.getElementById("select-all").checked;
			});
		});
		</script>';

		$out->addHTML( $html );
	}

	private function showPages() {
		$out = $this->getOutput();
		$out->setPageTitle( 'Page Management' );

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		
		// Get pages only - simpler approach without revision table
		$result = $dbr->select(
			'page',
			[ 'page_title', 'page_namespace', 'page_touched' ],
			[ 'page_namespace' => 0 ],  // Only main namespace pages
			__METHOD__,
			[ 'ORDER BY' => 'page_touched DESC', 'LIMIT' => 50 ]
		);

		$html = '<div class="mw-body-content">';
		$html .= '<h1>' . $this->msg( 'admindashboard-pages-title' )->text() . '</h1>';
		$html .= $this->makeNav();
		$html .= '<table class="wikitable sortable"><tr><th>' . $this->msg( 'admindashboard-title' )->text() . '</th><th>' . $this->msg( 'admindashboard-last-modified' )->text() . '</th></tr>';

		foreach ( $result as $row ) {
			// Build the page URL properly
			$pageTitle = str_replace( '_', ' ', $row->page_title );
			$pageUrl = $GLOBALS['wgScriptPath'] . '/index.php/' . urlencode( $pageTitle );
			
			$html .= '<tr><td><a href="' . htmlspecialchars( $pageUrl ) . '">' . htmlspecialchars( $pageTitle ) . '</a></td>';
			$html .= '<td>' . substr( $row->page_touched, 0, 10 ) . '</td></tr>';
		}

		$html .= '</table></div>';
		$out->addHTML( $html );
	}

	private function showPermissions() {
		$out = $this->getOutput();
		$out->setPageTitle( 'Permission Management' );

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$result = $dbr->select( 'user_groups', [ 'ug_group' ], [], __METHOD__, [ 'GROUP BY' => 'ug_group' ] );

		$html = '<div class="mw-body-content">';
		$html .= '<h1>' . $this->msg( 'admindashboard-groups-title' )->text() . '</h1>';
		$html .= $this->makeNav();
		$html .= '<table class="wikitable"><tr><th>' . $this->msg( 'admindashboard-group' )->text() . '</th><th>' . $this->msg( 'admindashboard-members' )->text() . '</th></tr>';

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

		$html = '<div class="mw-body-content">';
		$html .= '<h1>' . $this->msg( 'admindashboard-statistics-title' )->text() . '</h1>';
		$html .= $this->makeNav();
		$html .= '<div class="statistics-grid">';

		foreach ( $stats as $label => $value ) {
			$html .= '<div class="stat-box"><h3>' . htmlspecialchars( $label ) . '</h3><p>' . intval( $value ) . '</p></div>';
		}

		$html .= '</div></div>';
		$out->addHTML( $html );
	}

	private function makeNav() {
		$nav = '<div class="mw-ui-tabs">';
		$nav .= '<ul>';
		$nav .= '<li><a href="' . htmlspecialchars( $this->getTitleUrl( 'overview' ) ) . '">' . $this->msg( 'admindashboard-overview' )->text() . '</a></li>';
		$nav .= '<li><a href="' . htmlspecialchars( $this->getTitleUrl( 'users' ) ) . '">' . $this->msg( 'admindashboard-users' )->text() . '</a></li>';
		$nav .= '<li><a href="' . htmlspecialchars( $this->getTitleUrl( 'pages' ) ) . '">' . $this->msg( 'admindashboard-pages' )->text() . '</a></li>';
		$nav .= '<li><a href="' . htmlspecialchars( $this->getTitleUrl( 'permissions' ) ) . '">' . $this->msg( 'admindashboard-permissions' )->text() . '</a></li>';
		$nav .= '<li><a href="' . htmlspecialchars( $this->getTitleUrl( 'statistics' ) ) . '">' . $this->msg( 'admindashboard-statistics' )->text() . '</a></li>';
		$nav .= '</ul>';
		$nav .= '</div>';
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
