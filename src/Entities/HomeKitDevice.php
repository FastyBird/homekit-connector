<?php declare(strict_types = 1);

/**
 * HomeKitDevice.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 * @since          1.0.0
 *
 * @date           29.03.22
 */

namespace FastyBird\Connector\HomeKit\Entities;

use Doctrine\ORM\Mapping as ORM;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use function is_int;

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

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	public function getCategory(): Types\AccessoryCategory
	{
		$property = $this->properties
			->filter(
				// phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
				static fn (DevicesEntities\Devices\Properties\Property $property): bool => $property->getIdentifier() === Types\DevicePropertyIdentifier::IDENTIFIER_CATEGORY
			)
			->first();

		if (
			$property instanceof DevicesEntities\Devices\Properties\Variable
			&& is_int($property->getValue())
			&& Types\AccessoryCategory::isValidValue($property->getValue())
		) {
			return Types\AccessoryCategory::get($property->getValue());
		}

		return Types\AccessoryCategory::get(Types\AccessoryCategory::CATEGORY_OTHER);
	}

}
