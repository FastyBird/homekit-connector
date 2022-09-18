<?php declare(strict_types = 1);

/**
 * ResourceRecordClass.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 * @since          0.19.0
 *
 * @date           18.09.22
 */

namespace FastyBird\HomeKitConnector\Types;

use Consistence;

/**
 * mDNS resource record class type
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ResourceRecordClass extends Consistence\Enum\Enum
{

	/**
	 * Define classes
	 */
	public const CLASS_INTERNET = 1;
	public const CLASS_CSNET = 2;
	public const CLASS_CHAOS = 3;
	public const CLASS_HESIOD = 4;

	/** @var string[] */
	public static array $classToName = [
		self::CLASS_INTERNET => 'IN',
		self::CLASS_CSNET    => 'CS',
		self::CLASS_CHAOS    => 'CHAOS',
		self::CLASS_HESIOD   => 'HS',
	];

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return self::$classToName[self::getValue()];
	}

}
