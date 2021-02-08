<?php
/**
 * @copyright Copyright (c) 2021 Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
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

namespace OCA\Forms\Service;

use DateTimeZone;

use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\SubmissionMapper;
use OCA\Forms\Db\Answer;
use OCA\Forms\Db\AnswerMapper;

use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\IUserSession;

use League\Csv\EscapeFormula;
use League\Csv\Reader;
use League\Csv\Writer;

class SubmissionService {

	/** @var FormMapper */
	private $formMapper;

	/** @var QuestionMapper */
	private $questionMapper;

	/** @var SubmissionMapper */
	private $submissionMapper;

	/** @var AnswerMapper */
	private $answerMapper;

	/** @var IConfig */
	private $config;

	/** @var IDateTimeFormatter */
	private $dateTimeFormatter;

	/** @var IL10N */
	private $l10n;

	/** @var ILogger */
	private $logger;

	/** @var IUserManager */
	private $userManager;

	public function __construct(FormMapper $formMapper,
								QuestionMapper $questionMapper,
								SubmissionMapper $submissionMapper,
								AnswerMapper $answerMapper,
								IConfig $config,
								IDateTimeFormatter $dateTimeFormatter,
								IL10N $l10n,
								ILogger $logger,
								IUserManager $userManager,
								IUserSession $userSession) {
		$this->formMapper = $formMapper;
		$this->questionMapper = $questionMapper;
		$this->submissionMapper = $submissionMapper;
		$this->answerMapper = $answerMapper;
		$this->config = $config;
		$this->dateTimeFormatter = $dateTimeFormatter;
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->userManager = $userManager;

		$this->currentUser = $userSession->getUser();
	}


	/**
	 * Create CSV from Submissions to form
	 * @param string $hash Hash of the form
	 * @return array Array with 'fileName' and 'data'
	 */
	public function getSubmissionsCsv(string $hash): array {
		$form = $this->formMapper->findByHash($hash);

		try {
			$submissionEntities = $this->submissionMapper->findByForm($form->getId());
		} catch (DoesNotExistException $e) {
			// Just ignore, if no Data. Returns empty Submissions-Array
		}

		$questions = $this->questionMapper->findByForm($form->getId());
		$defaultTimeZone = date_default_timezone_get();
		$userTimezone = $this->config->getUserValue('core', 'timezone', $this->currentUser->getUID(), $defaultTimeZone);

		// Process initial header
		$header = [];
		$header[] = $this->l10n->t('User display name');
		$header[] = $this->l10n->t('Timestamp');
		foreach ($questions as $question) {
			$header[] = $question->getText();
		}

		// Init dataset
		$data = [];

		// Process each answers
		foreach ($submissionEntities as $submission) {
			$row = [];

			// User
			$user = $this->userManager->get($submission->getUserId());
			if ($user === null) {
				$row[] = $this->l10n->t('Anonymous user');
			} else {
				$row[] = $user->getDisplayName();
			}
			
			// Date
			$row[] = $this->dateTimeFormatter->formatDateTime($submission->getTimestamp(), 'full', 'full', new DateTimeZone($userTimezone), $this->l10n);

			// Answers, make sure we keep the question order
			$answers = array_reduce($this->answerMapper->findBySubmission($submission->getId()), function (array $carry, Answer $answer) {
				$carry[$answer->getQuestionId()] = $answer->getText();
				return $carry;
			}, []);

			foreach ($questions as $question) {
				$row[] = key_exists($question->getId(), $answers)
					? $answers[$question->getId()]
					: null;
			}

			$data[] = $row;
		}

		$fileName = $form->getTitle() . ' (' . $this->l10n->t('responses') . ').csv';

		return [
			'fileName' => $fileName,
			'data' => $this->array2csv($header, $data),
		];
	}
	
	/**
	 * Convert an array to a csv string
	 * @param array $array
	 * @return string
	 */
	private function array2csv(array $header, array $records): string {
		if (empty($header) && empty($records)) {
			return '';
		}

		// load the CSV document from a string
		$csv = Writer::createFromString('');
		$csv->setOutputBOM(Reader::BOM_UTF8);
		$csv->addFormatter(new EscapeFormula());

		// insert the header
		$csv->insertOne($header);

		// insert all the records
		$csv->insertAll($records);

		return $csv->getContent();
	}
}
