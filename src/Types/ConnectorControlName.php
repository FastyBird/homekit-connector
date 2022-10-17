<?php declare(strict_types = 1);

/**
 * ConnectorControlName.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 * @since          0.19.0
 *
 * @date           17.09.22
 */

namespace FastyBird\Connector\HomeKit\Types;

use Consistence;
use FastyBird\Metadata\Types as MetadataTypes;
use function strval;

/**
 * Connector control name types
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ConnectorControlName extends Consistence\Enum\Enum
{

	/**
	 * Define device states
	 */
	public const NAME_REBOOT = MetadataTypes\ControlName::NAME_REBOOT;

	public const NAME_RESET = MetadataTypes\ControlName::NAME_RESET;

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
