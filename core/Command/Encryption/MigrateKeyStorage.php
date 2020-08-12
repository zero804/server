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


namespace OC\Core\Command\Encryption;

use OC\Encryption\Keys\Storage;
use OC\Encryption\Util;
use OC\Files\Filesystem;
use OC\Files\View;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\Security\ICrypto;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateKeyStorage extends Command {

	/** @var View */
	protected $rootView;

	/** @var IUserManager */
	protected $userManager;

	/** @var IConfig */
	protected $config;

	/** @var Util */
	protected $util;

	/** @var QuestionHelper */
	protected $questionHelper;
	/**
	 * @var ICrypto
	 */
	private $crypto;

	/**
	 * @param View $view
	 * @param IUserManager $userManager
	 * @param IConfig $config
	 * @param Util $util
	 * @param QuestionHelper $questionHelper
	 */
	public function __construct(View $view, IUserManager $userManager, IConfig $config, Util $util, ICrypto $crypto) {
		parent::__construct();
		$this->rootView = $view;
		$this->userManager = $userManager;
		$this->config = $config;
		$this->util = $util;
		$this->crypto = $crypto;
	}

	protected function configure() {
		parent::configure();
		$this
			->setName('encryption:migrate-key-storage-format')
			->setDescription('Migrate the format of the keystorage to a newer format');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$root = $this->util->getKeyStorageRoot();

		$output->writeln("Updating key storage format");
		$this->updateKeys($root, $output);
		$output->writeln("Key storage format successfully updated");
	}

	/**
	 * move keys to new key storage root
	 *
	 * @param string $root
	 * @param OutputInterface $output
	 * @return bool
	 * @throws \Exception
	 */
	protected function updateKeys(string $root, OutputInterface $output) {
		$output->writeln("Start to update the keys:");

		$this->updateSystemKeys($root);
		$this->updateUsersKeys($root, $output);
		$this->config->deleteSystemValue('encryption.key_storage_migrated');
		return true;
	}

	/**
	 * move system key folder
	 *
	 * @param string $root
	 */
	protected function updateSystemKeys($root) {
		if (!$this->rootView->is_dir($root . '/files_encryption')) {
			return;
		}

		$this->traverseKeys($root . '/files_encryption');
	}

	private function traverseKeys(string $folder, ?string $uid) {
		$listing = $this->rootView->getDirectoryContent($folder);

		foreach ($listing as $node) {
			if ($node['mimetype'] === 'httpd/unix-directory') {
				$this->traverse($folder . '/' . $node['name']);
			} else {
				$endsWith = function ($haystack, $needle) {
					$length = strlen($needle);
					if ($length === 0) {
						return true;
					}

					return (substr($haystack, -$length) === $needle);
				};

				if ($node['name'] === 'fileKey' ||
					$endsWith($node['name'], '.privateKey') ||
					$endsWith($node['name'], '.publicKey') ||
					$endsWith($node['name'], 'shareKey')) {
					$path = $folder . '/' . $node['name'];

					$content = $this->rootView->file_get_contents($path);
					$data = json_decode($content, true);
					if (is_array($data) && isset($data['key'])) {
						continue;
					}

					$data = [
						'key' => base64_encode($content),
						'uid' => $uid,
					];

					$enc = $this->crypto->encrypt(json_encode($data));
					$this->rootView->file_put_contents($path, $enc);
				}
			}
		}
	}

	private function traverseFileKeys(string $folder) {
		$listing = $this->rootView->getDirectoryContent($folder);

		foreach ($listing as $node) {
			if ($node['mimetype'] === 'httpd/unix-directory') {
				$this->traverse($folder . '/' . $node['name']);
			} else {
				$endsWith = function ($haystack, $needle) {
					$length = strlen($needle);
					if ($length === 0) {
						return true;
					}

					return (substr($haystack, -$length) === $needle);
				};

				if ($node['name'] === 'fileKey' ||
					$endsWith($node['name'], '.privateKey') ||
					$endsWith($node['name'], '.publicKey') ||
					$endsWith($node['name'], 'shareKey')) {
					$path = $folder . '/' . $node['name'];

					$content = $this->rootView->file_get_contents($path);
					$data = json_decode($content, true);
					if (is_array($data) && isset($data['key'])) {
						continue;
					}

					$data = [
						'key' => base64_encode($content)
					];

					$enc = $this->crypto->encrypt(json_encode($data));

					$this->rootView->file_put_contents($path, json_encode($data));
				}
			}
		}
	}


	/**
	 * setup file system for the given user
	 *
	 * @param string $uid
	 */
	protected function setupUserFS($uid) {
		\OC_Util::tearDownFS();
		\OC_Util::setupFS($uid);
	}


	/**
	 * iterate over each user and move the keys to the new storage
	 *
	 * @param string $root
	 * @param OutputInterface $output
	 */
	protected function updateUsersKeys($root, OutputInterface $output) {
		$progress = new ProgressBar($output);
		$progress->start();


		foreach ($this->userManager->getBackends() as $backend) {
			$limit = 500;
			$offset = 0;
			do {
				$users = $backend->getUsers('', $limit, $offset);
				foreach ($users as $user) {
					$progress->advance();
					$this->setupUserFS($user);
					$this->updateUserKeys($user, $root);
				}
				$offset += $limit;
			} while (count($users) >= $limit);
		}
		$progress->finish();
	}

	/**
	 * move user encryption folder to new root folder
	 *
	 * @param string $user
	 * @param string $root
	 * @throws \Exception
	 */
	protected function updateUserKeys($user, $root) {
		if ($this->userManager->userExists($user)) {
			$source = $root . '/' . $user . '/files_encryption/OC_DEFAULT_MODULE';
			if ($this->rootView->is_dir($source)) {
				$this->traverseKeys($source, $user);
			}

			$source = $root . '/' . $user . '/files_encryption/keys';
			if ($this->rootView->is_dir($source)) {
				$this->traverseFileKeys($source);
			}
		}
	}

	/**
	 * Make preparations to filesystem for saving a key file
	 *
	 * @param string $path relative to data/
	 */
	protected function prepareParentFolder($path) {
		$path = Filesystem::normalizePath($path);
		// If the file resides within a subdirectory, create it
		if ($this->rootView->file_exists($path) === false) {
			$sub_dirs = explode('/', ltrim($path, '/'));
			$dir = '';
			foreach ($sub_dirs as $sub_dir) {
				$dir .= '/' . $sub_dir;
				if ($this->rootView->file_exists($dir) === false) {
					$this->rootView->mkdir($dir);
				}
			}
		}
	}

	/**
	 * check if target already exists
	 *
	 * @param $path
	 * @return bool
	 * @throws \Exception
	 */
	protected function targetExists($path) {
		if ($this->rootView->file_exists($path)) {
			throw new \Exception("new folder '$path' already exists");
		}

		return false;
	}
}
