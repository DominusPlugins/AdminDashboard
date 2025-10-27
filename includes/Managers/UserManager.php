<?php

namespace MediaWiki\Extension\AdminDashboard\Managers;

use User;
use MediaWiki\MediaWikiServices;

/**
 * Manager for user-related operations
 */
class UserManager {

	/**
	 * Get all users
	 *
	 * @return array
	 */
	public function getAllUsers() {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$result = $dbr->select(
			[ 'user', 'user_groups' ],
			[ 'user_name', 'user_registration', 'user_touched' ],
			[],
			__METHOD__,
			[ 'ORDER BY' => 'user_name' ],
			[ 'user_groups' => [ 'LEFT JOIN', 'user_id = ug_user' ] ]
		);

		$users = [];
		foreach ( $result as $row ) {
			$user = User::newFromName( $row->user_name );
			$groups = $user ? $this->getUserGroups( $user ) : [];
			$users[] = [
				'name' => $row->user_name,
				'created' => $this->formatTimestamp( $row->user_registration ),
				'lastActive' => $this->formatTimestamp( $row->user_touched ),
				'groups' => $groups,
			];
		}

		return $users;
	}

	/**
	 * Get user groups
	 *
	 * @param User $user
	 * @return array
	 */
	private function getUserGroups( User $user ) {
		return $user->getGroups();
	}

	/**
	 * Format timestamp for display
	 *
	 * @param string|null $timestamp
	 * @return string
	 */
	private function formatTimestamp( $timestamp ) {
		if ( !$timestamp ) {
			return 'Never';
		}
		return substr( $timestamp, 0, 10 );
	}

	/**
	 * Get user by name
	 *
	 * @param string $name
	 * @return User|null
	 */
	public function getUserByName( $name ) {
		$user = User::newFromName( $name );
		return $user && $user->isRegistered() ? $user : null;
	}

	/**
	 * Create a new user
	 *
	 * @param string $name
	 * @param string $password
	 * @param string $email
	 * @return bool
	 */
	public function createUser( $name, $password, $email = '' ) {
		$user = User::newFromName( $name );
		if ( !$user || $user->isRegistered() ) {
			return false;
		}

		$user->setEmail( $email );
		$user->setPassword( $password );
		$user->saveSettings();

		return $user->addToDatabase();
	}

	/**
	 * Delete a user
	 *
	 * @param string $name
	 * @return bool
	 */
	public function deleteUser( $name ) {
		$user = $this->getUserByName( $name );
		if ( !$user ) {
			return false;
		}

		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$dbw->delete(
			'user',
			[ 'user_id' => $user->getId() ],
			__METHOD__
		);

		return $dbw->affectedRows() > 0;
	}

	/**
	 * Update user information
	 *
	 * @param string $name
	 * @param array $updates
	 * @return bool
	 */
	public function updateUser( $name, array $updates ) {
		$user = $this->getUserByName( $name );
		if ( !$user ) {
			return false;
		}

		if ( isset( $updates['email'] ) ) {
			$user->setEmail( $updates['email'] );
		}

		if ( isset( $updates['realname'] ) ) {
			$user->setRealName( $updates['realname'] );
		}

		$user->saveSettings();
		return true;
	}
}
