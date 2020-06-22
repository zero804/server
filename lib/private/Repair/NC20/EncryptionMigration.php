<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020, Roeland Jago Douma <roeland@famdouma.nl>
 *
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\Repair\NC20;

use OCP\IConfig;
use OCP\IDBConnection;

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OC\Repair\NC18;

use OC\Encryption\Manager;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class ResetGeneratedAvatarFlag implements IRepairStep {

	/** @var IConfig */
	private $config;
	/**
	 * @var Manager
	 */
	private $manager;

	public function __construct(IConfig $config,
								Manager $manager) {
		$this->config = $config;
		$this->manager = $manager;
	}

	public function getName(): string {
		return 'Check encryption key format';
	}

	private function shouldRun(): bool {
		$versionFromBeforeUpdate = $this->config->getSystemValue('version', '0.0.0.0');
		return version_compare($versionFromBeforeUpdate, '18.0.0.5', '<=');
	}

	public function run(IOutput $output): void {
		if ($this->manager->isEnabled()) {
			$this->config->setSystemValue('encryption.key_storage_migrated', false);
		}
	}
}
