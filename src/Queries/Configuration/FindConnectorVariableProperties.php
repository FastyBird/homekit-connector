<?php declare(strict_types = 1);

/**
 * FindConnectorVariableProperties.php
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

namespace FastyBird\Connector\HomeKit\Queries\Configuration;

use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Module\Devices\Documents as DevicesDocuments;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use function sprintf;

/**
 * Find connector variable properties entities query
 *
 * @template T of DevicesDocuments\Connectors\Properties\Variable
 * @extends  DevicesQueries\Configuration\FindConnectorVariableProperties<T>
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Queries
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class FindConnectorVariableProperties extends DevicesQueries\Configuration\FindConnectorVariableProperties
{

	/**
	 * @phpstan-param Types\ConnectorPropertyIdentifier $identifier
	 *
	 * @throws Exceptions\InvalidArgument
	 */
	public function byIdentifier(Types\ConnectorPropertyIdentifier|string $identifier): void
	{
		if (!$identifier instanceof Types\ConnectorPropertyIdentifier) {
			throw new Exceptions\InvalidArgument(
				sprintf('Only instances of: %s are allowed', Types\ConnectorPropertyIdentifier::class),
			);
		}

		parent::byIdentifier($identifier->value);
	}

}
