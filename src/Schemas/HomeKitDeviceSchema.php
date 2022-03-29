<?php declare(strict_types = 1);

/**
 * HomeKitDeviceSchema.php
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
use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\HomeKitConnector\Entities;

/**
 * HomeKit connector entity schema
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Schemas
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 *
 * @phpstan-extends DevicesModuleSchemas\Devices\DeviceSchema<Entities\IHomeKitDevice>
 */
final class HomeKitDeviceSchema extends DevicesModuleSchemas\Devices\DeviceSchema
{

	/**
	 * Define entity schema type string
	 */
	public const SCHEMA_TYPE = MetadataTypes\ConnectorSourceType::SOURCE_CONNECTOR_HOMEKIT . '/device/' . Entities\HomeKitDevice::DEVICE_TYPE;

	/**
	 * {@inheritDoc}
	 */
	public function getEntityClass(): string
	{
		return Entities\HomeKitDevice::class;
	}

	/**
	 * @return string
	 */
	public function getType(): string
	{
		return self::SCHEMA_TYPE;
	}

}
