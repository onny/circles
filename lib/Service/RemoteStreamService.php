<?php

declare(strict_types=1);


/**
 * Circles - Bring cloud-users closer together.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2021
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

namespace OCA\Circles\Service;


use daita\MySmallPhpTools\ActivityPub\Nextcloud\nc21\NC21Signature;
use daita\MySmallPhpTools\Exceptions\RequestNetworkException;
use daita\MySmallPhpTools\Exceptions\SignatoryException;
use daita\MySmallPhpTools\Exceptions\SignatureException;
use daita\MySmallPhpTools\Exceptions\WellKnownLinkNotFoundException;
use daita\MySmallPhpTools\Model\Nextcloud\nc21\NC21Request;
use daita\MySmallPhpTools\Model\Nextcloud\nc21\NC21RequestResult;
use daita\MySmallPhpTools\Model\Nextcloud\nc21\NC21Signatory;
use daita\MySmallPhpTools\Model\Nextcloud\nc21\NC21SignedRequest;
use daita\MySmallPhpTools\Model\Request;
use daita\MySmallPhpTools\Model\SimpleDataStore;
use daita\MySmallPhpTools\Traits\Nextcloud\nc21\TNC21Deserialize;
use daita\MySmallPhpTools\Traits\Nextcloud\nc21\TNC21LocalSignatory;
use daita\MySmallPhpTools\Traits\Nextcloud\nc21\TNC21WellKnown;
use daita\MySmallPhpTools\Traits\TStringTools;
use JsonSerializable;
use OCA\Circles\AppInfo\Application;
use OCA\Circles\Db\RemoteRequest;
use OCA\Circles\Exceptions\FederatedItemException;
use OCA\Circles\Exceptions\RemoteAlreadyExistsException;
use OCA\Circles\Exceptions\RemoteInstanceException;
use OCA\Circles\Exceptions\RemoteNotFoundException;
use OCA\Circles\Exceptions\RemoteResourceNotFoundException;
use OCA\Circles\Exceptions\RemoteUidException;
use OCA\Circles\Exceptions\UnknownRemoteException;
use OCA\Circles\Model\Federated\RemoteInstance;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IURLGenerator;
use ReflectionClass;
use ReflectionException;


/**
 * Class RemoteStreamService
 *
 * @package OCA\Circles\Service
 */
class RemoteStreamService extends NC21Signature {


	use TNC21Deserialize;
	use TNC21LocalSignatory;
	use TStringTools;
	use TNC21WellKnown;


	const UPDATE_DATA = 'data';
	const UPDATE_TYPE = 'type';
	const UPDATE_INSTANCE = 'instance';
	const UPDATE_HREF = 'href';


	/** @var IL10N */
	private $l10n;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var RemoteRequest */
	private $remoteRequest;

	/** @var ConfigService */
	private $configService;


	/**
	 * RemoteStreamService constructor.
	 *
	 * @param IL10N $l10n
	 * @param IURLGenerator $urlGenerator
	 * @param RemoteRequest $remoteRequest
	 * @param ConfigService $configService
	 */
	public function __construct(
		IL10N $l10n, IURLGenerator $urlGenerator, RemoteRequest $remoteRequest, ConfigService $configService
	) {
		$this->setup('app', 'circles');

		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
		$this->remoteRequest = $remoteRequest;
		$this->configService = $configService;
	}


	/**
	 * Returns the Signatory model for the Circles app.
	 * Can be signed with a confirmKey.
	 *
	 * @param bool $generate
	 * @param string $confirmKey
	 *
	 * @return RemoteInstance
	 * @throws SignatoryException
	 */
	public function getAppSignatory(bool $generate = true, string $confirmKey = ''): RemoteInstance {
		$app = new RemoteInstance($this->configService->getFrontalPath());
		$this->fillSimpleSignatory($app, $generate);
		$app->setUidFromKey();

		if ($confirmKey !== '') {
			$app->setAuthSigned($this->signString($confirmKey, $app));
		}

		$app->setEvent($this->configService->getFrontalPath('circles.Remote.event'));
		$app->setIncoming($this->configService->getFrontalPath('circles.Remote.incoming'));
		$app->setTest($this->configService->getFrontalPath('circles.Remote.test'));
		$app->setCircles($this->configService->getFrontalPath('circles.Remote.circles'));
		$app->setCircle(
			urldecode(
				$this->configService->getFrontalPath(
					'circles.Remote.circle',
					['circleId' => '{circleId}']
				)
			)
		);
		$app->setMembers(
			urldecode(
				$this->configService->getFrontalPath(
					'circles.Remote.members',
					['circleId' => '{circleId}']
				)
			)
		);
		$app->setMember(
			urldecode(
				$this->configService->getFrontalPath(
					'circles.Remote.member',
					['type' => '{type}', 'userId' => '{userId}']
				)
			)
		);

		$app->setOrigData($app->jsonSerialize());

		return $app;
	}


	/**
	 * Reset the Signatory (and the Identity) for the Circles app.
	 */
	public function resetAppSignatory(): void {
		try {
			$app = $this->getAppSignatory();

			$this->removeSimpleSignatory($app);
		} catch (SignatoryException $e) {
		}
	}


	/**
	 * shortcut to requestRemoteInstance that return result if available, or exception.
	 *
	 * @param string $instance
	 * @param string $item
	 * @param int $type
	 * @param JsonSerializable|null $object
	 * @param array $params
	 *
	 * @return array
	 * @throws RemoteInstanceException
	 * @throws RemoteNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws UnknownRemoteException
	 * @throws FederatedItemException
	 */
	public function resultRequestRemoteInstance(
		string $instance,
		string $item,
		int $type = Request::TYPE_GET,
		?JsonSerializable $object = null,
		array $params = []
	): array {
		// TODO: check what is happening if website is down...
		$signedRequest = $this->requestRemoteInstance($instance, $item, $type, $object, $params);
		if (!$signedRequest->getOutgoingRequest()->hasResult()) {
			throw new RemoteInstanceException();
		}

		$result = $signedRequest->getOutgoingRequest()->getResult();

		if ($result->getStatusCode() === Http::STATUS_OK) {
			return $result->getAsArray();
		}

		throw $this->getFederatedItemExceptionFromResult($result);
	}


	/**
	 * Send a request to a remote instance, based on:
	 * - instance: address as saved in database,
	 * - item: the item to request (incoming, event, ...)
	 * - type: GET, POST
	 * - data: Serializable to be send if needed
	 *
	 * @param string $instance
	 * @param string $item
	 * @param int $type
	 * @param JsonSerializable|null $object
	 * @param array $params
	 *
	 * @return NC21SignedRequest
	 * @throws RemoteNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws UnknownRemoteException
	 * @throws RemoteInstanceException
	 */
	private function requestRemoteInstance(
		string $instance,
		string $item,
		int $type = Request::TYPE_GET,
		?JsonSerializable $object = null,
		array $params = []
	): NC21SignedRequest {

		$request = new NC21Request('', $type);
		if ($this->configService->isLocalInstance($instance)) {
			$this->configService->configureRequest($request, 'circles.Remote.' . $item, $params);
		} else {
			$this->configService->configureRequest($request);
			$link = $this->getRemoteInstanceEntry($instance, $item, $params);
			$request->basedOnUrl($link);
		}

		// TODO: Work Around: on local, if object is empty, request takes 10s. check on other configuration
		if (is_null($object) || empty(json_decode(json_encode($object), true))) {
			$object = new SimpleDataStore(['empty' => 1]);
		}

		if (!is_null($object)) {
			$request->setDataSerialize($object);
		}

		try {
			$app = $this->getAppSignatory();
//		$app->setAlgorithm(NC21Signatory::SHA512);
			$signedRequest = $this->signOutgoingRequest($request, $app);
			$this->doRequest($signedRequest->getOutgoingRequest(), false);
		} catch (RequestNetworkException | SignatoryException $e) {
			throw new RemoteInstanceException($e->getMessage());
		}

		return $signedRequest;
	}


	/**
	 * get the value of an entry from the Signatory of the RemoteInstance.
	 *
	 * @param string $instance
	 * @param string $item
	 * @param array $params
	 *
	 * @return string
	 * @throws RemoteNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws UnknownRemoteException
	 */
	private function getRemoteInstanceEntry(string $instance, string $item, array $params = []): string {
		$remote = $this->getCachedRemoteInstance($instance);

		$value = $this->get($item, $remote->getOrigData());
		if ($value === '') {
			throw new RemoteResourceNotFoundException();
		}

		return $this->feedStringWithParams($value, $params);
	}


	/**
	 * get RemoteInstance with confirmed and known identity from database.
	 *
	 * @param string $instance
	 *
	 * @return RemoteInstance
	 * @throws RemoteNotFoundException
	 * @throws UnknownRemoteException
	 */
	public function getCachedRemoteInstance(string $instance): RemoteInstance {
		$remoteInstance = $this->remoteRequest->getFromInstance($instance);
		if ($remoteInstance->getType() === RemoteInstance::TYPE_UNKNOWN) {
			throw new UnknownRemoteException($instance . ' is set as \'unknown\' in database');
		}

		return $remoteInstance;
	}


	/**
	 * Add a remote instance, based on the address
	 *
	 * @param string $instance
	 *
	 * @return RemoteInstance
	 * @throws RequestNetworkException
	 * @throws SignatoryException
	 * @throws SignatureException
	 * @throws WellKnownLinkNotFoundException
	 */
	public function retrieveRemoteInstance(string $instance): RemoteInstance {
		$resource = $this->getResourceData($instance, Application::APP_SUBJECT, Application::APP_REL);

		$remoteInstance = $this->retrieveSignatory($resource->g('id'), true);
		$remoteInstance->setInstance($instance);

		return $remoteInstance;
	}


	/**
	 * retrieve Signatory.
	 *
	 * @param string $keyId
	 * @param bool $refresh
	 *
	 * @return RemoteInstance
	 * @throws SignatoryException
	 * @throws SignatureException
	 */
	public function retrieveSignatory(string $keyId, bool $refresh = true): RemoteInstance {
		if (!$refresh) {
			try {
				return $this->remoteRequest->getFromHref(NC21Signatory::removeFragment($keyId));
			} catch (RemoteNotFoundException $e) {
				throw new SignatoryException();
			}
		}

		$remoteInstance = new RemoteInstance($keyId);
		$confirm = $this->uuid();

		$request = new NC21Request();
		$this->configService->configureRequest($request);

		$this->downloadSignatory($remoteInstance, $keyId, ['auth' => $confirm], $request);
		$remoteInstance->setUidFromKey();

		$this->confirmAuth($remoteInstance, $confirm);

		return $remoteInstance;
	}


	/**
	 * Add a remote instance, based on the address
	 *
	 * @param string $instance
	 * @param string $type
	 * @param bool $overwrite
	 *
	 * @throws RequestNetworkException
	 * @throws SignatoryException
	 * @throws SignatureException
	 * @throws WellKnownLinkNotFoundException
	 * @throws RemoteAlreadyExistsException
	 * @throws RemoteUidException
	 */
	public function addRemoteInstance(
		string $instance, string $type = RemoteInstance::TYPE_EXTERNAL, bool $overwrite = false
	): void {
		if ($this->configService->isLocalInstance($instance)) {
			throw new RemoteAlreadyExistsException('instance is local');
		}

		$remoteInstance = $this->retrieveRemoteInstance($instance);
		$remoteInstance->setType($type);

		try {
			$known = $this->remoteRequest->searchDuplicate($remoteInstance);
			if ($overwrite) {
				$this->remoteRequest->deleteById($known);
			} else {
				throw new RemoteAlreadyExistsException('instance is already known');
			}
		} catch (RemoteNotFoundException $e) {
		}

		$this->remoteRequest->save($remoteInstance);
	}


	/**
	 * Confirm the Auth of a RemoteInstance, based on the result from a request
	 *
	 * @param RemoteInstance $remote
	 * @param string $auth
	 *
	 * @throws SignatureException
	 */
	private function confirmAuth(RemoteInstance $remote, string $auth): void {
		list($algo, $signed) = explode(':', $this->get('auth-signed', $remote->getOrigData()));
		try {
			if ($signed === null) {
				throw new SignatureException('invalid auth-signed');
			}
			$this->verifyString($auth, base64_decode($signed), $remote->getPublicKey(), $algo);
			$remote->setIdentityAuthed(true);
		} catch (SignatureException $e) {
			$this->e(
				$e,
				['auth' => $auth, 'signed' => $signed, 'signatory' => $remote, 'msg' => 'auth not confirmed']
			);
			throw new SignatureException('auth not confirmed');
		}
	}


	/**
	 * @param NC21RequestResult $result
	 *
	 * @return FederatedItemException
	 */
	private function getFederatedItemExceptionFromResult(NC21RequestResult $result): FederatedItemException {
		$data = $result->getAsArray();

		$message = $this->get('message', $data);
		$code = $this->getInt('code', $data);
		$class = $this->get('class', $data);

		try {
			$test = new ReflectionClass($class);
			$this->confirmFederatedItemExceptionFromClass($test);
			$e = $class;
		} catch (ReflectionException | FederatedItemException $_e) {
			$e = $this->getFederatedItemExceptionFromStatus($result->getStatusCode());
		}

		return new $e($message, $code);
	}


	/**
	 * @param ReflectionClass $class
	 *
	 * @return void
	 * @throws FederatedItemException
	 */
	private function confirmFederatedItemExceptionFromClass(ReflectionClass $class): void {
		while (true) {
			foreach (FederatedItemException::$CHILDREN as $e) {
				if ($class->getName() === $e) {
					return;
				}
			}
			$class = $class->getParentClass();
			if (!$class) {
				throw new FederatedItemException();
			}
		}
	}


	/**
	 * @param int $statusCode
	 *
	 * @return string
	 */
	private function getFederatedItemExceptionFromStatus(int $statusCode): string {
		foreach (FederatedItemException::$CHILDREN as $e) {
			if ($e::STATUS === $statusCode) {
				return $e;
			}
		}

		return FederatedItemException::class;
	}


	/**
	 * TODO: confirm if method is really needed
	 *
	 * @param RemoteInstance $remote
	 * @param RemoteInstance|null $stored
	 *
	 * @throws RemoteNotFoundException
	 * @throws RemoteUidException
	 */
	public function confirmValidRemote(RemoteInstance $remote, ?RemoteInstance &$stored = null): void {
		try {
			$stored = $this->remoteRequest->getFromHref($remote->getId());
		} catch (RemoteNotFoundException $e) {
			if ($remote->getInstance() === '') {
				throw new RemoteNotFoundException();
			}

			$stored = $this->remoteRequest->getFromInstance($remote->getInstance());
		}

		if ($stored->getUid() !== $remote->getUid(true)) {
			throw new RemoteUidException();
		}
	}


	/**
	 * TODO: check if this method is not useless
	 *
	 * @param RemoteInstance $remote
	 * @param string $update
	 *
	 * @throws RemoteUidException
	 */
	public function update(RemoteInstance $remote, string $update = self::UPDATE_DATA): void {
		switch ($update) {
			case self::UPDATE_DATA:
				$this->remoteRequest->update($remote);
				break;

			case self::UPDATE_TYPE:
				$this->remoteRequest->updateType($remote);
				break;

			case self::UPDATE_HREF:
				$this->remoteRequest->updateHref($remote);
				break;

			case self::UPDATE_INSTANCE:
				$this->remoteRequest->updateInstance($remote);
				break;
		}
	}

}

