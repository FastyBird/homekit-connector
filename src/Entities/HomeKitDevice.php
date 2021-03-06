<?php declare(strict_types = 1);

/**
 * HomeKitDevice.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 * @since          0.1.0
 *
 * @date           29.03.22
 */

namespace FastyBird\HomeKitConnector\Entities;

use Doctrine\ORM\Mapping as ORM;
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use FastyBird\Metadata\Types as MetadataTypes;

/**
 * @ORM\Entity
 */
class HomeKitDevice extends DevicesModuleEntities\Devices\Device implements IHomeKitDevice
{

	public const DEVICE_TYPE = 'homekit';

	/**
	 * {@inheritDoc}
	 */
	public function getType(): string
	{
		return self::DEVICE_TYPE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDiscriminatorName(): string
	{
		return self::DEVICE_TYPE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSource(): MetadataTypes\ModuleSourceType|MetadataTypes\PluginSourceType|MetadataTypes\ConnectorSourceType
	{
		return MetadataTypes\ConnectorSourceType::get(MetadataTypes\ConnectorSourceType::SOURCE_CONNECTOR_HOMEKIT);
	}

}
