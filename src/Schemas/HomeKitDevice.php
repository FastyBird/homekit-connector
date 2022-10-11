<?php declare(strict_types = 1);

/**
 * HomeKitDevice.php
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
 * @extends DevicesModuleSchemas\Devices\Device<Entities\HomeKitDevice>
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Schemas
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class HomeKitDevice extends DevicesModuleSchemas\Devices\Device
{

	/**
	 * Define entity schema type string
	 */
	public const SCHEMA_TYPE = MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT . '/device/' . Entities\HomeKitDevice::DEVICE_TYPE;

	public function getEntityClass(): string
	{
		return Entities\HomeKitDevice::class;
	}

	public function getType(): string
	{
		return self::SCHEMA_TYPE;
	}

}
