<?php

declare(strict_types=1);


/**
 * Circles - Bring cloud-users closer together.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
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


namespace OCA\Circles\Cron;

use OCA\Circles\AppInfo\Application;
use OCA\Circles\Service\CirclesService;
use OCA\Circles\Service\GSUpstreamService;
use OCA\Circles\Service\MembersService;
use OCP\AppFramework\QueryException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

/**
 * Class GlobalSync
 *
 * @package OCA\Cicles\Cron
 */
class GlobalSync extends TimedJob {
	/**
	 * Cache constructor.
	 */
	public function __construct(ITimeFactory $timeFactory) {
		parent::__construct($timeFactory);
		$this->setInterval(10);
	}


	/**
	 * @param $argument
	 *
	 * @throws QueryException
	 */
	protected function run($argument) {
		return;
//		$app = \OC::$server->query(Application::class);
//		$c = $app->getContainer();
//
//		/** @var CirclesService $circlesService */
//		$circlesService = $c->query(CirclesService::class);
//		/** @var MembersService $membersService */
//		$membersService = $c->query(MembersService::class);
//		/** @var GSUpstreamService $gsUpstreamService */
//		$gsUpstreamService = $c->query(GSUpstreamService::class);
//
//		$circles = $circlesService->getCirclesToSync();
//
//		foreach ($circles as $circle) {
//			$membersService->updateCachedFromCircle($circle);
//		}
//
//		$gsUpstreamService->synchronize($circles);
	}
}
