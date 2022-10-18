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

namespace FastyBird\Connector\HomeKit\Entities;

use Doctrine\ORM\Mapping as ORM;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;

/**
 * @ORM\Entity
 */
class HomeKitDevice extends DevicesEntities\Devices\Device
{

	public const DEVICE_TYPE = 'homekit';

	public function getType(): string
	{
		return self::DEVICE_TYPE;
	}

	public function getDiscriminatorName(): string
	{
		return self::DEVICE_TYPE;
	}

	public function getSource(): MetadataTypes\ConnectorSource
	{
		return MetadataTypes\ConnectorSource::get(MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT);
	}

}
