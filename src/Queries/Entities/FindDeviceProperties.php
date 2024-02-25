<?php declare(strict_types = 1);

/**
 * FindDeviceProperties.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Queries
 * @since          1.0.0
 *
 * @date           16.02.24
 */

namespace FastyBird\Connector\HomeKit\Queries\Entities;

use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use function sprintf;

/**
 * Find device properties entities query
 *
 * @template T of DevicesEntities\Devices\Properties\Property
 * @extends  DevicesQueries\Entities\FindDeviceProperties<T>
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Queries
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class FindDeviceProperties extends DevicesQueries\Entities\FindDeviceProperties
{

	/**
	 * @phpstan-param Types\DevicePropertyIdentifier $identifier
	 *
	 * @throws Exceptions\InvalidArgument
	 */
	public function byIdentifier(Types\DevicePropertyIdentifier|string $identifier): void
	{
		if (!$identifier instanceof Types\DevicePropertyIdentifier) {
			throw new Exceptions\InvalidArgument(
				sprintf('Only instances of: %s are allowed', Types\DevicePropertyIdentifier::class),
			);
		}

		parent::byIdentifier($identifier->value);
	}

}
