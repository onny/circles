<?php
/**
 * Circles - Bring cloud-users closer together.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@pontapreta.net>
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


use OC;
use OCA\Circles\AppInfo\Application;
use OCA\Circles\Exceptions\ApiVersionIncompatibleException;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\FederatedLink;
use OCA\Circles\Model\Member;
use OCA\Circles\Model\SharingFrame;
use OCA\Circles\Service\CirclesService;
use OCA\Circles\Service\FederatedLinkService;
use OCA\Circles\Service\MembersService;
use OCA\Circles\Service\MiscService;
use OCA\Circles\Service\SharingFrameService;
use OCP\AppFramework\QueryException;
use OCP\Util;

class Circles {

	const API_VERSION = [0, 10, 0];

	// Expose circle and member constants via API
	const CIRCLES_PERSONAL = Circle::CIRCLES_PERSONAL;
	const CIRCLES_SECRET = Circle::CIRCLES_SECRET;
	const CIRCLES_CLOSED = Circle::CIRCLES_CLOSED;
	const CIRCLES_PUBLIC = Circle::CIRCLES_PUBLIC;
	const CIRCLES_ALL = Circle::CIRCLES_ALL;

	const TYPE_USER = Member::TYPE_USER;
	const TYPE_GROUP = Member::TYPE_GROUP;
	const TYPE_MAIL = Member::TYPE_MAIL;
	const TYPE_CONTACT = Member::TYPE_CONTACT;

	const LEVEL_NONE = Member::LEVEL_NONE;
	const LEVEL_MEMBER = Member::LEVEL_MEMBER;
	const LEVEL_MODERATOR = Member::LEVEL_MODERATOR;
	const LEVEL_ADMIN = Member::LEVEL_ADMIN;
	const LEVEL_OWNER = Member::LEVEL_OWNER;


	protected static function getContainer() {
		$app = OC::$server->query(Application::class);

		return $app->getContainer();
	}


	/**
	 * Circles::version();
	 *
	 * returns the current version of the API
	 *
	 * @return int[]
	 */
	public static function version() {
		return self::API_VERSION;
	}


	public static function addJavascriptAPI() {
		Util::addScript(Application::APP_NAME, 'circles.v1.circles');
		Util::addScript(Application::APP_NAME, 'circles.v1.members');
		Util::addScript(Application::APP_NAME, 'circles.v1.links');
		Util::addScript(Application::APP_NAME, 'circles.v1');
	}


	/**
	 * Circles::compareVersion();
	 *
	 * Compare and return true if version is compatible.
	 * Exception otherwise.
	 *
	 * @param array $apiVersion
	 *
	 * @return bool
	 * @throws ApiVersionIncompatibleException
	 */
	public static function compareVersion($apiVersion) {
		if ((int)$apiVersion[0] !== self::API_VERSION[0]
			|| (int)$apiVersion[1] !== self::API_VERSION[1]) {
			throw new ApiVersionIncompatibleException('api_not_compatible');
		}

		return true;
	}


	/**
	 * Circles::createCircle();
	 *
	 * Create a new circle and make the current user its owner.
	 * You must specify type and name. type is one of this value:
	 *
	 * CIRCLES_PERSONAL is 1 or 'personal'
	 * CIRCLES_SECRET is 2 or 'secret'
	 * CIRCLES_CLOSED is 4 or 'closed'
	 * CIRCLES_PUBLIC is 8 or 'public'
	 *
	 * @param mixed $type
	 * @param string $name
	 *
	 * @return Circle
	 */
	public static function createCircle($type, $name) {
		$c = self::getContainer();

		return $c->query(CirclesService::class)
				 ->createCircle($type, $name);
	}


	/**
	 * Circles::joinCircle();
	 *
	 * This function will make the current user joining a circle identified by its Id.
	 *
	 * @param string $circleUniqueId
	 *
	 * @return Member
	 */
	public static function joinCircle($circleUniqueId) {
		$c = self::getContainer();

		return $c->query(CirclesService::class)
				 ->joinCircle($circleUniqueId);
	}


	/**
	 * Circles::leaveCircle();
	 *
	 * This function will make the current user leaving the circle identified by its Id. Will fail
	 * if user is the owner of the circle.
	 *
	 * @param string $circleUniqueId
	 *
	 * @return Member
	 */
	public static function leaveCircle($circleUniqueId) {
		$c = self::getContainer();

		return $c->query(CirclesService::class)
				 ->leaveCircle($circleUniqueId);
	}


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
	 *
	 * @param bool $forceAll
	 *
	 * @return Circle[]
	 */
	public static function listCircles($type, $name = '', $level = 0, $userId = '', $forceAll = false) {
		$c = self::getContainer();

		if ($userId === '') {
			$userId = OC::$server->getUserSession()
								  ->getUser()
								  ->getUID();
		}

		return $c->query(CirclesService::class)
				 ->listCircles($userId, $type, $name, $level, $forceAll);
	}


	/**
	 * Circles::joinedCircles();
	 *
	 * Return all the circle the current user is a member.
	 *
	 * @param string $userId
	 * @param bool $forceAll
	 *
	 * @return Circle[]
	 * @throws QueryException
	 */
	public static function joinedCircles($userId = '', $forceAll = false) {
		return self::listCircles(self::CIRCLES_ALL, '', self::LEVEL_MEMBER, $userId, $forceAll);
	}


	/**
	 * Circles::joinedCircleIds();
	 *
	 * Return all the circleIds the user is a member, if empty user, using current user.
	 *
	 * @param $userId
	 *
	 * @return array
	 * @throws QueryException
	 */
	public static function joinedCircleIds($userId = '') {
		$circleIds = [];
		$circles = self::listCircles(self::CIRCLES_ALL, '', self::LEVEL_MEMBER, $userId);
		foreach ($circles as $circle) {
			$circleIds[] = $circle->getUniqueId();
		}

		return $circleIds;
	}


	/**
	 * Circles::detailsCircle();
	 *
	 * WARNING - This function is called by the core - WARNING
	 *                 Do not change it
	 *
	 * Returns details on the circle. If the current user is a member, the members list will be
	 * return as well.
	 *
	 * @param string $circleUniqueId
	 * @param bool $forceAll
	 *
	 * @return Circle
	 */
	public static function detailsCircle($circleUniqueId, $forceAll = false) {
		$c = self::getContainer();

		return $c->query(CirclesService::class)
				 ->detailsCircle($circleUniqueId, $forceAll);
	}


	/**
	 * Circles::settingsCircle();
	 *
	 * Save the settings. Settings is an array and current user need to be an admin
	 *
	 * @param string $circleUniqueId
	 * @param array $settings
	 *
	 * @return Circle
	 */
	public static function settingsCircle($circleUniqueId, array $settings) {
		$c = self::getContainer();

		return $c->query(CirclesService::class)
				 ->settingsCircle($circleUniqueId, $settings);
	}


	/**
	 * Circles::destroyCircle();
	 *
	 * This function will destroy the circle if the current user is the Owner.
	 *
	 * @param string $circleUniqueId
	 *
	 * @return mixed
	 */
	public static function destroyCircle($circleUniqueId) {
		$c = self::getContainer();

		return $c->query(CirclesService::class)
				 ->removeCircle($circleUniqueId);
	}


	/**
	 * Circles::addMember();
	 *
	 * This function will add a user as member of the circle. Current user need at least to be
	 * Moderator.
	 *
	 * @param string $circleUniqueId
	 * @param string $ident
	 * @param int $type
	 *
	 * @return Member[]
	 */
	public static function addMember($circleUniqueId, $ident, $type) {
		$c = self::getContainer();

		return $c->query(MembersService::class)
				 ->addMember($circleUniqueId, $ident, $type);
	}


	/**
	 * Circles::getMember();
	 *
	 * This function will return information on a member of the circle. Current user need at least
	 * to be Member.
	 *
	 * @param string $circleUniqueId
	 * @param string $ident
	 * @param int $type
	 * @param bool $forceAll
	 *
	 * @return Member
	 */
	public static function getMember($circleUniqueId, $ident, $type, $forceAll = false) {
		$c = self::getContainer();

		return $c->query(MembersService::class)
				 ->getMember($circleUniqueId, $ident, $type, $forceAll);
	}


	/**
	 * Circles::removeMember();
	 *
	 * This function will remove a member from the circle. Current user needs to be at least
	 * Moderator and have a higher level that the targeted member.
	 *
	 * @param string $circleUniqueId
	 * @param string $ident
	 * @param int $type
	 *
	 * @return Member[]
	 */
	public static function removeMember($circleUniqueId, $ident, $type) {
		$c = self::getContainer();

		return $c->query(MembersService::class)
				 ->removeMember($circleUniqueId, $ident, $type);
	}


	/**
	 * Circles::levelMember();
	 *
	 * Edit the level of a member of the circle. The current level of the target needs to be lower
	 * than the user that initiate the process (ie. the current user). The new level of the target
	 * cannot be the same than the current level of the user that initiate the process (ie. the
	 * current user).
	 *
	 * @param string $circleUniqueId
	 * @param string $ident
	 * @param int $type
	 * @param int $level
	 *
	 * @return Member[]
	 */
	public static function levelMember($circleUniqueId, $ident, $type, $level) {
		$c = self::getContainer();

		return $c->query(MembersService::class)
				 ->levelMember($circleUniqueId, $ident, $type, $level);
	}


	/**
	 * Circles::shareToCircle();
	 *
	 * This function will share an item (array) to the circle identified by its Id.
	 * Source is the app that is sharing the item and type can be used by the app to identified the
	 * payload.
	 *
	 * @param string $circleUniqueId
	 * @param string $source
	 * @param string $type
	 * @param array $payload
	 * @param string $broadcaster
	 *
	 * @return mixed
	 */
	public static function shareToCircle(
		$circleUniqueId, $source, $type, array $payload, $broadcaster
	) {
		$c = self::getContainer();

		$frame = new SharingFrame((string)$source, (string)$type);
		$frame->setPayload($payload);

		return $c->query(SharingFrameService::class)
				 ->createFrame($circleUniqueId, $frame, (string)$broadcaster);
	}


	/**
	 * Circles::getSharesFromCircle();
	 *
	 * This function will returns all item (array) shared to a specific circle identified by its Id,
	 * source and type. Limited to current user session.
	 *
	 * @param string $circleUniqueId
	 *
	 * @return mixed
	 */
	public static function getSharesFromCircle($circleUniqueId) {
		$c = self::getContainer();

		return $c->query(SharingFrameService::class)
				 ->getFrameFromCircle($circleUniqueId);
	}


	/**
	 * Circles::linkCircle();
	 *
	 * Initiate a link procedure. Current user must be at least Admin of the circle.
	 * circleId is the local circle and remote is the target for the link.
	 * Remote format is: <circle_name>@<remote_host> when remote_host must be a valid HTTPS address.
	 * Remote format is: <circle_name>@<remote_host> when remote_host must be a valid HTTPS address.
	 *
	 * @param string $circleUniqueId
	 * @param string $remote
	 *
	 * @return FederatedLink
	 */
	public static function linkCircle($circleUniqueId, $remote) {
		$c = self::getContainer();

		return $c->query(FederatedLinkService::class)
				 ->linkCircle($circleUniqueId, $remote);
	}


	/**
	 * Circles::generateLink();
	 *
	 * Returns the link to get access to a local circle.
	 *
	 * @param string $circleUniqueId
	 *
	 * @return string
	 */
	public static function generateLink($circleUniqueId) {
		return OC::$server->getURLGenerator()
						   ->linkToRoute('circles.Navigation.navigate') . '#' . $circleUniqueId;
	}


	/**
	 * Circles::generateAbsoluteLink();
	 *
	 * Returns the absolute link to get access to a local circle.
	 *
	 * @param string $circleUniqueId
	 *
	 * @return string
	 */
	public static function generateAbsoluteLink($circleUniqueId) {
		return OC::$server->getURLGenerator()
						   ->linkToRouteAbsolute('circles.Navigation.navigate') . '#' . $circleUniqueId;
	}


	/**
	 * Circles::generateRemoteLink();
	 *
	 * Returns the link to get access to a remote circle.
	 *
	 * @param FederatedLink $link
	 *
	 * @return string
	 */
	public static function generateRemoteLink(FederatedLink $link) {
		return OC::$server->getURLGenerator()
						   ->linkToRoute('circles.Navigation.navigate') . '#' . $link->getUniqueId()
			   . '-' . $link->getToken();
	}


	/**
	 * @param SharingFrame $frame
	 *
	 * @return array
	 */
	public static function generateUserParameter(SharingFrame $frame) {

		if ($frame->getCloudId() !== null) {
			$name = $frame->getAuthor() . '@' . $frame->getCloudId();
		} else {
			// Obtenir CachedName !!
			$name = MiscService::getDisplay($frame->getAuthor(), self::TYPE_USER);
		}

		return [
			'type' => 'user',
			'id'   => $frame->getAuthor(),
			'name' => $name
		];
	}


	/**
	 * @param SharingFrame $frame
	 *
	 * @return array
	 */
	public static function generateCircleParameter(SharingFrame $frame) {
		return [
			'type' => 'circle',
			'id'   => $frame->getCircle()
							->getUniqueId(),
			'name' => $frame->getCircle()
							->getName(),
			'link' => self::generateLink(
				$frame->getCircle()
					  ->getUniqueId()
			)
		];
	}

	/**
	 * Get a list of objects which are shred with $circleUniqueId.
	 *
	 * @since 0.14.0
	 *
	 * @param array $circleUniqueIds
	 *
	 * @return string[] array of object ids or empty array if none found
	 */
	public static function getFilesForCircles($circleUniqueIds) {
		$c = self::getContainer();

		return $c->query(CirclesService::class)
			->getFilesForCircles($circleUniqueIds);
	}
}
