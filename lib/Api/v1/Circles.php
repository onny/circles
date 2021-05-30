<?php
/**
 * Circles - Bring cloud-users closer together.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @author Vinicius Cubas Brand <vinicius@eita.org.br>
 * @author Daniel Tygel <dtygel@eita.org.br>
 *
 * @copyright 2017
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Circles\Api\v1;


use daita\MySmallPhpTools\Model\SimpleDataStore;
use OCA\Circles\Exceptions\CircleNotFoundException;
use OCA\Circles\Exceptions\FederatedUserException;
use OCA\Circles\Exceptions\FederatedUserNotFoundException;
use OCA\Circles\Exceptions\InitiatorNotFoundException;
use OCA\Circles\Exceptions\InvalidIdException;
use OCA\Circles\Exceptions\RequestBuilderException;
use OCA\Circles\Exceptions\SingleCircleNotFoundException;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\DeprecatedCircle;
use OCA\Circles\Model\DeprecatedMember;
use OCA\Circles\Model\Member;
use OCA\Circles\Service\CircleService;
use OCA\Circles\Service\FederatedUserService;

class Circles {

	const API_VERSION = [0, 10, 0];

	// Expose circle and member constants via API
	const CIRCLES_PERSONAL = DeprecatedCircle::CIRCLES_PERSONAL;
	const CIRCLES_SECRET = DeprecatedCircle::CIRCLES_SECRET;
	const CIRCLES_CLOSED = DeprecatedCircle::CIRCLES_CLOSED;
	const CIRCLES_PUBLIC = DeprecatedCircle::CIRCLES_PUBLIC;
	const CIRCLES_ALL = DeprecatedCircle::CIRCLES_ALL;

	const TYPE_USER = DeprecatedMember::TYPE_USER;
	const TYPE_GROUP = DeprecatedMember::TYPE_GROUP;
	const TYPE_MAIL = DeprecatedMember::TYPE_MAIL;
	const TYPE_CONTACT = DeprecatedMember::TYPE_CONTACT;

	const LEVEL_NONE = DeprecatedMember::LEVEL_NONE;
	const LEVEL_MEMBER = DeprecatedMember::LEVEL_MEMBER;
	const LEVEL_MODERATOR = DeprecatedMember::LEVEL_MODERATOR;
	const LEVEL_ADMIN = DeprecatedMember::LEVEL_ADMIN;
	const LEVEL_OWNER = DeprecatedMember::LEVEL_OWNER;


	/**
	 * Circles::listCircles();
	 *
	 * This function list all circles fitting a search regarding its name and the level and the
	 * rights from the current user. In case of Secret circle, name needs to be complete so the
	 * circle is included in the list (or if the current user is the owner)
	 *
	 * example: Circles::listCircles(Circles::CIRCLES_ALL, '', 8, callback); will returns all
	 * circles when the current user is at least an Admin.
	 *
	 * @param mixed $type
	 * @param string $name
	 * @param int $level
	 * @param string $userId
	 * @param bool $forceAll
	 *
	 * @return Circle[]
	 */
	public static function listCircles($type, $name = '', $level = 0, $userId = '', $forceAll = false) {
		/** @var FederatedUserService $federatedUserService */
		$federatedUserService = \OC::$server->get(FederatedUserService::class);

		$personalCircle = false;
		if ($forceAll) {
			$personalCircle = true;
		}


		if ($userId === '') {
			$federatedUserService->initCurrentUser();
		} else {
			$federatedUserService->setLocalCurrentUserId($userId);
		}

		/** @var CircleService $circleService */
		$circleService = \OC::$server->get(CircleService::class);

		return $circleService->getCircles(
			null,
			null,
			new SimpleDataStore(['includePersonalCircles' => $personalCircle])
		);
	}


	/**
	 * @param string $userId
	 * @param bool $forceAll
	 *
	 * @return Circle[]
	 * @throws FederatedUserException
	 * @throws FederatedUserNotFoundException
	 * @throws InitiatorNotFoundException
	 * @throws InvalidIdException
	 * @throws RequestBuilderException
	 * @throws SingleCircleNotFoundException
	 *
	 * @deprecated - used by apps/dav/lib/Connector/Sabre/Principal.php
	 *
	 * Circles::joinedCircles();
	 *
	 * Return all the circle the current user is a member.
	 */
	public static function joinedCircles($userId = '', $forceAll = false) {
		/** @var FederatedUserService $federatedUserService */
		$federatedUserService = \OC::$server->get(FederatedUserService::class);

		$personalCircle = false;
		if ($forceAll) {
			$personalCircle = true;
		}

		if ($userId === '') {
			$federatedUserService->initCurrentUser();
		} else {
			$federatedUserService->setLocalCurrentUserId($userId);
		}

		/** @var CircleService $circleService */
		$circleService = \OC::$server->get(CircleService::class);

		return $circleService->getCircles(
			null,
			null,
			new SimpleDataStore(
				[
					'mustBeMember'           => true,
					'includePersonalCircles' => $personalCircle
				]
			)
		);
	}


	/**
	 * @param string $circleUniqueId
	 * @param bool $forceAll
	 *
	 * @return Circle
	 * @throws CircleNotFoundException
	 * @throws FederatedUserException
	 * @throws FederatedUserNotFoundException
	 * @throws InitiatorNotFoundException
	 * @throws InvalidIdException
	 * @throws RequestBuilderException
	 * @throws SingleCircleNotFoundException
	 *
	 * @deprecated - used by apps/dav/lib/Connector/Sabre/Principal.php
	 *             - used by apps/files_sharing/lib/Controller/ShareAPIController.php
	 *             - used by lib/private/Share20/Manager.php
	 *
	 * Circles::detailsCircle();
	 *
	 * WARNING - This function is called by the core - WARNING
	 *                 Do not change it
	 *
	 * Returns details on the circle. If the current user is a member, the members list will be
	 * return as well.
	 *
	 */
	public static function detailsCircle(string $circleUniqueId, bool $forceAll = false): Circle {
		/** @var FederatedUserService $federatedUserService */
		$federatedUserService = \OC::$server->get(FederatedUserService::class);
		if ($forceAll) {
			$federatedUserService->bypassCurrentUserCondition($forceAll);
		} else {
			$federatedUserService->initCurrentUser();
		}

		/** @var CircleService $circleService */
		$circleService = \OC::$server->get(CircleService::class);

		return $circleService->getCircle($circleUniqueId);
	}


	/**
	 * @param string $circleUniqueId
	 * @param string $ident
	 * @param int $type
	 * @param bool $forceAll
	 *
	 * @return Member
	 *
	 * @deprecated - used by apps/files_sharing/lib/Controller/ShareAPIController.php
	 *
	 * Circles::getMember();
	 *
	 * This function will return information on a member of the circle. Current user need at least
	 * to be Member.
	 *
	 */
	public static function getMember($circleUniqueId, $ident, $type, $forceAll = false) {
//		$c = self::getContainer();
//
//		return $c->query(MembersService::class)
//				 ->getMember($circleUniqueId, $ident, $type, $forceAll);
	}


	/**
	 * @param array $circleUniqueIds
	 *
	 * @return string[] array of object ids or empty array if none found
	 *
	 * @deprecated - used by apps/dav/lib/Connector/Sabre/FilesReportPlugin.php
	 *
	 * Get a list of objects which are shred with $circleUniqueId.
	 *
	 * @since 0.14.0
	 *
	 */
	public static function getFilesForCircles($circleUniqueIds) {
//		$c = self::getContainer();
//
//		return $c->query(CirclesService::class)
//				 ->getFilesForCircles($circleUniqueIds);
	}

}

