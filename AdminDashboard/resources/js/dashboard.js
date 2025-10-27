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
		editGroup: editGroup
	};
} )();
