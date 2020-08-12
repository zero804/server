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

namespace OCA\WeatherStatus\Service;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IL10N;
use OCP\App\IAppManager;
use OCP\Accounts\IAccountManager;
use OCP\Accounts\PropertyDoesNotExistException;
use OCP\IUserManager;
use OCP\Http\Client\IClientService;
use OCP\ICacheFactory;

/**
 * Class StatusService
 *
 * @package OCA\WeatherStatus\Service
 */
class WeatherStatusService {
	public const MODE_BROWSER_LOCATION = 1;
	public const MODE_MANUAL_LOCATION = 2;

	/**
	 * StatusService constructor.
	 *
	 * @param ITimeFactory $timeFactory
	 * @param PredefinedStatusService $defaultStatusService,
	 */
	public function __construct(ITimeFactory $timeFactory,
								IClientService $clientService,
								IConfig $config,
								IL10N $l10n,
								IAccountManager $accountManager,
								IUserManager $userManager,
								IAppManager $appManager,
								ICacheFactory $cacheFactory,
								string $userId) {
		$this->timeFactory = $timeFactory;
		$this->config = $config;
		$this->userId = $userId;
		$this->l10n = $l10n;
		$this->accountManager = $accountManager;
		$this->userManager = $userManager;
		$this->appManager = $appManager;
		$this->version = $appManager->getAppVersion('weather_status');
		$this->clientService = $clientService;
		$this->client = $clientService->newClient();
		if ($cacheFactory->isAvailable()) {
			$this->cache = $cacheFactory->createDistributed();
		}
	}

	public function setMode(int $mode): array {
		$this->config->setUserValue($this->userId, 'weather_status', 'mode', $mode);
		return ['success' => true];
	}

	public function usePersonalAddress(): array {
		$account = $this->accountManager->getAccount($this->userManager->get($this->userId));
		try {
			$address = $account->getProperty('address')->getValue();
		} catch (PropertyDoesNotExistException $e) {
			return ['success' => false];
		}
		if ($address === '') {
			return ['success' => false];
		}
		return $this->setAddress($address);
	}

	/**
	 */
	public function setLocation(string $address = '', $lat = null, $lon = null): array {
		if ($lat !== null and $lon !== null) {
			$this->config->setUserValue($this->userId, 'weather_status', 'lat', $lat);
			$this->config->setUserValue($this->userId, 'weather_status', 'lon', $lon);
			$address = $this->resolveLocation($lat, $lon);
			$address = $address ? $address : $this->l10n->t('Unknown address');
			$this->config->setUserValue($this->userId, 'weather_status', 'address', $address);
			return [
				'address' => $address,
			];
		} elseif ($address !== '') {
			return $this->setAddress($address);
		} else {
			return ['success' => false];
		}
	}

	private function resolveLocation($lat, $lon) {
		$params = [
			'lat' => number_format($lat, 3),
			'lon' => number_format($lon, 3),
			'addressdetails' => 1,
			'format' => 'json',
		];
		$url = 'https://nominatim.openstreetmap.org/reverse';
		$result = $this->requestJSON($url, $params);
		return $this->formatOsmAddress($result);
	}

	private function formatOsmAddress($json) {
		if (isset($json['address']) and isset($json['display_name'])) {
			$jsonAddr = $json['address'];
			$cityAddress = '';
			// priority : city, town, village, municipality
			if (isset($jsonAddr['city'])) {
				$cityAddress .= $jsonAddr['city'];
			} elseif (isset($jsonAddr['town'])) {
				$cityAddress .= $jsonAddr['town'];
			} elseif (isset($jsonAddr['village'])) {
				$cityAddress .= $jsonAddr['village'];
			} elseif (isset($jsonAddr['municipality'])) {
				$cityAddress .= $jsonAddr['municipality'];
			} else {
				return $json['display_name'];
			}
			// post code
			if (isset($jsonAddr['postcode'])) {
				$cityAddress .= ', ' . $jsonAddr['postcode'];
			}
			// country
			if (isset($jsonAddr['country'])) {
				$cityAddress .= ', ' . $jsonAddr['country'];
				return $cityAddress;
			} else {
				return $json['display_name'];
			}
		} elseif (isset($json['display_name'])) {
			return $json['display_name'];
		}
		return null;
	}

	public function setAddress(string $address): array {
		$addressInfo = $this->searchForAddress($address);
		if (isset($addressInfo['display_name']) and isset($addressInfo['lat']) and isset($addressInfo['lon'])) {
			$formattedAddress = $this->formatOsmAddress($addressInfo);
			$this->config->setUserValue($this->userId, 'weather_status', 'address', $formattedAddress);
			$this->config->setUserValue($this->userId, 'weather_status', 'lat', $addressInfo['lat']);
			$this->config->setUserValue($this->userId, 'weather_status', 'lon', $addressInfo['lon']);
			$this->config->setUserValue($this->userId, 'weather_status', 'mode', self::MODE_MANUAL_LOCATION);
			return [
				'lat' => $addressInfo['lat'],
				'lon' => $addressInfo['lon'],
				'address' => $formattedAddress,
			];
		} else {
			return ['success' => false];
		}
	}

	private function searchForAddress(string $address): array {
		$params = [
			'format' => 'json',
			'addressdetails' => '1',
			'extratags' => '1',
			'namedetails' => '1',
			'limit' => '1',
		];
		$url = 'https://nominatim.openstreetmap.org/search/' . $address;
		$results = $this->requestJSON($url, $params);
		if (is_array($results) and count($results) > 0) {
			return $results[0];
		}
		return ['error' => $this->l10n->t('No result.')];
	}

	public function getLocation(): array {
		$lat = $this->config->getUserValue($this->userId, 'weather_status', 'lat', '');
		$lon = $this->config->getUserValue($this->userId, 'weather_status', 'lon', '');
		$address = $this->config->getUserValue($this->userId, 'weather_status', 'address', '');
		$mode = $this->config->getUserValue($this->userId, 'weather_status', 'mode', self::MODE_MANUAL_LOCATION);
		return [
			'lat' => $lat,
			'lon' => $lon,
			'address' => $address,
			'mode' => intval($mode),
		];
	}

	public function getForecast(): array {
		$lat = $this->config->getUserValue($this->userId, 'weather_status', 'lat', '');
		$lon = $this->config->getUserValue($this->userId, 'weather_status', 'lon', '');
		if (is_numeric($lat) and is_numeric($lon)) {
			return $this->forecastRequest(floatval($lat), floatval($lon));
		} else {
			return ['success' => false];
		}
	}

	private function forecastRequest(float $lat, float $lon, int $nbValues = 10): array {
		$params = [
			'lat' => number_format($lat, 3),
			'lon' => number_format($lon, 3),
		];
		$url = 'https://api.met.no/weatherapi/locationforecast/2.0/compact';
		$weather = $this->requestJSON($url, $params);
		if (isset($weather['properties']) and isset($weather['properties']['timeseries']) and is_array($weather['properties']['timeseries'])) {
			return array_slice($weather['properties']['timeseries'], 0, $nbValues);
		}
		return ['error' => $this->l10n->t('Malformed JSON data.')];
	}

	private function requestJSON($url, $params = []) {
		if (isset($this->cache)) {
			$cacheKey = $url . '|' . implode(',', $params) . '|' . implode(',', array_keys($params));
			if ($this->cache->hasKey($cacheKey)) {
				return $this->cache->get($cacheKey);
			}
		}
		try {
			$options = [
				'headers' => [
					'User-Agent' => 'NextcloudWeatherStatus/' . $this->version . ' nextcloud.com'
				],
			];

			$reqUrl = $url;
			if (count($params) > 0) {
				$paramsContent = http_build_query($params);
				$reqUrl = $url . '?' . $paramsContent;
			}

			$response = $this->client->get($reqUrl, $options);
			$body = $response->getBody();
			$headers = $response->getHeaders();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Error')];
			} else {
				$json = json_decode($body, true);
				if (isset($this->cache)) {
					// default cache duration is one hour
					$cacheDuration = 60 * 60;
					if (isset($headers['Expires']) and count($headers['Expires']) > 0) {
						// if the Expires response header is set, use it to define cache duration
						$expireTs = (new \Datetime($headers['Expires'][0]))->getTimestamp();
						$nowTs = (new \Datetime())->getTimestamp();
						$cacheDuration = $expireTs - $nowTs;
					}
					$this->cache->set($cacheKey, $json, $cacheDuration);
				}
				return $json;
			}
		} catch (\Exception $e) {
			$this->logger->warning($url . 'API error : ' . $e, ['app' => $this->appName]);
			$response = $e->getResponse();
			$headers = $response->getHeaders();
			return ['error' => $e];
		}
	}
}
