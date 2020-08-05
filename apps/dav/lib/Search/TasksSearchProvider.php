<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Georg Ehrke
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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
namespace OCA\DAV\Search;

use OCA\DAV\CalDAV\CalDavBackend;
use OCP\IUser;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use Sabre\VObject\Component;

/**
 * Class TasksSearchProvider
 *
 * @package OCA\DAV\Search
 */
class TasksSearchProvider extends ACalendarSearchProvider {

	/**
	 * @var string[]
	 */
	private static $searchProperties = [
		'SUMMARY',
		'DESCRIPTION',
		'CATEGORIES',
	];

	/**
	 * @var string[]
	 */
	private static $searchParameters = [];

	/**
	 * @var string
	 */
	private static $componentType = 'VTODO';

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'tasks-dav';
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l10n->t('Tasks');
	}

	/**
	 * @inheritDoc
	 */
	public function search(IUser $user,
						   ISearchQuery $query): SearchResult {
		if (!$this->appManager->isEnabledForUser('tasks', $user)) {
			return SearchResult::complete($this->getName(), []);
		}

		$principalUri = 'principals/users/' . $user->getUID();
		$calendarsById = $this->getSortedCalendars($principalUri);
		$subscriptionsById = $this->getSortedSubscriptions($principalUri);

		$searchResults = $this->backend->searchPrincipalUri(
			$principalUri,
			$query->getTerm(),
			[self::$componentType],
			self::$searchProperties,
			self::$searchParameters,
			[
				'limit' => $query->getLimit(),
				'offset' => $query->getCursor(),
			]
		);
		$formattedResults = \array_map(function (array $taskRow) use ($calendarsById, $subscriptionsById):TasksSearchResultEntry {
			$component = $this->getPrimaryComponent($taskRow['calendardata'], self::$componentType);
			$title = (string)($component->SUMMARY ?? $this->l10n->t('Untitled task'));
			$subline = $this->generateSubline($component);

			if ($taskRow['calendartype'] === CalDavBackend::CALENDAR_TYPE_CALENDAR) {
				$calendar = $calendarsById[$taskRow['calendarid']];
			} else {
				$calendar = $subscriptionsById[$taskRow['calendarid']];
			}
			$resourceUrl = $this->getDeepLinkToTasksApp($calendar['uri'], $taskRow['uri']);

			return new TasksSearchResultEntry('', $title, $subline, $resourceUrl, 'icon-checkmark', false);
		}, $searchResults);

		return SearchResult::paginated(
			$this->getName(),
			$formattedResults,
			$query->getCursor() + count($formattedResults)
		);
	}

	/**
	 * @param string $calendarUri
	 * @param string $taskUri
	 * @return string
	 */
	protected function getDeepLinkToTasksApp(string $calendarUri,
											 string $taskUri): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->linkToRoute('tasks.page.index')
			. '#/calendars/'
			. $calendarUri
			. '/tasks/'
			. $taskUri
		);
	}

	/**
	 * @param Component $taskComponent
	 * @return string
	 */
	protected function generateSubline(Component $taskComponent): string {
		if ($taskComponent->COMPLETED) {
			$completedDateTime = new \DateTime($taskComponent->COMPLETED->getDateTime()->format(\DateTime::ATOM));
			$formattedDate = $this->l10n->l('date', $completedDateTime, ['width' => 'medium']);
			return $this->l10n->t('Completed on %s', [$formattedDate]);
		}

		if ($taskComponent->DUE) {
			$dueDateTime = new \DateTime($taskComponent->DUE->getDateTime()->format(\DateTime::ATOM));
			$formattedDate = $this->l10n->l('date', $dueDateTime, ['width' => 'medium']);

			if ($taskComponent->DUE->hasTime()) {
				$formattedTime =  $this->l10n->l('time', $dueDateTime, ['width' => 'short']);
				return $this->l10n->t('Due on %s by %s', [$formattedDate, $formattedTime]);
			}

			return $this->l10n->t('Due on %s', [$formattedDate]);
		}

		return '';
	}
}