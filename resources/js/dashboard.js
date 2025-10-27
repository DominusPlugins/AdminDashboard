/**
 * Admin Dashboard JavaScript (ResourceLoader module)
 */

( function ( $, mw ) {
	'use strict';

	// Minimal diagnostics
	if ( typeof console !== 'undefined' && console.log ) {
		console.log( '[AdminDashboard] scripts module loaded' );
	}

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
		var isSavingUser = false;
		// Event delegation (jQuery) for username clicks
		$( document ).on( 'click', '.user-edit-link', function ( e ) {
			e.preventDefault();
			const row = this.closest( 'tr' );
			if ( !row ) return;
			const userData = {
				id: row.dataset.userId,
				name: row.dataset.userName,
				email: row.dataset.userEmail || '',
				groups: JSON.parse( row.dataset.userGroups || '[]' ),
				registration: row.dataset.userRegistered || '',
				touched: row.dataset.userTouched || ''
			};
			showUserEditModal( userData );
		} );

		// Close modal when clicking close buttons
		$( document ).on( 'click', '.modal-close', function () {
			hideUserEditModal();
		} );

		// Close modal when clicking overlay background
		$( '#user-edit-modal' ).on( 'click', function ( e ) {
			if ( e.target === this ) {
				hideUserEditModal();
			}
		} );

		// Save user (groups) via API userrights
		function handleUserSave( e ) {
			e.preventDefault();
			if ( isSavingUser ) { if ( typeof console !== 'undefined' ) console.log( '[AdminDashboard] Save already in progress' ); return; }
			isSavingUser = true;
			if ( typeof console !== 'undefined' ) console.log( '[AdminDashboard] Save submitted' );
			// Disable save button and show progress
			var saveBtn = document.querySelector( '#user-edit-form button[type="submit"]' );
			if ( saveBtn ) {
				if ( !saveBtn.dataset.origText ) saveBtn.dataset.origText = saveBtn.textContent;
				saveBtn.disabled = true;
				saveBtn.textContent = 'Saving…';
			}
			const username = ( document.getElementById( 'edit-username' ) || {} ).value || '';
			const initGroupsStr = ( document.getElementById( 'initial-groups' ) || {} ).value || '[]';
			let initGroups = [];
			try { initGroups = JSON.parse( initGroupsStr ); } catch ( _e ) {}
			const nowGroups = getGroupsFromUI();
			const d = diffGroups( initGroups, nowGroups );
			if ( !username ) { ( mw.notify ? mw.notify( 'No username found', { type: 'error' } ) : alert( 'No username found' ) ); return; }

			if ( d.add.length === 0 && d.remove.length === 0 ) {
				if ( typeof console !== 'undefined' ) console.log( '[AdminDashboard] No changes detected' );
				mw.notify && mw.notify( 'No changes to save', { type: 'info' } );
				hideUserEditModal();
				return;
			}

			const api = new mw.Api();
			const params = {
				action: 'userrights',
				format: 'json',
				user: username
			};
			if ( d.add.length ) { params.add = d.add.join( '|' ); }
			if ( d.remove.length ) { params.remove = d.remove.join( '|' ); }
			api.postWithToken( 'csrf', params ).done( function ( data ) {
				if ( typeof console !== 'undefined' ) console.log( '[AdminDashboard] userrights success', data );
				mw.notify && mw.notify( 'User groups updated', { type: 'success' } );
				hideUserEditModal();
			} ).fail( function ( err ) {
				if ( typeof console !== 'undefined' ) console.error( '[AdminDashboard] userrights failed', err );
				mw.notify && mw.notify( 'Failed to update groups: ' + ( err && err.error && err.error.info || 'Unknown error' ), { type: 'error' } );
			} ).always( function () {
				isSavingUser = false;
				if ( saveBtn ) {
					saveBtn.disabled = false;
					saveBtn.textContent = saveBtn.dataset.origText || 'Save';
				}
			} );
		}

		$( document ).on( 'submit', '#user-edit-form', handleUserSave );
		// Safety: also catch direct button clicks
		$( document ).on( 'click', '#user-edit-form button[type="submit"]', handleUserSave );

		// Block user via core API
		$( document ).on( 'click', '#block-user-btn', function () {
			const username = ( document.getElementById( 'edit-username' ) || {} ).value || '';
			if ( !username ) { mw.notify( 'No username to block', { type: 'error' } ); return; }
			const api = new mw.Api();
			api.postWithToken( 'csrf', {
				action: 'block',
				user: username,
				reason: 'Blocked via AdminDashboard',
				expiry: '2 weeks',
				nocreate: 1,
				autoblock: 1,
				format: 'json'
			} ).done( function () {
				mw.notify( 'User blocked', { type: 'success' } );
				hideUserEditModal();
			} ).fail( function ( err ) {
				mw.notify( 'Failed to block user: ' + ( err && err.error && err.error.info || 'Unknown error' ), { type: 'error' } );
			} );
		} );

		// Bulk actions
		$( document ).on( 'click', '#bulk-apply-btn', function () {
			const actionSel = document.getElementById( 'bulk-action-select' );
			const actionVal = actionSel ? actionSel.value : '';
			if ( !actionVal ) { mw.notify( 'Select a bulk action', { type: 'warn' } ); return; }
			const ids = Array.from( document.querySelectorAll( 'input[name="user_ids[]"]:checked' ) )
				.map( function ( cb ) { return cb.closest( 'tr' ); } )
				.filter( Boolean )
				.map( function ( row ) { return {
					name: row.dataset.userName
				}; } )
				.filter( function ( u ) { return !!u.name; } );
			if ( ids.length === 0 ) { mw.notify( 'Select at least one user', { type: 'warn' } ); return; }

			const api = new mw.Api();
			const calls = ids.map( function ( u ) {
				if ( actionVal === 'promote' ) {
					return api.postWithToken( 'csrf', { action: 'userrights', user: u.name, add: 'sysop', format: 'json' } );
				} else if ( actionVal === 'demote' ) {
					return api.postWithToken( 'csrf', { action: 'userrights', user: u.name, remove: 'sysop', format: 'json' } );
				} else if ( actionVal === 'block' ) {
					return api.postWithToken( 'csrf', { action: 'block', user: u.name, reason: 'Bulk block (AdminDashboard)', expiry: '2 weeks', nocreate: 1, autoblock: 1, format: 'json' } );
				}
				return Promise.resolve();
			} );

			Promise.allSettled( calls ).then( function ( results ) {
				const failed = results.filter( function ( r ) { return r.status === 'rejected'; } );
				if ( failed.length ) {
					mw.notify( 'Some actions failed (' + failed.length + ')', { type: 'warn' } );
				} else {
					mw.notify( 'Bulk action completed', { type: 'success' } );
				}
			} );
		} );

		// Add group button
		$( document ).on( 'click', '#add-group-btn', function () {
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

		// Remove group (delegated)
		$( document ).on( 'click', '.remove-group', function ( e ) {
			e.preventDefault();
			const parent = this.closest( '.group-tag' );
			if ( parent ) parent.remove();
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

		// Store initial groups for diffing on save
		try {
			const initEl = document.getElementById( 'initial-groups' );
			if ( initEl ) initEl.value = JSON.stringify( Array.isArray( user.groups ) ? user.groups : [] );
		} catch ( e ) {}

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

	function getGroupsFromUI() {
		const list = document.getElementById( 'user-groups-list' );
		if ( !list ) return [];
		return Array.from( list.querySelectorAll( '.group-tag' ) )
			.map( function ( el ) { return el.textContent.replace( '×', '' ).trim(); } )
			.filter( Boolean );
	}

	function diffGroups( before, after ) {
		const b = new Set( before );
		const a = new Set( after );
		const add = Array.from( a ).filter( function ( g ) { return !b.has( g ); } );
		const remove = Array.from( b ).filter( function ( g ) { return !a.has( g ); } );
		return { add: add, remove: remove };
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

	// Initialize on DOM ready and when content is re-rendered
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initDashboard );
	} else {
		initDashboard();
	}
	// MediaWiki hook for content ready (supports PJAX or skin reflows)
	mw.hook( 'wikipage.content' ).add( function () {
		initDashboard();
	} );

	// Export functions to global scope for MediaWiki integration
	window.AdminDashboard = {
		editUser: editUser,
		editPage: editPage,
		editGroup: editGroup,
		showUserEditModal: showUserEditModal,
		hideUserEditModal: hideUserEditModal
	};
} )( jQuery, mediaWiki );
