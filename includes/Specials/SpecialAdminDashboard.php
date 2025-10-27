<?php

namespace MediaWiki\Extension\AdminDashboard\Specials;

use SpecialPage;
use MediaWiki\MediaWikiServices;

/**
 * Special page for Admin Dashboard
 */
class SpecialAdminDashboard extends SpecialPage {

	public function __construct() {
		// Inline JS removed; handled by ext.AdminDashboard.scripts module
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

		// Build page HTML
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
		$html .= '<button type="submit" class="mw-ui-button mw-ui-button-destructive">' . $this->msg( 'admindashboard-apply' )->text() . '</button>';
		$html .= '</div>';
		$html .= '</div>';

		$html .= '</div>';
		$html .= '</div>';
		$html .= '</form>'; // Close the search form

		// Start a new form for bulk actions
		$html .= '<form method="POST" id="bulk-actions-form">';
		$html .= '<input type="hidden" name="bulk_action" id="bulk-action-value">';
		$html .= '<input type="hidden" name="user_ids" id="selected-user-ids">';
		$html .= '</form>';

		$html .= '<table class="wikitable sortable">';
		$html .= '<tr><th><input type="checkbox" id="select-all"></th><th>' . $this->msg( 'admindashboard-username' )->text() . '</th><th>' . $this->msg( 'admindashboard-groups' )->text() . '</th><th>' . $this->msg( 'admindashboard-registered' )->text() . '</th><th>' . $this->msg( 'admindashboard-last-active' )->text() . '</th><th>' . $this->msg( 'admindashboard-email' )->text() . '</th><th>' . $this->msg( 'admindashboard-status' )->text() . '</th></tr>';

		foreach ( $users as $userId => $user ) {
			$blocked = isset( $blockInfo[$userId] ) ? 'Blocked' : 'Active';
			$blockClass = $blocked === 'Blocked' ? ' class="mw-ui-destructive"' : '';
			
			$groups = !empty( $user['groups'] ) ? implode( ', ', array_map( 'htmlspecialchars', $user['groups'] ) ) : $this->msg( 'admindashboard-none' )->text();
			
			$html .= '<tr' . $blockClass . ' data-user-id="' . intval( $userId ) . '" data-user-name="' . htmlspecialchars( $user['name'] ) . '" data-user-email="' . htmlspecialchars( $user['email'] ?? '' ) . '" data-user-groups="' . htmlspecialchars( json_encode( $user['groups'] ) ) . '" data-user-registered="' . htmlspecialchars( $user['registration'] ) . '" data-user-touched="' . htmlspecialchars( $user['touched'] ) . '">';
			$html .= '<td><input type="checkbox" name="user_ids[]" value="' . intval( $userId ) . '"></td>';
			$html .= '<td><a href="#" class="user-edit-link" data-user-id="' . intval( $userId ) . '">' . htmlspecialchars( $user['name'] ) . '</a></td>';
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

		// User Edit Modal
		$html .= '<div id="user-edit-modal" class="modal-overlay" style="display: none;">';
		$html .= '<div class="modal-content">';
		$html .= '<div class="modal-header">';
		$html .= '<h3 id="modal-title">' . $this->msg( 'admindashboard-edit-user' )->text() . '</h3>';
		$html .= '<button type="button" class="modal-close mw-ui-button">×</button>';
		$html .= '</div>';
		$html .= '<form id="user-edit-form">';
		$html .= '<input type="hidden" id="edit-user-id" name="user_id">';

		// User details section
		$html .= '<fieldset>';
		$html .= '<legend>' . $this->msg( 'admindashboard-user-details' )->text() . '</legend>';

		$html .= '<div>';
		$html .= '<label for="edit-username">' . $this->msg( 'admindashboard-username' )->text() . ':</label>';
		$html .= '<input type="text" id="edit-username" class="mw-ui-input" readonly>';
		$html .= '</div>';

		$html .= '<div>';
		$html .= '<label for="edit-email">' . $this->msg( 'admindashboard-email' )->text() . ':</label>';
		$html .= '<input type="email" id="edit-email" name="email" class="mw-ui-input">';
		$html .= '</div>';

		$html .= '<div>';
		$html .= '<label>' . $this->msg( 'admindashboard-registered' )->text() . ':</label>';
		$html .= '<span id="edit-registered"></span>';
		$html .= '</div>';

		$html .= '<div>';
		$html .= '<label>' . $this->msg( 'admindashboard-last-active' )->text() . ':</label>';
		$html .= '<span id="edit-last-active"></span>';
		$html .= '</div>';
		$html .= '</fieldset>';

		// User groups section
		$html .= '<fieldset>';
		$html .= '<legend>' . $this->msg( 'admindashboard-user-groups' )->text() . '</legend>';
		$html .= '<div id="user-groups-list"></div>';

		$html .= '<div>';
		$html .= '<label for="add-group-select">' . $this->msg( 'admindashboard-add-group' )->text() . ':</label>';
		$html .= '<select id="add-group-select" class="mw-ui-select">';
		$html .= '<option value="">' . $this->msg( 'admindashboard-select-group' )->text() . '</option>';
		$html .= '<option value="sysop">' . $this->msg( 'admindashboard-sysop' )->text() . '</option>';
		$html .= '<option value="bureaucrat">' . $this->msg( 'admindashboard-bureaucrat' )->text() . '</option>';
		$html .= '<option value="bot">' . $this->msg( 'admindashboard-bot' )->text() . '</option>';
		$html .= '<option value="interface-admin">' . $this->msg( 'admindashboard-interface-admin' )->text() . '</option>';
		$html .= '</select>';
		$html .= '<button type="button" id="add-group-btn" class="mw-ui-button mw-ui-button-primary">' . $this->msg( 'admindashboard-add' )->text() . '</button>';
		$html .= '</div>';
		$html .= '</fieldset>';

		// Action buttons
		$html .= '<div class="modal-footer">';
		$html .= '<button type="button" class="modal-close mw-ui-button">' . $this->msg( 'admindashboard-cancel' )->text() . '</button>';
		$html .= '<button type="submit" class="mw-ui-button mw-ui-button-primary">' . $this->msg( 'admindashboard-save' )->text() . '</button>';
		$html .= '<button type="button" id="block-user-btn" class="mw-ui-button mw-ui-button-destructive">' . $this->msg( 'admindashboard-block-user' )->text() . '</button>';
		$html .= '</div>';

		$html .= '</form>';
		$html .= '</div>';
		$html .= '</div>';

		// Add JavaScript for modal functionality
		$html .= '<script>
		function handleUserClick(event, link) {
			event.preventDefault();
			alert("You clicked on: " + link.textContent);
			const row = link.closest("tr");
			const userData = {
				id: row.dataset.userId,
				name: row.dataset.userName,
				email: row.dataset.userEmail,
				groups: JSON.parse(row.dataset.userGroups || "[]"),
				registration: row.dataset.userRegistered,
				touched: row.dataset.userTouched
			};
			showUserEditModal(userData);
		}

		// Modal functionality
		function showUserEditModal(userData) {
			document.getElementById("edit-user-id").value = userData.id;
			document.getElementById("edit-username").value = userData.name;
			document.getElementById("edit-email").value = userData.email || "";
			document.getElementById("edit-registered").textContent = userData.registration.substring(0, 10);
			document.getElementById("edit-last-active").textContent = userData.touched.substring(0, 10);

			// Display user groups
			const groupsList = document.getElementById("user-groups-list");
			groupsList.innerHTML = "";
			if (userData.groups && userData.groups.length > 0) {
				userData.groups.forEach(function(group) {
					const groupDiv = document.createElement("div");
					groupDiv.className = "group-tag";
					groupDiv.innerHTML = group + " <button type=\"button\" onclick=\"removeGroup(this, \'" + group + "\')\">×</button>";
					groupsList.appendChild(groupDiv);
				});
			} else {
				groupsList.innerHTML = "<em>' . addslashes( $this->msg( 'admindashboard-no-groups' )->text() ) . '</em>";
			}

			document.getElementById("user-edit-modal").style.display = "block";
		}

		function hideUserEditModal() {
			document.getElementById("user-edit-modal").style.display = "none";
		}

		function removeGroup(button, groupName) {
			button.parentElement.remove();
		}

		// Event listeners - run immediately and on DOMContentLoaded
		function initializeModal() {
			console.log("Modal initialization started");
			
			// Find all user edit links and bind click handlers directly
			var links = document.querySelectorAll(".user-edit-link");
			console.log("Found " + links.length + " user edit links");
			
			links.forEach(function(link) {
				link.addEventListener("click", function(e) {
					e.preventDefault();
					console.log("User edit link clicked:", this.textContent);
					const row = this.closest("tr");
					const userData = {
						id: row.dataset.userId,
						name: row.dataset.userName,
						email: row.dataset.userEmail,
						groups: JSON.parse(row.dataset.userGroups || "[]"),
						registration: row.dataset.userRegistered,
						touched: row.dataset.userTouched
					};
					console.log("User data:", userData);
					showUserEditModal(userData);
				});
			});

			// Modal close buttons
			document.querySelectorAll(".modal-close").forEach(function(btn) {
				btn.addEventListener("click", hideUserEditModal);
			});

			// Click outside modal to close
			var modal = document.getElementById("user-edit-modal");
			if (modal) {
				modal.addEventListener("click", function(e) {
					if (e.target === this) {
						hideUserEditModal();
					}
				});
			}

			// Add group functionality
			var addGroupBtn = document.getElementById("add-group-btn");
			if (addGroupBtn) {
				addGroupBtn.addEventListener("click", function() {
					const select = document.getElementById("add-group-select");
					const groupName = select.value;
					if (groupName) {
						const groupsList = document.getElementById("user-groups-list");
						const groupDiv = document.createElement("div");
						groupDiv.className = "group-tag";
						groupDiv.innerHTML = groupName + " <button type=\"button\" onclick=\"removeGroup(this, \'" + groupName + "\')\">×</button>";
						groupsList.appendChild(groupDiv);
						select.value = "";
					}
				});
			}

			// Select all checkbox functionality
			var selectAll = document.getElementById("select-all");
			if (selectAll) {
				selectAll.addEventListener("change", function() {
					var checkboxes = document.querySelectorAll("input[name=\"user_ids[]\"]");
					checkboxes.forEach(function(checkbox) {
						checkbox.checked = document.getElementById("select-all").checked;
					});
				});
			}
		}

		// Initialize when ready
		if (document.readyState === "loading") {
			document.addEventListener("DOMContentLoaded", function() {
				console.log("DOMContentLoaded fired, initializing modal");
				initializeModal();
			});
		} else {
			console.log("Document already loaded, initializing modal immediately");
			initializeModal();
		}
		
		// Also try initializing after a short delay
		setTimeout(function() {
			console.log("Delayed initialization check");
			var links = document.querySelectorAll(".user-edit-link:not([data-initialized])");
			if (links.length > 0) {
				console.log("Found uninitialized links, reinitializing");
				initializeModal();
			}
		}, 500);
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
			// Build the page URL properly using Title object
			$title = \Title::makeTitleSafe( $row->page_namespace, $row->page_title );
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
		return $title->getFullURL();
	}
}
