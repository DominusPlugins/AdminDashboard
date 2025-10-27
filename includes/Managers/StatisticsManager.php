<?php

namespace MediaWiki\Extension\AdminDashboard\Managers;

use MediaWiki\MediaWikiServices;

/**
 * Manager for statistics-related operations
 */
class StatisticsManager {

	/**
	 * Get basic statistics
	 *
	 * @return array
	 */
	public function getBasicStats() {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		// Total users
		$totalUsers = $dbr->selectField(
			'user',
			'COUNT(*)',
			[],
			__METHOD__
		);

		// Total pages
		$totalPages = $dbr->selectField(
			'page',
			'COUNT(*)',
			[ 'page_namespace' => 0 ],
			__METHOD__
		);

		// Total edits
		$totalEdits = $dbr->selectField(
			'revision',
			'COUNT(*)',
			[],
			__METHOD__
		);

		// Active users (last 30 days)
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$timestamp = $dbr->timestamp( time() - 30 * 24 * 3600 );
		$activeUsers = $dbr->selectField(
			'user',
			'COUNT(*)',
			[ 'user_touched > ' . $dbr->addQuotes( $timestamp ) ],
			__METHOD__,
			[],
			[]
		);

		return [
			'users' => (int)$totalUsers,
			'pages' => (int)$totalPages,
			'edits' => (int)$totalEdits,
			'activeUsers' => (int)$activeUsers,
		];
	}

	/**
	 * Get detailed statistics
	 *
	 * @return array
	 */
	public function getDetailedStats() {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		$stats = $this->getBasicStats();

		// File uploads
		$fileUploads = $dbr->selectField(
			'image',
			'COUNT(*)',
			[],
			__METHOD__
		);

		// Categories
		$categories = $dbr->selectField(
			'category',
			'COUNT(*)',
			[],
			__METHOD__
		);

		// Talk pages
		$talkPages = $dbr->selectField(
			'page',
			'COUNT(*)',
			[ 'page_namespace' => 1 ],
			__METHOD__
		);

		// Admin users
		$adminUsers = $dbr->selectField(
			'user_groups',
			'COUNT(DISTINCT ug_user)',
			[ 'ug_group' => 'sysop' ],
			__METHOD__
		);

		return array_merge( $stats, [
			'fileUploads' => (int)$fileUploads,
			'categories' => (int)$categories,
			'talkPages' => (int)$talkPages,
			'adminUsers' => (int)$adminUsers,
		] );
	}

	/**
	 * Get user activity statistics
	 *
	 * @return array
	 */
	public function getUserActivityStats() {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		$result = $dbr->select(
			'revision',
			[ 'rev_user_text', 'COUNT(*) as edit_count' ],
			[],
			__METHOD__,
			[ 'GROUP BY' => 'rev_user_text', 'ORDER BY' => 'edit_count DESC', 'LIMIT' => 10 ]
		);

		$activity = [];
		foreach ( $result as $row ) {
			$activity[] = [
				'user' => $row->rev_user_text,
				'edits' => (int)$row->edit_count,
			];
		}

		return $activity;
	}

	/**
	 * Get page statistics by namespace
	 *
	 * @return array
	 */
	public function getPagesByNamespace() {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		$result = $dbr->select(
			'page',
			[ 'page_namespace', 'COUNT(*) as page_count' ],
			[],
			__METHOD__,
			[ 'GROUP BY' => 'page_namespace' ]
		);

		$namespaces = [];
		foreach ( $result as $row ) {
			$nsName = $this->getNamespaceName( $row->page_namespace );
			$namespaces[] = [
				'namespace' => $nsName,
				'count' => (int)$row->page_count,
			];
		}

		return $namespaces;
	}

	/**
	 * Get namespace name
	 *
	 * @param int $nsId
	 * @return string
	 */
	private function getNamespaceName( $nsId ) {
		$contentLanguage = MediaWikiServices::getInstance()->getContentLanguage();
		return $contentLanguage->getFormattedNsText( $nsId ) ?: 'Main';
	}
}
