<?php

namespace MediaWiki\Extension\AdminDashboard\Specials;

use MediaWiki\Specials\SpecialPage;
use MediaWiki\Extension\AdminDashboard\Managers\UserManager;
use MediaWiki\Extension\AdminDashboard\Managers\PageManager;
use MediaWiki\Extension\AdminDashboard\Managers\PermissionManager;
use MediaWiki\Extension\AdminDashboard\Managers\StatisticsManager;

/**
 * Special page for Admin Dashboard
 */
class SpecialAdminDashboard extends SpecialPage {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'AdminDashboard', 'adminboard' );
	}

	/**
	 * Execute special page
	 *
	 * @param string|null $par Subpage
	 */
	public function execute( $par ) {
		$this->setHeaders();
		$this->checkPermissions();

		// Add resources
		$this->getOutput()->addModuleStyles( 'ext.AdminDashboard.styles' );
		$this->getOutput()->addModules( 'ext.AdminDashboard.scripts' );

		// Get the action from the parameter
		$action = $par ?? 'overview';

		// Render appropriate section
		switch ( $action ) {
			case 'users':
				$this->showUserManagement();
				break;
			case 'pages':
				$this->showPageManagement();
				break;
			case 'permissions':
				$this->showPermissionManagement();
				break;
			case 'statistics':
				$this->showStatistics();
				break;
			case 'logs':
				$this->showLogs();
				break;
			case 'moderation':
				$this->showModeration();
				break;
			case 'configuration':
				$this->showConfiguration();
				break;
			case 'extensions':
				$this->showExtensions();
				break;
			default:
				$this->showOverview();
		}
	}

	/**
	 * Show dashboard overview
	 */
	private function showOverview() {
		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'admindashboard-title' ) );

		$html = '<div class="admin-dashboard">';
		$html .= '<h1>' . $this->msg( 'admindashboard-overview' )->text() . '</h1>';
		$html .= '<div class="dashboard-grid">';

		// Dashboard cards with statistics
		$statsManager = new StatisticsManager();
		$stats = $statsManager->getBasicStats();

		$html .= $this->makeDashboardCard(
			'Users',
			$stats['users'],
			'users',
			'👥'
		);

		$html .= $this->makeDashboardCard(
			'Pages',
			$stats['pages'],
			'pages',
			'📄'
		);

		$html .= $this->makeDashboardCard(
			'Edits',
			$stats['edits'],
			'statistics',
			'✏️'
		);

		$html .= $this->makeDashboardCard(
			'Active Users',
			$stats['activeUsers'],
			'users',
			'🟢'
		);

		$html .= '</div>';
		$html .= $this->makeNavigation();
		$html .= '</div>';

		$out->addHTML( $html );
	}

	/**
	 * Show user management section
	 */
	private function showUserManagement() {
		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'admindashboard-users' ) );

		$userManager = new UserManager();
		$users = $userManager->getAllUsers();

		$html = '<div class="admin-section">';
		$html .= '<h1>' . $this->msg( 'admindashboard-users' )->text() . '</h1>';
		$html .= $this->makeNavigation();
		$html .= '<div class="user-table">';
		$html .= '<table class="wikitable sortable">';
		$html .= '<tr><th>Username</th><th>Created</th><th>Last Active</th><th>Groups</th><th>Actions</th></tr>';

		foreach ( $users as $user ) {
			$html .= '<tr>';
			$html .= '<td>' . htmlspecialchars( $user['name'] ) . '</td>';
			$html .= '<td>' . htmlspecialchars( $user['created'] ) . '</td>';
			$html .= '<td>' . htmlspecialchars( $user['lastActive'] ) . '</td>';
			$html .= '<td>' . implode( ', ', $user['groups'] ) . '</td>';
			$html .= '<td><button class="edit-user" data-user="' . htmlspecialchars( $user['name'] ) . '">Edit</button></td>';
			$html .= '</tr>';
		}

		$html .= '</table>';
		$html .= '</div>';
		$html .= '</div>';

		$out->addHTML( $html );
	}

	/**
	 * Show page management section
	 */
	private function showPageManagement() {
		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'admindashboard-pages' ) );

		$pageManager = new PageManager();
		$pages = $pageManager->getRecentPages( 100 );

		$html = '<div class="admin-section">';
		$html .= '<h1>' . $this->msg( 'admindashboard-pages' )->text() . '</h1>';
		$html .= $this->makeNavigation();
		$html .= '<div class="pages-table">';
		$html .= '<table class="wikitable sortable">';
		$html .= '<tr><th>Title</th><th>Last Modified</th><th>By</th><th>Revisions</th><th>Actions</th></tr>';

		foreach ( $pages as $page ) {
			$html .= '<tr>';
			$html .= '<td><a href="' . htmlspecialchars( $page['url'] ) . '">' . htmlspecialchars( $page['title'] ) . '</a></td>';
			$html .= '<td>' . htmlspecialchars( $page['lastModified'] ) . '</td>';
			$html .= '<td>' . htmlspecialchars( $page['lastUser'] ) . '</td>';
			$html .= '<td>' . intval( $page['revisions'] ) . '</td>';
			$html .= '<td><button class="edit-page" data-page="' . htmlspecialchars( $page['title'] ) . '">Edit</button></td>';
			$html .= '</tr>';
		}

		$html .= '</table>';
		$html .= '</div>';
		$html .= '</div>';

		$out->addHTML( $html );
	}

	/**
	 * Show permission management section
	 */
	private function showPermissionManagement() {
		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'admindashboard-permissions' ) );

		$permManager = new PermissionManager();
		$groups = $permManager->getAllGroups();

		$html = '<div class="admin-section">';
		$html .= '<h1>' . $this->msg( 'admindashboard-permissions' )->text() . '</h1>';
		$html .= $this->makeNavigation();
		$html .= '<div class="permissions-table">';
		$html .= '<table class="wikitable">';
		$html .= '<tr><th>Group</th><th>Permissions</th><th>Members</th><th>Actions</th></tr>';

		foreach ( $groups as $group ) {
			$html .= '<tr>';
			$html .= '<td>' . htmlspecialchars( $group['name'] ) . '</td>';
			$html .= '<td>' . implode( ', ', $group['permissions'] ) . '</td>';
			$html .= '<td>' . intval( $group['memberCount'] ) . '</td>';
			$html .= '<td><button class="edit-group" data-group="' . htmlspecialchars( $group['name'] ) . '">Edit</button></td>';
			$html .= '</tr>';
		}

		$html .= '</table>';
		$html .= '</div>';
		$html .= '</div>';

		$out->addHTML( $html );
	}

	/**
	 * Show statistics section
	 */
	private function showStatistics() {
		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'admindashboard-statistics' ) );

		$statsManager = new StatisticsManager();
		$stats = $statsManager->getDetailedStats();

		$html = '<div class="admin-section">';
		$html .= '<h1>' . $this->msg( 'admindashboard-statistics' )->text() . '</h1>';
		$html .= $this->makeNavigation();
		$html .= '<div class="statistics-grid">';

		foreach ( $stats as $key => $value ) {
			$html .= '<div class="stat-box">';
			$html .= '<h3>' . htmlspecialchars( $key ) . '</h3>';
			$html .= '<p class="stat-value">' . htmlspecialchars( (string)$value ) . '</p>';
			$html .= '</div>';
		}

		$html .= '</div>';
		$html .= '</div>';

		$out->addHTML( $html );
	}

	/**
	 * Show logs section
	 */
	private function showLogs() {
		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'admindashboard-logs' ) );

		$html = '<div class="admin-section">';
		$html .= '<h1>' . $this->msg( 'admindashboard-logs' )->text() . '</h1>';
		$html .= $this->makeNavigation();
		$html .= '<div class="logs-viewer">';
		$html .= '<p>Recent activity logs will be displayed here.</p>';
		$html .= '</div>';
		$html .= '</div>';

		$out->addHTML( $html );
	}

	/**
	 * Show moderation section
	 */
	private function showModeration() {
		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'admindashboard-moderation' ) );

		$html = '<div class="admin-section">';
		$html .= '<h1>' . $this->msg( 'admindashboard-moderation' )->text() . '</h1>';
		$html .= $this->makeNavigation();
		$html .= '<div class="moderation-panel">';
		$html .= '<p>Content moderation tools will be displayed here.</p>';
		$html .= '</div>';
		$html .= '</div>';

		$out->addHTML( $html );
	}

	/**
	 * Show configuration section
	 */
	private function showConfiguration() {
		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'admindashboard-configuration' ) );

		$html = '<div class="admin-section">';
		$html .= '<h1>' . $this->msg( 'admindashboard-configuration' )->text() . '</h1>';
		$html .= $this->makeNavigation();
		$html .= '<div class="configuration-panel">';
		$html .= '<p>Site configuration settings will be displayed here.</p>';
		$html .= '</div>';
		$html .= '</div>';

		$out->addHTML( $html );
	}

	/**
	 * Show extensions section
	 */
	private function showExtensions() {
		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'admindashboard-extensions' ) );

		$html = '<div class="admin-section">';
		$html .= '<h1>' . $this->msg( 'admindashboard-extensions' )->text() . '</h1>';
		$html .= $this->makeNavigation();
		$html .= '<div class="extensions-panel">';
		$html .= '<p>Extension management tools will be displayed here.</p>';
		$html .= '</div>';
		$html .= '</div>';

		$out->addHTML( $html );
	}

	/**
	 * Make a dashboard card
	 *
	 * @param string $title
	 * @param mixed $value
	 * @param string $link
	 * @param string $icon
	 * @return string HTML
	 */
	private function makeDashboardCard( $title, $value, $link, $icon ) {
		return '<div class="dashboard-card">' .
			'<div class="card-icon">' . $icon . '</div>' .
			'<h3>' . htmlspecialchars( $title ) . '</h3>' .
			'<p class="card-value">' . htmlspecialchars( (string)$value ) . '</p>' .
			'<a href="' . $this->getTitleUrl( $link ) . '" class="card-link">View Details →</a>' .
			'</div>';
	}

	/**
	 * Make navigation menu
	 *
	 * @return string HTML
	 */
	private function makeNavigation() {
		$title = $this->getPageTitle();
		$nav = '<nav class="admin-nav">';
		$nav .= '<ul>';
		$nav .= '<li><a href="' . $title->getLocalURL( 'action=overview' ) . '">Overview</a></li>';
		$nav .= '<li><a href="' . $title->getLocalURL( 'action=users' ) . '">Users</a></li>';
		$nav .= '<li><a href="' . $title->getLocalURL( 'action=pages' ) . '">Pages</a></li>';
		$nav .= '<li><a href="' . $title->getLocalURL( 'action=permissions' ) . '">Permissions</a></li>';
		$nav .= '<li><a href="' . $title->getLocalURL( 'action=statistics' ) . '">Statistics</a></li>';
		$nav .= '<li><a href="' . $title->getLocalURL( 'action=logs' ) . '">Logs</a></li>';
		$nav .= '<li><a href="' . $title->getLocalURL( 'action=moderation' ) . '">Moderation</a></li>';
		$nav .= '<li><a href="' . $title->getLocalURL( 'action=configuration' ) . '">Configuration</a></li>';
		$nav .= '<li><a href="' . $title->getLocalURL( 'action=extensions' ) . '">Extensions</a></li>';
		$nav .= '</ul>';
		$nav .= '</nav>';
		return $nav;
	}

	/**
	 * Get title URL for navigation
	 *
	 * @param string $section
	 * @return string
	 */
	private function getTitleUrl( $section ) {
		$title = \Title::makeTitle( NS_SPECIAL, 'AdminDashboard/' . $section );
		return $title->getLocalURL();
	}
}
