<?php

namespace MediaWiki\Extension\AdminDashboard\Managers;

use MediaWiki\MediaWikiServices;
use User;

/**
 * Manager for permission-related operations
 */
class PermissionManager {

	/**
	 * Get all user groups
	 *
	 * @return array
	 */
	public function getAllGroups() {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		// Get all groups
		$result = $dbr->select(
			'user_groups',
			[ 'ug_group' ],
			[],
			__METHOD__,
			[ 'DISTINCT' ]
		);

		$groups = [];
		foreach ( $result as $row ) {
			$groupName = $row->ug_group;
			$permissions = $this->getGroupPermissions( $groupName );
			$memberCount = $this->getGroupMemberCount( $groupName );

			$groups[] = [
				'name' => $groupName,
				'permissions' => $permissions,
				'memberCount' => $memberCount,
			];
		}

		return $groups;
	}

	/**
	 * Get permissions for a group
	 *
	 * @param string $group
	 * @return array
	 */
	private function getGroupPermissions( $group ) {
		// Read from MediaWiki configuration (GroupPermissions)
		try {
			$config = MediaWikiServices::getInstance()->getMainConfig();
			$groupPermissions = (array)$config->get( 'GroupPermissions' );
			if ( isset( $groupPermissions[$group] ) && is_array( $groupPermissions[$group] ) ) {
				$rights = [];
				foreach ( $groupPermissions[$group] as $right => $allowed ) {
					if ( $allowed ) {
						$rights[] = $right;
					}
				}
				return $rights;
			}
		} catch ( \Throwable $e ) {
			// Fall through to empty list if config not available
		}
		return [];
	}

	/**
	 * Get member count for a group
	 *
	 * @param string $group
	 * @return int
	 */
	private function getGroupMemberCount( $group ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$result = $dbr->selectField(
			'user_groups',
			'COUNT(*)',
			[ 'ug_group' => $group ],
			__METHOD__
		);

		return (int)$result;
	}

	/**
	 * Add user to group
	 *
	 * @param string $userName
	 * @param string $group
	 * @return bool
	 */
	public function addUserToGroup( $userName, $group ) {
		$user = \User::newFromName( $userName );
		if ( !$user || !$user->isRegistered() ) {
			return false;
		}

		$user->addGroup( $group );
		return true;
	}

	/**
	 * Remove user from group
	 *
	 * @param string $userName
	 * @param string $group
	 * @return bool
	 */
	public function removeUserFromGroup( $userName, $group ) {
		$user = \User::newFromName( $userName );
		if ( !$user || !$user->isRegistered() ) {
			return false;
		}

		$user->removeGroup( $group );
		return true;
	}

	/**
	 * Get permissions for a user
	 *
	 * @param string $userName
	 * @return array
	 */
	public function getUserPermissions( $userName ) {
		$user = \User::newFromName( $userName );
		if ( !$user || !$user->isRegistered() ) {
			return [];
		}

		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		return $permissionManager->getUserPermissions( $user );
	}
}
