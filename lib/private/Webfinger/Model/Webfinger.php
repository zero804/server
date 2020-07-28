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


namespace OC\Webfinger\Model;

use JsonSerializable;
use OCP\Webfinger\Model\IWebfinger;


/**
 * @since 20.0.0
 *
 * @package OC\Webfinger\Model
 */
final class Webfinger implements IWebfinger, JsonSerializable {


	/** @var string */
	private $subject;

	/** @var array */
	private $aliases = [];

	/** @var array */
	private $properties = [];

	/** @var array */
	private $rels = [];

	/** @var array */
	private $links = [];


	/**
	 * Webfinger constructor.
	 *
	 * @param string $subject
	 *
	 * @since 20.0.0
	 */
	public function __construct(string $subject) {
		$this->subject = $subject;
	}


	/**
	 * @return string
	 * @since 20.0.0
	 */
	public function getSubject(): string {
		return $this->subject;
	}


	/**
	 * @return array
	 * @since 20.0.0
	 */
	public function getRels(): array {
		return $this->rels;
	}


	/**
	 * @param string $alias
	 *
	 * @return $this
	 * @since 20.0.0
	 */
	public function addAlias(string $alias): IWebfinger {
		if (!in_array($alias, $this->aliases)) {
			$this->aliases[] = $alias;
		}

		return $this;
	}

	/**
	 * @return array
	 * @since 20.0.0
	 */
	public function getAliases(): array {
		return $this->aliases;
	}


	/**
	 * @param string $property
	 * @param $value
	 *
	 * @return IWebfinger
	 * @since 20.0.0
	 */
	public function addProperty(string $property, $value): IWebfinger {
		$this->properties[$property] = $value;

		return $this;
	}

	/**
	 * @return array
	 * @since 20.0.0
	 */
	public function getProperties(): array {
		return $this->properties;
	}


	/**
	 * @param array $arr
	 *
	 * @return IWebfinger
	 * @since 20.0.0
	 */
	public function addLink(array $arr): IWebfinger {
		$this->links[] = $arr;

		return $this;
	}

	/**
	 * @param JsonSerializable $object
	 *
	 * @return IWebfinger
	 * @since 20.0.0
	 */
	public function addLinkSerialized(JsonSerializable $object): IWebfinger {
		$this->links[] = $object;

		return $this;
	}

	/**
	 * @return array
	 * @since 20.0.0
	 */
	public function getLinks(): array {
		return $this->links;
	}


	/**
	 * @return array
	 * @since 20.0.0
	 */
	public function jsonSerialize(): array {
		$data = [
			'subject'    => $this->getSubject(),
			'properties' => $this->getProperties(),
			'aliases'    => $this->getAliases(),
			'links'      => $this->getLinks()
		];

		return array_filter($data);
	}

}

