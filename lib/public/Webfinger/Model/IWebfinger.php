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


namespace OCP\Webfinger\Model;

use JsonSerializable;

/**
 * @since 20.0.0
 *
 * @package OCP\Webfinger\Model
 */
interface IWebfinger {


	/**
	 * @return string
	 * @since 20.0.0
	 */
	public function getSubject(): string;


	/**
	 * @return array
	 * @since 20.0.0
	 */
	public function getRels(): array;


	/**
	 * @param string $alias
	 *
	 * @return IWebfinger
	 * @since 20.0.0
	 */
	public function addAlias(string $alias): IWebfinger;

	/**
	 * @return array
	 * @since 20.0.0
	 */
	public function getAliases(): array;


	/**
	 * @param string $property
	 * @param $value
	 *
	 * @return IWebfinger
	 * @since 20.0.0
	 */
	public function addProperty(string $property, $value): IWebfinger;

	/**
	 * @return array
	 * @since 20.0.0
	 */
	public function getProperties(): array;


	/**
	 * @param array $arr
	 *
	 * @return IWebfinger
	 * @since 20.0.0
	 */
	public function addLink(array $arr): IWebfinger;

	/**
	 * @param JsonSerializable $object
	 *
	 * @return IWebfinger
	 * @since 20.0.0
	 */
	public function addLinkSerialized(JsonSerializable $object): IWebfinger;


	/**
	 * @return array
	 * @since 20.0.0
	 */
	public function getLinks(): array;
}
