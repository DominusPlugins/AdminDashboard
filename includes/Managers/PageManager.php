<?php

namespace MediaWiki\Extension\AdminDashboard\Managers;

use MediaWiki\MediaWikiServices;
use Title;

/**
 * Manager for page-related operations
 */
class PageManager {

	/**
	 * Get recent pages
	 *
	 * @param int $limit
	 * @return array
	 */
	public function getRecentPages( $limit = 100 ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$result = $dbr->select(
			[ 'page', 'revision' ],
			[ 'page_title', 'page_namespace', 'rev_timestamp', 'rev_user_text', 'page_id' ],
			[],
			__METHOD__,
			[ 'ORDER BY' => 'rev_timestamp DESC', 'LIMIT' => $limit ],
			[
				'revision' => [ 'INNER JOIN', 'page_id = rev_page' ]
			]
		);

		$pages = [];
		foreach ( $result as $row ) {
			$title = \Title::makeTitleSafe( $row->page_namespace, $row->page_title );
			if ( !$title ) {
				continue;
			}
			$pages[] = [
				'title' => $title->getPrefixedText(),
				'url' => $title->getLocalURL(),
				'lastModified' => substr( $row->rev_timestamp, 0, 10 ),
				'lastUser' => $row->rev_user_text ?? 'Unknown',
				'revisions' => $this->getRevisionCount( $row->page_id ),
			];
		}

		return $pages;
	}

	/**
	 * Get revision count for a page
	 *
	 * @param int $pageId
	 * @return int
	 */
	private function getRevisionCount( $pageId ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$result = $dbr->selectField(
			'revision',
			'COUNT(*)',
			[ 'rev_page' => $pageId ],
			__METHOD__
		);

		return (int)$result;
	}

	/**
	 * Delete a page
	 *
	 * @param string $title
	 * @return bool
	 */
	public function deletePage( $title ) {
		$titleObj = Title::newFromText( $title );
		if ( !$titleObj || !$titleObj->exists() ) {
			return false;
		}

		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $titleObj );
		$page->doDeleteArticleReal( 'Admin Dashboard deletion', null, null, null );

		return true;
	}

	/**
	 * Get page statistics
	 *
	 * @return array
	 */
	public function getPageStatistics() {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		$totalPages = $dbr->selectField(
			'page',
			'COUNT(*)',
			[ 'page_namespace' => 0 ],
			__METHOD__
		);

		$redirects = $dbr->selectField(
			'page',
			'COUNT(*)',
			[ 'page_is_redirect' => 1 ],
			__METHOD__
		);

		return [
			'totalPages' => (int)$totalPages,
			'redirects' => (int)$redirects,
		];
	}
}
