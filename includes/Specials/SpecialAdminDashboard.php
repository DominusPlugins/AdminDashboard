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

		// Get block information
		$userIds = array_keys( $users );
		$blockInfo = [];
		if ( !empty( $userIds ) ) {
			$blockResult = $dbr->select(
				'ipblocks',
				[ 'ipb_user', 'ipb_reason', 'ipb_expiry' ],
				[ 'ipb_user' => $userIds ],
				__METHOD__
			);
			foreach ( $blockResult as $row ) {
				$blockInfo[$row->ipb_user] = [ 'reason' => $row->ipb_reason, 'expiry' => $row->ipb_expiry ];
			}
		}

		$html = '<div class="admin-section">';
		$html .= '<h1>User Management</h1>';
		$html .= $this->makeNav();

		// Search form
		$html .= '<form method="GET" style="margin-bottom: 20px;">';
		$html .= '<input type="text" name="search" value="' . htmlspecialchars( $search ) . '" placeholder="Search users...">';
		$html .= '<button type="submit">Search</button>';
		if ( $search ) {
			$html .= '<a href="' . htmlspecialchars( $this->getTitleUrl( 'users' ) ) . '">Clear</a>';
		}
		$html .= '</form>';

		// Bulk actions form
		$html .= '<form method="POST" style="margin-bottom: 20px;">';
		$html .= '<select name="bulk_action"><option value="">Select Action...</option><option value="promote">Promote to Sysop</option><option value="demote">Remove Sysop</option><option value="block">Block Users</option></select>';
		$html .= '<button type="submit">Apply</button>';
		$html .= '</form>';

		$html .= '<table class="wikitable sortable" style="width: 100%;">';
		$html .= '<tr><th><input type="checkbox" id="select-all"></th><th>Username</th><th>Groups</th><th>Registered</th><th>Last Active</th><th>Email</th><th>Status</th></tr>';

		foreach ( $users as $userId => $user ) {
			$blocked = isset( $blockInfo[$userId] ) ? 'Blocked' : 'Active';
			$blockClass = $blocked === 'Blocked' ? ' style="background-color: #ffcccc;"' : '';
			
			$userLink = $GLOBALS['wgScriptPath'] . '/index.php/User:' . urlencode( $user['name'] );
			$groups = !empty( $user['groups'] ) ? implode( ', ', array_map( 'htmlspecialchars', $user['groups'] ) ) : 'None';
			
			$html .= '<tr' . $blockClass . '>';
			$html .= '<td><input type="checkbox" name="user_ids[]" value="' . intval( $userId ) . '"></td>';
			$html .= '<td><a href="' . htmlspecialchars( $userLink ) . '">' . htmlspecialchars( $user['name'] ) . '</a></td>';
			$html .= '<td>' . $groups . '</td>';
			$html .= '<td>' . substr( $user['registration'], 0, 10 ) . '</td>';
			$html .= '<td>' . substr( $user['touched'], 0, 10 ) . '</td>';
			$html .= '<td>' . htmlspecialchars( $user['email'] ?? 'N/A' ) . '</td>';
			$html .= '<td>';
			if ( $blocked === 'Blocked' && isset( $blockInfo[$userId] ) ) {
				$html .= '<span style="color: red; font-weight: bold;">Blocked</span>';
				if ( $blockInfo[$userId]['reason'] ) {
					$html .= '<br><small>' . htmlspecialchars( $blockInfo[$userId]['reason'] ) . '</small>';
				}
			} else {
				$html .= '<span style="color: green;">Active</span>';
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

		$html = '<div class="admin-section">';
		$html .= '<h1>Pages</h1>';
		$html .= $this->makeNav();
		$html .= '<table class="wikitable sortable"><tr><th>Title</th><th>Last Modified</th></tr>';

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
