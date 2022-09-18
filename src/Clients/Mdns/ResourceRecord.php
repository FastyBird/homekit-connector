<?php declare(strict_types = 1);

/**
 * ResourceRecord.php
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

use FastyBird\HomeKitConnector\Types;

/**
 * mDNS record
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ResourceRecord
{

	/** @var string */
	private string $name;

	/** @var int */
	private int $type;

	/** @var int */
	private int $ttl;

	/** @var string|Array<string, string|int|string[]>|Array<int, string> */
	private string|array $rdata;

	/** @var int */
	private int $class;

	/** @var bool */
	private bool $question;

	/**
	 * @param string $name
	 * @param int $type
	 * @param int $ttl
	 * @param string|Array<string, string|int|string[]>|Array<int, string> $rdata
	 * @param int $class
	 * @param bool $question
	 */
	public function __construct(
		string $name,
		int $type,
		int $ttl,
		string|array $rdata,
		int $class = Types\ResourceRecordClass::CLASS_INTERNET,
		bool $question = false
	) {
		$this->name = $name;
		$this->type = $type;
		$this->ttl = $ttl;
		$this->rdata = $rdata;
		$this->class = $class;
		$this->question = $question;
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @param string $name
	 *
	 * @return void
	 */
	public function setName(string $name): void
	{
		$this->name = $name;
	}

	/**
	 * @return int
	 */
	public function getType(): int
	{
		return $this->type;
	}

	/**
	 * @param int $type
	 *
	 * @return void
	 */
	public function setType(int $type): void
	{
		$this->type = $type;
	}

	/**
	 * @return int
	 */
	public function getTtl(): int
	{
		return $this->ttl;
	}

	/**
	 * @return string|Array<string, string|int|string[]>|Array<int, string>
	 */
	public function getRdata(): string|array
	{
		return $this->rdata;
	}

	/**
	 * @return int
	 */
	public function getClass(): int
	{
		return $this->class;
	}

	/**
	 * @param int $class
	 *
	 * @return void
	 */
	public function setClass(int $class): void
	{
		$this->class = $class;
	}

	/**
	 * @return bool
	 */
	public function isQuestion(): bool
	{
		return $this->question;
	}

	/**
	 * @return string
	 */
	public function __toString()
	{
		if (is_array($this->rdata)) {
			$rdata = '(';

			foreach ($this->rdata as $key => $value) {
				$rdata .= $key . ': ' . (is_array($value) ? implode(',', $value) : $value) . ', ';
			}

			$rdata = rtrim($rdata, ', ') . ')';
		} else {
			$rdata = $this->rdata;
		}

		return sprintf(
			'%s %s %s %s %s',
			$this->name,
			strval(Types\ResourceRecordType::get($this->type)),
			strval(Types\ResourceRecordClass::get($this->class)),
			$this->ttl,
			$rdata
		);
	}

}
