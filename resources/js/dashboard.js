/**
 * Admin Dashboard JavaScript (ResourceLoader module)
 */

( function ( $, mw ) {
	'use strict';

	// Prevent attaching duplicate event handlers across re-renders
	var adminDashboardEventsBound = false;

	// Minimal diagnostics
	if ( typeof console !== 'undefined' && console.log ) {
		console.log( '[AdminDashboard] scripts module loaded' );
	}

	/**
	 * Initialize dashboard functionality
	 */
	function initDashboard() {
		if ( adminDashboardEventsBound ) { return; }
		attachEventListeners();
		initializeTableSorting();
		initializeUserModal();
		adminDashboardEventsBound = true;
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
		// Ensure modal exists; if not, create a minimal one so clicks still work
		function ensureModalExists() {
			if ( document.getElementById( 'user-edit-modal' ) ) { return; }
			if ( typeof console !== 'undefined' ) console.warn( '[AdminDashboard] Modal not found in DOM. Injecting fallback modal skeleton.' );
			var wrapper = document.createElement( 'div' );
			wrapper.innerHTML = '' +
				'<div id="user-edit-modal" class="modal-overlay" style="display:none;">' +
				'	<div class="modal-content">' +
				'		<div class="modal-header">' +
				'			<h3 id="modal-title">Edit user</h3>' +
				'			<button type="button" class="modal-close mw-ui-button">×</button>' +
				'		</div>' +
				'		<form id="user-edit-form">' +
				'			<input type="hidden" id="edit-user-id" name="user_id">' +
				'			<input type="hidden" id="initial-groups" value="[]">' +
				'			<fieldset>' +
				'				<legend>User details</legend>' +
				'				<div><label for="edit-username">Username:</label><input type="text" id="edit-username" class="mw-ui-input" readonly></div>' +
				'				<div><label for="edit-email">Email:</label><input type="email" id="edit-email" name="email" class="mw-ui-input"></div>' +
				'				<div><label>Registered:</label><span id="edit-registered"></span></div>' +
				'				<div><label>Last active:</label><span id="edit-last-active"></span></div>' +
				'			</fieldset>' +
				'			<fieldset>' +
				'				<legend>User groups</legend>' +
				'				<div id="user-groups-list"></div>' +
				'				<div><label for="add-group-select">Add group:</label><select id="add-group-select" class="mw-ui-select"><option value="">Select a group</option><option value="sysop">sysop</option><option value="bureaucrat">bureaucrat</option><option value="bot">bot</option><option value="interface-admin">interface-admin</option></select> <button type="button" id="add-group-btn" class="mw-ui-button mw-ui-button-primary">Add</button></div>' +
				'			</fieldset>' +
				'			<div class="modal-footer">' +
				'				<button type="button" class="modal-close mw-ui-button">Cancel</button>' +
				'				<button type="submit" class="mw-ui-button mw-ui-button-primary">Save</button>' +
				'				<button type="button" id="block-user-btn" class="mw-ui-button mw-ui-button-destructive">Block user</button>' +
				'			</div>' +
				'		</form>' +
				'	</div>' +
				'</div>';
			var modal = wrapper.firstChild;
			document.body.appendChild( modal );
		}

		ensureModalExists();

		function notify( message, opts ) {
			if ( mw && typeof mw.notify === 'function' ) {
				mw.notify( message, opts || {} );
			} else if ( typeof alert === 'function' ) {
				alert( message );
			} else if ( typeof console !== 'undefined' ) {
				console.log( '[AdminDashboard notify]', message, opts );
			}
		}
		// Event delegation (jQuery) for username clicks
		$( document ).off( 'click.adminDashboard', '.user-edit-link' ).on( 'click.adminDashboard', '.user-edit-link', function ( e ) {
			e.preventDefault();
			const row = this.closest( 'tr' );
			if ( !row ) { if ( typeof console !== 'undefined' ) console.warn( '[AdminDashboard] Click on .user-edit-link but no row found' ); return; }
			// Mark handled to avoid fallback duplicate handling
			this.dataset.adHandled = '1';
			setTimeout( () => { try { delete this.dataset.adHandled; } catch ( _e ) {} }, 0 );
			const userData = {
				id: row.dataset.userId,
				name: row.dataset.userName,
				email: row.dataset.userEmail || '',
				groups: JSON.parse( row.dataset.userGroups || '[]' ),
				registration: row.dataset.userRegistered || '',
				touched: row.dataset.userTouched || ''
			};
			if ( typeof console !== 'undefined' ) console.log( '[AdminDashboard] Opening modal for user', userData.name );
			showUserEditModal( userData );
		} );

		// Close modal when clicking close buttons
		$( document ).off( 'click.adminDashboard', '.modal-close' ).on( 'click.adminDashboard', '.modal-close', function () {
			hideUserEditModal();
		} );

		// Close modal when clicking overlay background (delegated)
		$( document ).off( 'click.adminDashboard', '#user-edit-modal' ).on( 'click.adminDashboard', '#user-edit-modal', function ( e ) {
			if ( e.target === this ) {
				hideUserEditModal();
			}
		} );

		// Helpers to update the table UI without a full page refresh
		function findRowByUserId( userId ) {
			return document.querySelector( 'tr[data-user-id="' + String( userId ) + '"]' );
		}

		function findRowByUserName( name ) {
			const rows = document.querySelectorAll( 'table.wikitable tr[data-user-name]' );
			for ( var i = 0; i < rows.length; i++ ) {
				if ( rows[i].dataset.userName === name ) return rows[i];
			}
			return null;
		}

		function renderGroupsCell( row, groupsArr ) {
			if ( !row ) return;
			row.dataset.userGroups = JSON.stringify( groupsArr || [] );
			var cell = row.cells && row.cells[2];
			if ( cell ) {
				cell.textContent = ( groupsArr && groupsArr.length ) ? groupsArr.join( ', ' ) : 'None';
			}
		}

		function setBlocked( row, blocked, reason ) {
			if ( !row ) return;
			var statusCell = row.cells && row.cells[6];
			if ( blocked ) {
				row.classList.add( 'mw-ui-destructive' );
				if ( statusCell ) {
					statusCell.innerHTML = '<span class="mw-ui-destructive">Blocked</span>' + ( reason ? '<br><small>' + reason.replace( /</g, '&lt;' ) + '</small>' : '' );
				}
			} else {
				row.classList.remove( 'mw-ui-destructive' );
				if ( statusCell ) {
					statusCell.innerHTML = '<span class="mw-ui-progressive">Active</span>';
				}
			}
		}

		// Helper: POST with a specific token type and retry once on badtoken
		function apiPostWithToken( params, tokenType, retry ) {
			retry = typeof retry === 'number' ? retry : 1;
			tokenType = tokenType || 'csrf';
			const api = new mw.Api();
			const base = Object.assign( { format: 'json', assert: 'user', origin: ( window && window.location && window.location.origin ) ? window.location.origin : undefined }, params );
			var dfd = $.Deferred();

			function postWith( token, mayRetry ) {
				const fullParams = Object.assign( {}, base, { token: token } );
				api.post( fullParams ).done( function ( data ) {
					dfd.resolve( data );
				} ).fail( function ( err ) {
					const code = err && err.error && err.error.code;
					if ( code === 'badtoken' && mayRetry && retry > 0 ) {
						if ( typeof console !== 'undefined' ) console.warn( '[AdminDashboard] badtoken, fetching fresh token and retrying' );
						api.getToken( tokenType ).done( function ( fresh ) {
							postWith( fresh, false );
						} ).fail( function ( e2 ) {
							dfd.reject( e2 );
						} );
					} else {
						dfd.reject( err );
					}
				} );
			}

			try {
				var cached = null;
				if ( mw.user && mw.user.tokens && mw.user.tokens.get ) {
					// mw.user.tokens.get expects keys like 'csrfToken', 'userrightsToken'
					var key = tokenType + 'Token';
					cached = mw.user.tokens.get( key );
				}
				if ( cached ) {
					postWith( cached, true );
				} else {
					api.getToken( tokenType ).done( function ( token ) { postWith( token, true ); } ).fail( function ( e ) { dfd.reject( e ); } );
				}
			} catch ( _e ) {
				api.getToken( tokenType ).done( function ( token ) { postWith( token, true ); } ).fail( function ( e ) { dfd.reject( e ); } );
			}

			return dfd.promise();
		}

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
			const userId = ( document.getElementById( 'edit-user-id' ) || {} ).value || '';
			const initGroupsStr = ( document.getElementById( 'initial-groups' ) || {} ).value || '[]';
			let initGroups = [];
			try { initGroups = JSON.parse( initGroupsStr ); } catch ( _e ) {}
			const nowGroups = getGroupsFromUI();
			const d = diffGroups( initGroups, nowGroups );
			if ( !username ) {
				notify( 'No username found', { type: 'error' } );
				isSavingUser = false;
				if ( saveBtn ) { saveBtn.disabled = false; saveBtn.textContent = saveBtn.dataset.origText || 'Save'; }
				return;
			}

			if ( d.add.length === 0 && d.remove.length === 0 ) {
				if ( typeof console !== 'undefined' ) console.log( '[AdminDashboard] No changes detected' );
				notify( 'No changes to save', { type: 'info' } );
				hideUserEditModal();
				isSavingUser = false;
				if ( saveBtn ) { saveBtn.disabled = false; saveBtn.textContent = saveBtn.dataset.origText || 'Save'; }
				return;
			}

			const params = {
				action: 'userrights',
				user: username
			};
			if ( d.add.length ) { params.add = d.add.join( '|' ); }
			if ( d.remove.length ) { params.remove = d.remove.join( '|' ); }
			apiPostWithToken( params, 'userrights' ).done( function ( data ) {
				if ( typeof console !== 'undefined' ) console.log( '[AdminDashboard] userrights success', data );
				notify( 'User groups updated', { type: 'success' } );
				// Update table row groups immediately
				try {
					var row = userId ? findRowByUserId( userId ) : findRowByUserName( username );
					renderGroupsCell( row, nowGroups );
				} catch ( _e ) {}
				hideUserEditModal();
			} ).fail( function ( err ) {
				if ( typeof console !== 'undefined' ) console.error( '[AdminDashboard] userrights failed', err );
				notify( 'Failed to update groups: ' + ( err && err.error && ( err.error.info || err.error.code ) || 'Unknown error' ), { type: 'error' } );
			} ).always( function () {
				isSavingUser = false;
				if ( saveBtn ) {
					saveBtn.disabled = false;
					saveBtn.textContent = saveBtn.dataset.origText || 'Save';
				}
			} );
		}

		$( document ).off( 'submit.adminDashboard', '#user-edit-form' ).on( 'submit.adminDashboard', '#user-edit-form', handleUserSave );

		// Block user via core API
		$( document ).off( 'click.adminDashboard', '#block-user-btn' ).on( 'click.adminDashboard', '#block-user-btn', function () {
			const username = ( document.getElementById( 'edit-username' ) || {} ).value || '';
			if ( !username ) { notify( 'No username to block', { type: 'error' } ); return; }
			const userId = ( document.getElementById( 'edit-user-id' ) || {} ).value || '';
			apiPostWithToken( {
				action: 'block',
				user: username,
				reason: 'Blocked via AdminDashboard',
				expiry: '2 weeks',
				nocreate: 1,
				autoblock: 1
			}, 'csrf' ).done( function () {
				notify( 'User blocked', { type: 'success' } );
				try {
					var row = userId ? findRowByUserId( userId ) : findRowByUserName( username );
					setBlocked( row, true, 'Blocked via AdminDashboard' );
				} catch ( _e ) {}
				hideUserEditModal();
			} ).fail( function ( err ) {
				notify( 'Failed to block user: ' + ( err && err.error && err.error.info || 'Unknown error' ), { type: 'error' } );
			} );
		} );

		// Bulk actions
		$( document ).off( 'click.adminDashboard', '#bulk-apply-btn' ).on( 'click.adminDashboard', '#bulk-apply-btn', function () {
			const actionSel = document.getElementById( 'bulk-action-select' );
			const actionVal = actionSel ? actionSel.value : '';
			if ( !actionVal ) { notify( 'Select a bulk action', { type: 'warn' } ); return; }
			const ids = Array.from( document.querySelectorAll( 'input[name="user_ids[]"]:checked' ) )
				.map( function ( cb ) { return cb.closest( 'tr' ); } )
				.filter( Boolean )
				.map( function ( row ) { return {
					name: row.dataset.userName
				}; } )
				.filter( function ( u ) { return !!u.name; } );
			if ( ids.length === 0 ) { notify( 'Select at least one user', { type: 'warn' } ); return; }

			const calls = ids.map( function ( u ) {
				if ( actionVal === 'promote' ) {
					return new Promise( function ( resolve, reject ) { apiPostWithToken( { action: 'userrights', user: u.name, add: 'sysop' }, 'userrights' ).done( resolve ).fail( reject ); } );
				} else if ( actionVal === 'demote' ) {
					return new Promise( function ( resolve, reject ) { apiPostWithToken( { action: 'userrights', user: u.name, remove: 'sysop' }, 'userrights' ).done( resolve ).fail( reject ); } );
				} else if ( actionVal === 'block' ) {
					return new Promise( function ( resolve, reject ) { apiPostWithToken( { action: 'block', user: u.name, reason: 'Bulk block (AdminDashboard)', expiry: '2 weeks', nocreate: 1, autoblock: 1 }, 'csrf' ).done( resolve ).fail( reject ); } );
				}
				return Promise.resolve();
			} );

			Promise.allSettled( calls ).then( function ( results ) {
				const failed = results.filter( function ( r ) { return r.status === 'rejected'; } );
				if ( failed.length ) {
					notify( 'Some actions failed (' + failed.length + ')', { type: 'warn' } );
				} else {
					notify( 'Bulk action completed', { type: 'success' } );
				}
				// Update table rows to reflect the bulk action
				try {
					ids.forEach( function ( u, idx ) {
						if ( results[idx].status !== 'fulfilled' ) return;
						var row = findRowByUserName( u.name );
						if ( !row ) return;
						if ( actionVal === 'promote' ) {
							var groups = [];
							try { groups = JSON.parse( row.dataset.userGroups || '[]' ); } catch ( _e ) {}
							if ( groups.indexOf( 'sysop' ) === -1 ) groups.push( 'sysop' );
							renderGroupsCell( row, groups );
						} else if ( actionVal === 'demote' ) {
							var groups2 = [];
							try { groups2 = JSON.parse( row.dataset.userGroups || '[]' ); } catch ( _e2 ) {}
							groups2 = groups2.filter( function ( g ) { return g !== 'sysop'; } );
							renderGroupsCell( row, groups2 );
						} else if ( actionVal === 'block' ) {
							setBlocked( row, true, 'Bulk block (AdminDashboard)' );
						}
					} );
				} catch ( _e ) {}
			} );
		} );

		// Add group button
		$( document ).off( 'click.adminDashboard', '#add-group-btn' ).on( 'click.adminDashboard', '#add-group-btn', function () {
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
		$( document ).off( 'click.adminDashboard', '.remove-group' ).on( 'click.adminDashboard', '.remove-group', function ( e ) {
			e.preventDefault();
			const parent = this.closest( '.group-tag' );
			if ( parent ) parent.remove();
		} );

		// Select all checkbox (delegated)
		$( document ).off( 'change.adminDashboard', '#select-all' ).on( 'change.adminDashboard', '#select-all', function () {
			var checked = this.checked;
			document.querySelectorAll( 'input[name="user_ids[]"]' ).forEach( function ( cb ) { cb.checked = checked; } );
		} );
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
		if ( !modal ) { if ( typeof console !== 'undefined' ) console.error( '[AdminDashboard] Modal element #user-edit-modal not found' ); return; }

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

		modal.style.display = 'block';
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
