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
		// Use v2 module name to avoid stale client cache of old code paths
		$out->addModules( 'ext.AdminDashboard.scripts.v2' );

		$action = $par ?: 'overview';

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
	}

	private function showOverview() {
		$out = $this->getOutput();
		$out->setPageTitle( 'Admin Dashboard' );

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		$users = $dbr->selectField( 'user', 'COUNT(*)', [], __METHOD__ );
		$pages = $dbr->selectField( 'page', 'COUNT(*)', [ 'page_namespace' => 0 ], __METHOD__ );
		$edits = $dbr->selectField( 'revision', 'COUNT(*)', [], __METHOD__ );

		$html = '<div class="mw-body-content">';
		$html .= '<h1>' . $this->msg( 'admindashboard-title' )->text() . '</h1>';
		$html .= '<div class="dashboard-grid">';

		$html .= '<div class="dashboard-card">';
		$html .= '<h3>' . $this->msg( 'admindashboard-users' )->text() . '</h3>';
		$html .= '<p class="stat-value">' . intval( $users ) . '</p>';
		$html .= '<a href="' . htmlspecialchars( $this->getTitleUrl( 'users' ) ) . '" class="mw-ui-button">' . $this->msg( 'admindashboard-view-users' )->text() . '</a>';
		$html .= '</div>';

		$html .= '<div class="dashboard-card">';
		$html .= '<h3>' . $this->msg( 'admindashboard-pages' )->text() . '</h3>';
		$html .= '<p class="stat-value">' . intval( $pages ) . '</p>';
		$html .= '<a href="' . htmlspecialchars( $this->getTitleUrl( 'pages' ) ) . '" class="mw-ui-button">' . $this->msg( 'admindashboard-view-pages' )->text() . '</a>';
		$html .= '</div>';

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

		$search = $this->getRequest()->getText( 'search', '' );
		$where = [];
		if ( $search !== '' ) {
			// Simple LIKE search; DB abstraction handles escaping
			$where[] = 'user_name ' . $dbr->buildLike( $dbr->anyString(), $dbr->escapeLike( $search ), $dbr->anyString() );
		}

		$result = $dbr->select(
			[ 'user', 'user_groups' ],
			[ 'user_id', 'user_name', 'user_registration', 'user_touched', 'user_email', 'ug_group' ],
			$where,
			__METHOD__,
			[ 'LIMIT' => 200, 'ORDER BY' => 'user_name ASC' ],
			[ 'user_groups' => [ 'LEFT JOIN', 'user_id = ug_user' ] ]
		);

		$users = [];
		foreach ( $result as $row ) {
			if ( !isset( $users[$row->user_id] ) ) {
				$users[$row->user_id] = [
					'id' => (int)$row->user_id,
					'name' => $row->user_name,
					'registration' => (string)$row->user_registration,
					'touched' => (string)$row->user_touched,
					'email' => $row->user_email,
					'groups' => []
				];
			}
			if ( $row->ug_group ) {
				$users[$row->user_id]['groups'][] = $row->ug_group;
			}
		}

		$blockInfo = [];
		$userIds = array_keys( $users );
		if ( $userIds ) {
			try {
				$blockResult = $dbr->select( 'block', [ 'bl_user', 'bl_reason', 'bl_expiry' ], [ 'bl_user' => $userIds ], __METHOD__ );
				foreach ( $blockResult as $row ) {
					$blockInfo[$row->bl_user] = [ 'reason' => $row->bl_reason, 'expiry' => $row->bl_expiry ];
				}
			} catch ( \Exception $e ) {
				try {
					$blockResult = $dbr->select( 'ipblocks', [ 'ipb_user', 'ipb_reason', 'ipb_expiry' ], [ 'ipb_user' => $userIds ], __METHOD__ );
					foreach ( $blockResult as $row ) {
						$blockInfo[$row->ipb_user] = [ 'reason' => $row->ipb_reason, 'expiry' => $row->ipb_expiry ];
					}
				} catch ( \Exception $e ) {
					$blockInfo = [];
				}
			}
		}

		$html = '';
		$html .= '<div class="mw-body-content">';
		$html .= '<h1>' . $this->msg( 'admindashboard-users-title' )->text() . '</h1>';
		$html .= $this->makeNav();

		// Search and Actions Bar
		$html .= '<form method="GET">';
		$html .= '<div class="user-management-controls">';
		$html .= '<div class="controls-row">';

		// Search section
		$html .= '<div class="search-section">';
		$html .= '<label for="search-input">' . $this->msg( 'admindashboard-search-users' )->text() . ':</label>';
		$html .= '<div>';
		$html .= '<input type="text" id="search-input" name="search" value="' . htmlspecialchars( $search ) . '" class="mw-ui-input" placeholder="' . $this->msg( 'admindashboard-search-users' )->text() . '">';
		$html .= '<button type="submit" class="mw-ui-button mw-ui-button-primary">' . $this->msg( 'admindashboard-search' )->text() . '</button>';
		if ( $search ) {
			$html .= '<a href="' . htmlspecialchars( $this->getTitleUrl( 'users' ) ) . '" class="mw-ui-button">' . $this->msg( 'admindashboard-clear' )->text() . '</a>';
		}
		$html .= '</div>';
		$html .= '</div>';

		// Bulk actions section
		$html .= '<div class="bulk-actions-section">';
		$html .= '<label for="bulk-action-select">' . $this->msg( 'admindashboard-bulk-actions' )->text() . ':</label>';
		$html .= '<div>';
		$html .= '<select id="bulk-action-select" name="bulk_action" class="mw-ui-select">';
		$html .= '<option value="">' . $this->msg( 'admindashboard-select-action' )->text() . '</option>';
		$html .= '<option value="promote">' . $this->msg( 'admindashboard-promote-sysop' )->text() . '</option>';
		$html .= '<option value="demote">' . $this->msg( 'admindashboard-remove-sysop' )->text() . '</option>';
		$html .= '<option value="block">' . $this->msg( 'admindashboard-block-users' )->text() . '</option>';
		$html .= '</select>';
		$html .= '<button type="button" id="bulk-apply-btn" class="mw-ui-button mw-ui-button-destructive">' . $this->msg( 'admindashboard-apply' )->text() . '</button>';
		$html .= '</div>';
		$html .= '</div>';

		$html .= '</div>';
		$html .= '</div>';
		$html .= '</form>';

		// Hidden form for bulk actions
		$html .= '<form method="POST" id="bulk-actions-form">';
		$html .= '<input type="hidden" name="bulk_action" id="bulk-action-value">';
		$html .= '<input type="hidden" name="user_ids" id="selected-user-ids">';
		$html .= '</form>';

		// Users table
		$html .= '<table class="wikitable sortable">';
		$html .= '<tr><th><input type="checkbox" id="select-all"></th><th>' . $this->msg( 'admindashboard-username' )->text() . '</th><th>' . $this->msg( 'admindashboard-groups' )->text() . '</th><th>' . $this->msg( 'admindashboard-registered' )->text() . '</th><th>' . $this->msg( 'admindashboard-last-active' )->text() . '</th><th>' . $this->msg( 'admindashboard-email' )->text() . '</th><th>' . $this->msg( 'admindashboard-status' )->text() . '</th></tr>';

		foreach ( $users as $userId => $user ) {
			$blocked = isset( $blockInfo[$userId] );
			$blockClass = $blocked ? ' class="mw-ui-destructive"' : '';
			$groups = $user['groups'] ? implode( ', ', array_map( 'htmlspecialchars', $user['groups'] ) ) : $this->msg( 'admindashboard-none' )->text();

			$html .= '<tr' . $blockClass . ' data-user-id="' . intval( $userId ) . '" data-user-name="' . htmlspecialchars( $user['name'] ) . '" data-user-email="' . htmlspecialchars( $user['email'] ?? '' ) . '" data-user-groups="' . htmlspecialchars( json_encode( $user['groups'] ) ) . '" data-user-registered="' . htmlspecialchars( $user['registration'] ) . '" data-user-touched="' . htmlspecialchars( $user['touched'] ) . '">';
			$html .= '<td><input type="checkbox" name="user_ids[]" value="' . intval( $userId ) . '"></td>';
			$html .= '<td><a href="#" class="user-edit-link" data-user-id="' . intval( $userId ) . '">' . htmlspecialchars( $user['name'] ) . '</a></td>';
			$html .= '<td>' . $groups . '</td>';
			$html .= '<td>' . substr( $user['registration'], 0, 10 ) . '</td>';
			$html .= '<td>' . substr( $user['touched'], 0, 10 ) . '</td>';
			$html .= '<td>' . htmlspecialchars( $user['email'] ?? $this->msg( 'admindashboard-na' )->text() ) . '</td>';
			$html .= '<td>';
			if ( $blocked ) {
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

		// Modal markup (hidden by default; JS toggles to display:block)
		$html .= '<div id="user-edit-modal" class="modal-overlay" style="display: none;">';
		$html .= '<div class="modal-content">';
		$html .= '<div class="modal-header">';
		$html .= '<h3 id="modal-title">' . $this->msg( 'admindashboard-edit-user' )->text() . '</h3>';
		$html .= '<button type="button" class="modal-close mw-ui-button">×</button>';
		$html .= '</div>';
		$html .= '<form id="user-edit-form">';
		$html .= '<input type="hidden" id="edit-user-id" name="user_id">';
		$html .= '<input type="hidden" id="initial-groups" value="[]">';
		$html .= '<fieldset>';
		$html .= '<legend>' . $this->msg( 'admindashboard-user-details' )->text() . '</legend>';
		$html .= '<div><label for="edit-username">' . $this->msg( 'admindashboard-username' )->text() . ':</label><input type="text" id="edit-username" class="mw-ui-input" readonly></div>';
		$html .= '<div><label for="edit-email">' . $this->msg( 'admindashboard-email' )->text() . ':</label><input type="email" id="edit-email" name="email" class="mw-ui-input"></div>';
		$html .= '<div><label>' . $this->msg( 'admindashboard-registered' )->text() . ':</label><span id="edit-registered"></span></div>';
		$html .= '<div><label>' . $this->msg( 'admindashboard-last-active' )->text() . ':</label><span id="edit-last-active"></span></div>';
		$html .= '</fieldset>';
		$html .= '<fieldset>';
		$html .= '<legend>' . $this->msg( 'admindashboard-user-groups' )->text() . '</legend>';
		$html .= '<div id="user-groups-list"></div>';
		$html .= '<div><label for="add-group-select">' . $this->msg( 'admindashboard-add-group' )->text() . ':</label><select id="add-group-select" class="mw-ui-select"><option value="">' . $this->msg( 'admindashboard-select-group' )->text() . '</option><option value="sysop">' . $this->msg( 'admindashboard-sysop' )->text() . '</option><option value="bureaucrat">' . $this->msg( 'admindashboard-bureaucrat' )->text() . '</option><option value="bot">' . $this->msg( 'admindashboard-bot' )->text() . '</option><option value="interface-admin">' . $this->msg( 'admindashboard-interface-admin' )->text() . '</option></select> <button type="button" id="add-group-btn" class="mw-ui-button mw-ui-button-primary">' . $this->msg( 'admindashboard-add' )->text() . '</button></div>';
		$html .= '</fieldset>';
		$html .= '<div class="modal-footer">';
		$html .= '<button type="button" class="modal-close mw-ui-button">' . $this->msg( 'admindashboard-cancel' )->text() . '</button>';
		$html .= '<button type="submit" class="mw-ui-button mw-ui-button-primary">' . $this->msg( 'admindashboard-save' )->text() . '</button>';
		$html .= '<button type="button" id="block-user-btn" class="mw-ui-button mw-ui-button-destructive">' . $this->msg( 'admindashboard-block-user' )->text() . '</button>';
		$html .= '</div>';
		$html .= '</form>';
		$html .= '</div>';
		$html .= '</div>';

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
			// Build the page URL properly using Title object (namespaced for MW 1.36+)
			$title = \MediaWiki\Title\Title::makeTitleSafe( $row->page_namespace, $row->page_title );
			if ( !$title ) {
				continue;
			}
			$pageTitle = $title->getPrefixedText();
			$pageUrl = $title->getLocalURL();
			
			$html .= '<tr><td><a href="' . htmlspecialchars( $pageUrl ) . '">' . htmlspecialchars( $pageTitle ) . '</a></td>';
			$html .= '<td>' . substr( $row->page_touched, 0, 10 ) . '</td></tr>';
		}

		$html .= '</table></div>';
		$out->addHTML( $html );
	}

	private function showPermissions() {
		$out = $this->getOutput();
		$out->setPageTitle( 'Permission Management' );

		$services = MediaWikiServices::getInstance();
		$dbr = $services->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$config = $services->getMainConfig();

		// Gather groups from DB (memberships) and from config (permissions)
		$dbGroupsRes = $dbr->select( 'user_groups', [ 'ug_group' ], [], __METHOD__, [ 'GROUP BY' => 'ug_group' ] );
		$dbGroups = [];
		foreach ( $dbGroupsRes as $row ) {
			$dbGroups[] = (string)$row->ug_group;
		}
		$cfgGroupPerms = (array)$config->get( 'GroupPermissions' );
		// Fallback to global for older MW or when config bag is empty
		if ( !$cfgGroupPerms && isset( $GLOBALS['wgGroupPermissions'] ) && is_array( $GLOBALS['wgGroupPermissions'] ) ) {
			$cfgGroupPerms = $GLOBALS['wgGroupPermissions'];
		}
		$allGroupNames = array_values( array_unique( array_merge( array_keys( $cfgGroupPerms ), $dbGroups ) ) );
		sort( $allGroupNames );

		$availableRights = (array)$config->get( 'AvailableRights' );
		if ( !$availableRights && isset( $GLOBALS['wgAvailableRights'] ) && is_array( $GLOBALS['wgAvailableRights'] ) ) {
			$availableRights = $GLOBALS['wgAvailableRights'];
		}
		// Final fallback: derive from union of group permissions
		if ( !$availableRights ) {
			$seen = [];
			foreach ( $cfgGroupPerms as $g => $rightsArr ) {
				if ( !is_array( $rightsArr ) ) { continue; }
				foreach ( $rightsArr as $right => $allowed ) {
					$seen[$right] = true;
				}
			}
			$availableRights = array_keys( $seen );
		}
		sort( $availableRights );

		// Handle POST to generate config snippet (no direct config writes)
		$req = $this->getRequest();
		$snippetHtml = '';
		if ( $req->wasPosted() && $req->getCheck( 'generate_group_snippet' ) ) {
			$groupName = trim( $req->getText( 'group_name', '' ) );
			$rightsPosted = (array)$req->getArray( 'rights', [] );
			$rightsChosen = array_keys( array_filter( $rightsPosted ) );
			if ( $groupName !== '' && $rightsChosen ) {
				$lines = [];
				foreach ( $rightsChosen as $r ) {
					$lines[] = "\$wgGroupPermissions['" . addslashes( $groupName ) . "']['" . addslashes( $r ) . "'] = true;";
				}
				$snippet = implode( "\n", $lines );
				$snippetHtml = '<div class="mw-message-box mw-message-box-notice" style="margin-top:1em;">'
					. '<strong>' . $this->msg( 'admindashboard-config-snippet-title' )->escaped() . '</strong><br>'
					. '<p>' . $this->msg( 'admindashboard-config-snippet-note' )->escaped() . '</p>'
					. '<textarea rows="8" class="mw-ui-input" style="width:100%;font-family:monospace;">' . htmlspecialchars( $snippet ) . '</textarea>'
					. '</div>';
			} else {
				$snippetHtml = '<div class="mw-message-box mw-message-box-error">' . $this->msg( 'admindashboard-config-snippet-missing' )->escaped() . '</div>';
			}
		}

		$html = '<div class="mw-body-content">';
		$html .= '<h1>' . $this->msg( 'admindashboard-groups-title' )->text() . '</h1>';
		$html .= $this->makeNav();

		// Groups table with rights and member counts
		$html .= '<h2>' . $this->msg( 'admindashboard-group-rights' )->escaped() . '</h2>';
		$html .= '<table class="wikitable"><tr><th>' . $this->msg( 'admindashboard-group' )->text() . '</th><th>' . $this->msg( 'admindashboard-members' )->text() . '</th><th>' . $this->msg( 'admindashboard-rights' )->text() . '</th></tr>';
		foreach ( $allGroupNames as $group ) {
			$memberCount = (int)$dbr->selectField( 'user_groups', 'COUNT(*)', [ 'ug_group' => $group ], __METHOD__ );
			$rightsList = [];
			if ( isset( $cfgGroupPerms[$group] ) && is_array( $cfgGroupPerms[$group] ) ) {
				foreach ( $cfgGroupPerms[$group] as $right => $allowed ) {
					if ( $allowed ) { $rightsList[] = (string)$right; }
				}
			}
			$html .= '<tr>'
				. '<td>' . htmlspecialchars( $group ) . '</td>'
				. '<td>' . intval( $memberCount ) . '</td>'
				. '<td>' . ( $rightsList ? htmlspecialchars( implode( ', ', $rightsList ) ) : '<em>' . $this->msg( 'admindashboard-none' )->escaped() . '</em>' ) . '</td>'
				. '</tr>';
		}
		$html .= '</table>';

		// Create/modify group form (generates snippet)
		$html .= '<h2 style="margin-top:1.5em;">' . $this->msg( 'admindashboard-create-group' )->escaped() . '</h2>';
		$html .= '<form method="post">';
		// Use plain HTML input to avoid dependency on Html helper class on older MW installs
		$html .= '<input type="hidden" name="generate_group_snippet" value="1">';
		$html .= '<div class="mw-form">';
		$html .= '<div class="field"><label for="group_name">' . $this->msg( 'admindashboard-group-name' )->escaped() . '</label> '
			. '<input type="text" class="mw-ui-input" id="group_name" name="group_name" required placeholder="mygroup"></div>';
		$html .= '<div class="field"><label>' . $this->msg( 'admindashboard-rights' )->escaped() . '</label>';
		$html .= '<div class="rights-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:.5em;max-height:320px;overflow:auto;border:1px solid #ccc;padding:.5em;">';
		foreach ( $availableRights as $right ) {
			$html .= '<label style="display:flex;align-items:center;gap:.4em;">'
				. '<input type="checkbox" name="rights[' . htmlspecialchars( $right ) . ']" value="1">'
				. '<code>' . htmlspecialchars( $right ) . '</code>'
				. '</label>';
		}
		$html .= '</div></div>';
		$html .= '<div class="actions" style="margin-top:.8em;">'
			. '<button type="submit" class="mw-ui-button mw-ui-button-primary">' . $this->msg( 'admindashboard-generate-snippet' )->escaped() . '</button>'
			. '</div>';
		$html .= '</div>';
		$html .= '</form>';

		// If there is a snippet to show, append it
		if ( $snippetHtml ) {
			$html .= $snippetHtml;
		}

		$html .= '</div>';
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
		return $title->getFullURL();
	}
}
