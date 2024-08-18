<?php declare(strict_types = 1);

/**
 * CharacteristicFactory.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Protocol
 * @since          1.0.0
 *
 * @date           29.01.24
 */

namespace FastyBird\Connector\HomeKit\Protocol\Characteristics;

use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Module\Devices\Documents as DevicesDocuments;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use Ramsey\Uuid;

/**
 * HAP accessory service characteristic factory interface
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Protocol
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface CharacteristicFactory
{

	/**
	 * @param array<Types\CharacteristicPermission> $permissions
	 * @param array<int>|null $validValues
	 */
	public function create(
		Uuid\UuidInterface $typeId,
		string $name,
		Types\DataType $dataType,
		array $permissions,
		Protocol\Services\Service $service,
		DevicesDocuments\Channels\Properties\Property|null $property = null,
		array|null $validValues = [],
		int|null $maxLength = null,
		float|null $minValue = null,
		float|null $maxValue = null,
		float|null $minStep = null,
		float|int|bool|string|null $default = null,
		Types\CharacteristicUnit|null $unit = null,
	): Characteristic;

	/**
	 * @return class-string<DevicesEntities\Channels\Properties\Property>|null
	 */
	public function getEntityClass(): string|null;

}
