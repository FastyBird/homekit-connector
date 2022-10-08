<?php declare(strict_types = 1);

/**
 * HomeKitConnector.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Schemas
 * @since          0.1.0
 *
 * @date           29.03.22
 */

namespace FastyBird\HomeKitConnector\Schemas;

use FastyBird\DevicesModule\Schemas as DevicesModuleSchemas;
use FastyBird\HomeKitConnector\Entities;
use FastyBird\Metadata\Types as MetadataTypes;

/**
 * HomeKit connector entity schema
 *
 * @extends DevicesModuleSchemas\Connectors\Connector<Entities\HomeKitConnector>
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Schemas
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class HomeKitConnector extends DevicesModuleSchemas\Connectors\Connector
{

	/**
	 * Define entity schema type string
	 */
	public const SCHEMA_TYPE
		= MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT
		. '/connector/'
		. Entities\HomeKitConnector::CONNECTOR_TYPE;

	public function getEntityClass(): string
	{
		return Entities\HomeKitConnector::class;
	}

	public function getType(): string
	{
		return self::SCHEMA_TYPE;
	}

}
