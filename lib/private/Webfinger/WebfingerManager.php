<?php

declare(strict_types=1);

/**
 * @copyright 2020, Maxence Lange <maxence@artificial-owl.com>
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 *
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\Webfinger;

use OC\Webfinger\Event\WebfingerEvent;
use OC\Webfinger\Exceptions\WebfingerRequestException;
use OC\Webfinger\Model\Webfinger;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;
use OCP\Webfinger\IWebfingerManager;

/**
 * @since 20.0.0
 *
 * Class WebfingerManager
 *
 * @package OC\Webfinger
 */
class WebfingerManager implements IWebfingerManager {


	/** @var IEventDispatcher */
	private $eventDispatcher;


	/**
	 * WebfingerManager constructor.
	 *
	 * @param IEventDispatcher $eventDispatcher
	 *
	 * @since 20.0.0
	 *
	 */
	public function __construct(IEventDispatcher $eventDispatcher) {
		$this->eventDispatcher = $eventDispatcher;
	}


	/**
	 * @param IRequest $request
	 *
	 * @throws WebfingerRequestException
	 * @since 20.0.0
	 */
	public function manageRequest(IRequest $request): void {
		$resource = $request->getParam('resource', '');
		if ($resource === '') {
			throw new WebfingerRequestException(400, 'missing resource');
		}

		$webfinger = new Webfinger($resource);

		$this->dispatch('onRequest', new WebfingerEvent($webfinger));

		if ($this->isEmpty($webfinger)) {
			throw new WebfingerRequestException(404);
		}

		header('Content-type: application/json');
		echo json_encode($webfinger) . "\n";
	}


	/**
	 * @param Webfinger $webfinger
	 *
	 * @return bool
	 * @since 20.0.0
	 */
	private function isEmpty(Webfinger $webfinger): bool {
		if (!empty($webfinger->getLinks())) {
			return false;
		}

		if (!empty($webfinger->getProperties())) {
			return false;
		}

		if (!empty($webfinger->getAliases())) {
			return false;
		}

		return true;
	}


	/**
	 * @param string $context
	 * @param WebfingerEvent $event
	 *
	 * @since 20.0.0
	 */
	private function dispatch(string $context, WebfingerEvent $event) {
		$this->eventDispatcher->dispatch('\OC\Webfinger::' . $context, $event);
	}
}
