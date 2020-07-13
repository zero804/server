<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OC;

use Closure;
use OCP\AppFramework\Services\InitialStateProvider;
use OCP\AppFramework\QueryException;
use OCP\IInitialStateService;
use OCP\ILogger;
use OCP\IServerContainer;

class InitialStateService implements IInitialStateService {

	/** @var ILogger */
	private $logger;

	/** @var IServerContainer */
	private $serverContainer;

	/** @var string[][] */
	private $states = [];

	/** @var Closure[][] */
	private $lazyStates = [];

	/** @var array[] */
	private $lazyBases = [];

	public function __construct(IServerContainer $serverContainer, ILogger $logger) {
		$this->logger = $logger;
		$this->serverContainer = $serverContainer;
	}

	public function provideInitialState(string $appName, string $key, $data): void {
		// Scalars and JsonSerializable are fine
		if (is_scalar($data) || $data instanceof \JsonSerializable || is_array($data)) {
			if (!isset($this->states[$appName])) {
				$this->states[$appName] = [];
			}
			$this->states[$appName][$key] = json_encode($data);
			return;
		}

		$this->logger->warning('Invalid data provided to provideInitialState by ' . $appName);
	}

	public function provideLazyInitialState(string $appName, string $key, Closure $closure): void {
		if (!isset($this->lazyStates[$appName])) {
			$this->lazyStates[$appName] = [];
		}
		$this->lazyStates[$appName][$key] = $closure;
	}

	/**
	 * Invoke all callbacks to populate the `states` property
	 */
	private function invokeLazyStateCallbacks(): void {
		foreach ($this->lazyStates as $app => $lazyStates) {
			foreach ($lazyStates as $key => $lazyState) {
				$this->provideInitialState($app, $key, $lazyState());
			}
		}
		$this->lazyStates = [];
	}

	public function getInitialStates(): array {
		$this->invokeLazyStateCallbacks();
		$this->invokeLazyBase();

		$appStates = [];
		foreach ($this->states as $app => $states) {
			foreach ($states as $key => $value) {
				$appStates["$app-$key"] = $value;
			}
		}
		return $appStates;
	}

	private function invokeLazyBase(): void {
		foreach ($this->lazyBases as $lazyBase) {
			try {
				$state = $this->serverContainer->query($lazyBase['class']);
			} catch (QueryException $e) {
				$this->logger->logException($e, [
					'message' => 'Could not query: ' . $lazyBase['class'],
					'level' => ILogger::WARN,
				]);
				continue;
			}

			if (!($state instanceof InitialStateProvider)) {
				$this->logger->debug($lazyBase['class'] . ' is not an instance of ' . InitialStateProvider::class);
				continue;
			}

			$this->provideInitialState($lazyBase['appId'], $state->getKey(), $state->getData());
		}

		$this->lazyBases = [];
	}

	public function registerLazyBase(string $appId, string $class): void {
		$this->lazyBases[] = [
			'appId' => $appId,
			'class' => $class,
		];
	}
}
