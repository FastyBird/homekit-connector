<?php declare(strict_types = 1);

/**
 * Message.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Clients
 * @since          0.19.0
 *
 * @date           17.09.22
 */

namespace FastyBird\HomeKitConnector\Clients\Mdns;

use FastyBird\HomeKitConnector\Exceptions;
use Nette;

/**
 * mDNS request/response message
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Message
{

	use Nette\SmartObject;

	/** @var Header */
	private Header $header;

	/** @var ResourceRecord[] */
	private array $questions = [];

	/** @var ResourceRecord[] */
	private array $answers = [];

	/** @var ResourceRecord[] */
	private array $authoritatives = [];

	/** @var ResourceRecord[] */
	private array $additionals = [];

	/**
	 * @param Header $header
	 * @param ResourceRecord[] $questions
	 * @param ResourceRecord[] $answers
	 * @param ResourceRecord[] $authoritatives
	 * @param ResourceRecord[] $additionals
	 */
	public function __construct(
		Header $header,
		array $questions,
		array $answers,
		array $authoritatives,
		array $additionals
	) {
		$this->header = $header;

		$this->questions = [];

		foreach ($questions as $resourceRecord) {
			if (!$resourceRecord->isQuestion()) {
				throw new Exceptions\InvalidArgument('Resource Record provided is not a question.');
			}

			$this->questions[] = $resourceRecord;
		}

		$this->header->setQuestionCount(count($this->questions));

		$this->answers = [];

		foreach ($answers as $resourceRecord) {
			$this->answers[] = $resourceRecord;
		}

		$this->header->setAnswerCount(count($this->answers));

		$this->authoritatives = [];

		foreach ($authoritatives as $resourceRecord) {
			$this->authoritatives[] = $resourceRecord;
		}

		$this->header->setNameServerCount(count($this->authoritatives));

		$this->additionals = [];

		foreach ($additionals as $resourceRecord) {
			$this->additionals[] = $resourceRecord;
		}

		$this->header->setAdditionalRecordsCount(count($this->additionals));
	}

	/**
	 * @return Header
	 */
	public function getHeader(): Header
	{
		return $this->header;
	}

	/**
	 * @return ResourceRecord[]
	 */
	public function getQuestions(): array
	{
		return $this->questions;
	}

	/**
	 * @return ResourceRecord[]
	 */
	public function getAnswers(): array
	{
		return $this->answers;
	}

	/**
	 * @return ResourceRecord[]
	 */
	public function getAuthoritatives(): array
	{
		return $this->authoritatives;
	}

	/**
	 * @return ResourceRecord[]
	 */
	public function getAdditionals(): array
	{
		return $this->additionals;
	}

}
