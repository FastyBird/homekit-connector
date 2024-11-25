<?php declare(strict_types = 1);

/**
 * Connector.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Documents
 * @since          1.0.0
 *
 * @date           10.02.24
 */

namespace FastyBird\Connector\HomeKit\Documents\Connectors;

use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Core\Application\Documents as ApplicationDocuments;
use FastyBird\Module\Devices\Documents as DevicesDocuments;

#[ApplicationDocuments\Mapping\Document(entity: Entities\Connectors\Connector::class)]
#[ApplicationDocuments\Mapping\DiscriminatorEntry(name: Entities\Connectors\Connector::TYPE)]
class Connector extends DevicesDocuments\Connectors\Connector
{

	public static function getType(): string
	{
		return Entities\Connectors\Connector::TYPE;
	}

}
