/**
 * Admin Dashboard JavaScript
 */

( function () {
	'use strict';

	/**
	 * Initialize dashboard functionality
	 */
	function initDashboard() {
		attachEventListeners();
		initializeTableSorting();
		initializeUserModal();
	}

	/**
	 * Attach event listeners
	 */
	function attachEventListeners() {
		// User edit buttons
		document.querySelectorAll( '.edit-user' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				const username = this.getAttribute( 'data-user' );
				editUser( username );
			} );
		} );

		// Page edit buttons
		document.querySelectorAll( '.edit-page' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				const pageTitle = this.getAttribute( 'data-page' );
				editPage( pageTitle );
			} );
		} );

		// Group edit buttons
		document.querySelectorAll( '.edit-group' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				const groupName = this.getAttribute( 'data-group' );
				editGroup( groupName );
			} );
		} );
	}

	/**
	 * Initialize user modal behavior and event delegation
	 */
	function initializeUserModal() {
		// Event delegation for username clicks
		document.addEventListener( 'click', function ( e ) {
			const link = e.target.closest( '.user-edit-link' );
			if ( link ) {
				e.preventDefault();
				const row = link.closest( 'tr' );
				if ( !row ) {
					return;
				}
				const userData = {
					id: row.dataset.userId,
					name: row.dataset.userName,
					email: row.dataset.userEmail || '',
					groups: JSON.parse( row.dataset.userGroups || '[]' ),
					registration: row.dataset.userRegistered || '',
					touched: row.dataset.userTouched || ''
				};
				showUserEditModal( userData );
			}
		} );

		// Close modal when clicking close buttons
		document.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '.modal-close' ) ) {
				hideUserEditModal();
			}
		} );

		// Close modal when clicking overlay background
		const overlay = document.getElementById( 'user-edit-modal' );
		if ( overlay ) {
			overlay.addEventListener( 'click', function ( e ) {
				if ( e.target === overlay ) {
					hideUserEditModal();
				}
			} );
		}

		// Add group button
		const addGroupBtn = document.getElementById( 'add-group-btn' );
		if ( addGroupBtn ) {
			addGroupBtn.addEventListener( 'click', function () {
				const select = document.getElementById( 'add-group-select' );
				const groupsList = document.getElementById( 'user-groups-list' );
				const groupName = ( select && select.value ) ? select.value : '';
				if ( groupName && groupsList ) {
					const div = document.createElement( 'div' );
					div.className = 'group-tag';
					div.innerHTML = groupName + ' <button type="button" class="remove-group">×</button>';
					groupsList.appendChild( div );
					select.value = '';
				}
			} );
		}

		// Remove group (delegated)
		document.addEventListener( 'click', function ( e ) {
			if ( e.target && e.target.classList.contains( 'remove-group' ) ) {
				e.preventDefault();
				const parent = e.target.closest( '.group-tag' );
				if ( parent ) parent.remove();
			}
		} );

		// Select all checkbox
		const selectAll = document.getElementById( 'select-all' );
		if ( selectAll ) {
			selectAll.addEventListener( 'change', function () {
				document.querySelectorAll( 'input[name="user_ids[]"]' ).forEach( function ( cb ) {
					cb.checked = selectAll.checked;
				} );
			} );
		}
	}

	function showUserEditModal( user ) {
		// Fill fields
		const idEl = document.getElementById( 'edit-user-id' );
		const nameEl = document.getElementById( 'edit-username' );
		const emailEl = document.getElementById( 'edit-email' );
		const regEl = document.getElementById( 'edit-registered' );
		const touchedEl = document.getElementById( 'edit-last-active' );
		const groupsList = document.getElementById( 'user-groups-list' );
		const modal = document.getElementById( 'user-edit-modal' );

		if ( idEl ) idEl.value = user.id || '';
		if ( nameEl ) nameEl.value = user.name || '';
		if ( emailEl ) emailEl.value = user.email || '';
		if ( regEl ) regEl.textContent = ( user.registration || '' ).substring( 0, 10 );
		if ( touchedEl ) touchedEl.textContent = ( user.touched || '' ).substring( 0, 10 );

		if ( groupsList ) {
			groupsList.innerHTML = '';
			if ( Array.isArray( user.groups ) && user.groups.length ) {
				user.groups.forEach( function ( g ) {
					const div = document.createElement( 'div' );
					div.className = 'group-tag';
					div.innerHTML = g + ' <button type="button" class="remove-group">×</button>';
					groupsList.appendChild( div );
				} );
			} else {
				const em = document.createElement( 'em' );
				em.textContent = 'No groups assigned';
				groupsList.appendChild( em );
			}
		}

		if ( modal ) {
			modal.style.display = 'block';
		}
	}

	function hideUserEditModal() {
		const modal = document.getElementById( 'user-edit-modal' );
		if ( modal ) {
			modal.style.display = 'none';
		}
	}

	/**
	 * Initialize table sorting
	 */
	function initializeTableSorting() {
		document.querySelectorAll( '.wikitable th' ).forEach( function ( header, index ) {
			header.style.cursor = 'pointer';
			header.addEventListener( 'click', function () {
				sortTable( this.closest( 'table' ), index );
			} );
		} );
	}

	/**
	 * Sort table by column
	 * @param {HTMLTableElement} table - The table to sort
	 * @param {number} columnIndex - The index of the column to sort
	 */
	function sortTable( table, columnIndex ) {
		const tbody = table.querySelector( 'tbody' ) || table.querySelector( 'tr' ).parentNode;
		const rows = Array.from( tbody.querySelectorAll( 'tr' ) );

		rows.sort( function ( a, b ) {
			const aText = a.cells[ columnIndex ].textContent.trim();
			const bText = b.cells[ columnIndex ].textContent.trim();

			// Try numeric comparison first
			const aNum = parseFloat( aText );
			const bNum = parseFloat( bText );

			if ( !isNaN( aNum ) && !isNaN( bNum ) ) {
				return aNum - bNum;
			}

			// Fallback to string comparison
			return aText.localeCompare( bText );
		} );

		rows.forEach( function ( row ) {
			tbody.appendChild( row );
		} );
	}

	/**
	 * Edit user
	 * @param {string} username
	 */
	function editUser( username ) {
		// Open a modal or redirect to edit page
		alert( 'Edit user: ' + username );
	}

	/**
	 * Edit page
	 * @param {string} pageTitle
	 */
	function editPage( pageTitle ) {
		// Open a modal or redirect to edit page
		window.location.href = '/wiki/index.php?title=' + encodeURIComponent( pageTitle ) + '&action=edit';
	}

	/**
	 * Edit group
	 * @param {string} groupName
	 */
	function editGroup( groupName ) {
		// Open a modal or redirect to edit page
		alert( 'Edit group: ' + groupName );
	}

	// Initialize when DOM is ready
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initDashboard );
	} else {
		initDashboard();
	}

	// Export functions to global scope for MediaWiki integration
	window.AdminDashboard = {
		editUser: editUser,
		editPage: editPage,
		editGroup: editGroup,
		showUserEditModal: showUserEditModal,
		hideUserEditModal: hideUserEditModal
	};
} )();
