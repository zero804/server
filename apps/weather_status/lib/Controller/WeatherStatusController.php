<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Julien Veyssier
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\WeatherStatus\Controller;

use OCA\WeatherStatus\Service\WeatherStatusService;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\ILogger;
use OCP\IRequest;

class WeatherStatusController extends OCSController {

	/** @var string */
	private $userId;

	/** @var ILogger */
	private $logger;

	/** @var StatusService */
	private $service;

	public function __construct(string $appName,
								IRequest $request,
								ILogger $logger,
								WeatherStatusService $service,
								string $userId) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->logger = $logger;
		$this->service = $service;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function usePersonalAddress(): DataResponse {
		return new DataResponse($this->service->usePersonalAddress());
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function setMode($mode): DataResponse {
		return new DataResponse($this->service->setMode($mode));
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function setLocation($address, $lat, $lon): DataResponse {
		$currentWeather = $this->service->setLocation($address, $lat, $lon);
		return new DataResponse($currentWeather);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function getLocation(): DataResponse {
		$location = $this->service->getLocation();
		return new DataResponse($location);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function getForecast(): DataResponse {
		$forecast = $this->service->getForecast();
		return new DataResponse($forecast);
	}
}
