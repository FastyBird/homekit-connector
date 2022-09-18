<?php declare(strict_types = 1);

/**
 * Header.php
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

use Nette;

/**
 * mDNS request/response header
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Header
{

	use Nette\SmartObject;

	public const OPCODE_STANDARD_QUERY = 0;
	public const OPCODE_INVERSE_QUERY = 1;
	public const OPCODE_STATUS_REQUEST = 2;

	public const RCODE_NO_ERROR = 0;
	public const RCODE_FORMAT_ERROR = 1;
	public const RCODE_SERVER_FAILURE = 2;
	public const RCODE_NAME_ERROR = 3;
	public const RCODE_NOT_IMPLEMENTED = 4;
	public const RCODE_REFUSED = 5;

	/** @var int */
	private int $id;

	/** @var bool */
	private bool $response;

	/** @var int */
	private int $opcode;

	/** @var bool */
	private bool $authoritative;

	/** @var bool */
	private bool $truncated;

	/** @var bool */
	private bool $recursionDesired;

	/** @var bool */
	private bool $recursionAvailable;

	/** @var int */
	private int $z;

	/** @var int */
	private int $rcode;

	/** @var int */
	private int $questionCount;

	/** @var int */
	private int $answerCount;

	/** @var int */
	private int $nameServerCount;

	/** @var int */
	private int $additionalRecordsCount;

	/**
	 * @param int $id
	 * @param bool $response
	 * @param int $opcode
	 * @param bool $authoritative
	 * @param bool $truncated
	 * @param bool $recursionDesired
	 * @param bool $recursionAvailable
	 * @param int $z
	 * @param int $rcode
	 * @param int $questionCount
	 * @param int $answerCount
	 * @param int $nameServerCount
	 * @param int $additionalRecordsCount
	 */
	public function __construct(
		int $id,
		bool $response,
		int $opcode,
		bool $authoritative,
		bool $truncated,
		bool $recursionDesired,
		bool $recursionAvailable,
		int $z,
		int $rcode,
		int $questionCount,
		int $answerCount,
		int $nameServerCount,
		int $additionalRecordsCount
	) {
		$this->id = $id;
		$this->response = $response;
		$this->opcode = $opcode;
		$this->authoritative = $authoritative;
		$this->truncated = $truncated;
		$this->recursionDesired = $recursionDesired;
		$this->recursionAvailable = $recursionAvailable;
		$this->z = $z;
		$this->rcode = $rcode;
		$this->questionCount = $questionCount;
		$this->answerCount = $answerCount;
		$this->nameServerCount = $nameServerCount;
		$this->additionalRecordsCount = $additionalRecordsCount;
	}

	/**
	 * @return int
	 */
	public function getId(): int
	{
		return $this->id;
	}

	/**
	 * @param int $id
	 *
	 * @return void
	 */
	public function setId(int $id): void
	{
		$this->id = $id;
	}

	/**
	 * @return bool
	 */
	public function isQuery(): bool
	{
		return !$this->response;
	}

	/**
	 * @return bool
	 */
	public function isResponse(): bool
	{
		return $this->response;
	}

	/**
	 * @return int
	 */
	public function getOpcode(): int
	{
		return $this->opcode;
	}

	/**
	 * @return bool
	 */
	public function isAuthoritative(): bool
	{
		return $this->authoritative;
	}

	/**
	 * @return bool
	 */
	public function isTruncated(): bool
	{
		return $this->truncated;
	}

	/**
	 * @return bool
	 */
	public function isRecursionDesired(): bool
	{
		return $this->recursionDesired;
	}

	/**
	 * @return bool
	 */
	public function isRecursionAvailable(): bool
	{
		return $this->recursionAvailable;
	}

	/**
	 * @return int
	 */
	public function getZ(): int
	{
		return $this->z;
	}

	/**
	 * @return int
	 */
	public function getRcode(): int
	{
		return $this->rcode;
	}

	/**
	 * @return int
	 */
	public function getQuestionCount(): int
	{
		return $this->questionCount;
	}

	/**
	 * @param int $questionCount
	 *
	 * @return void
	 */
	public function setQuestionCount(int $questionCount): void
	{
		$this->questionCount = $questionCount;
	}

	/**
	 * @return int
	 */
	public function getAnswerCount(): int
	{
		return $this->answerCount;
	}

	/**
	 * @param int $answerCount
	 *
	 * @return void
	 */
	public function setAnswerCount(int $answerCount): void
	{
		$this->answerCount = $answerCount;
	}

	/**
	 * @return int
	 */
	public function getNameServerCount(): int
	{
		return $this->nameServerCount;
	}

	/**
	 * @param int $nameServerCount
	 *
	 * @return void
	 */
	public function setNameServerCount(int $nameServerCount): void
	{
		$this->nameServerCount = $nameServerCount;
	}

	/**
	 * @return int
	 */
	public function getAdditionalRecordsCount(): int
	{
		return $this->additionalRecordsCount;
	}

	/**
	 * @param int $additionalRecordsCount
	 *
	 * @return void
	 */
	public function setAdditionalRecordsCount(int $additionalRecordsCount): void
	{
		$this->additionalRecordsCount = $additionalRecordsCount;
	}

}
