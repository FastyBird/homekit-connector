<?php declare(strict_types = 1);

/**
 * CharacteristicsFactory.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 * @since          0.19.0
 *
 * @date           13.09.22
 */

namespace FastyBird\HomeKitConnector\Entities\Protocol;

use FastyBird\HomeKitConnector\Exceptions;
use FastyBird\HomeKitConnector\Helpers;
use FastyBird\HomeKitConnector\Types;
use FastyBird\Metadata\Entities as MetadataEntities;
use Nette\Utils;
use function array_values;
use function floatval;
use function sprintf;
use function strval;

/**
 * HAP service characteristics factory
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class CharacteristicsFactory
{

	/** @var Helpers\Loader */
	private Helpers\Loader $loader;

	/**
	 * @param Helpers\Loader $loader
	 */
	public function __construct(
		Helpers\Loader $loader
	) {
		$this->loader = $loader;
	}

	/**
	 * @param string $name
	 * @param Service $service
	 * @param MetadataEntities\Modules\DevicesModule\DynamicPropertyEntity|MetadataEntities\Modules\DevicesModule\StaticPropertyEntity|null $property
	 * @param int[]|null $validValues
	 * @param int|null $maxLength
	 * @param float|null $minValue
	 * @param float|null $maxValue
	 * @param float|null $minStep
	 * @param Types\CharacteristicUnit|null $unit
	 *
	 * @return Characteristic
	 */
	public function create(
		string $name,
		Service $service,
		MetadataEntities\Modules\DevicesModule\DynamicPropertyEntity|MetadataEntities\Modules\DevicesModule\StaticPropertyEntity|null $property = null,
		?array $validValues = [],
		?int $maxLength = null,
		?float $minValue = null,
		?float $maxValue = null,
		?float $minStep = null,
		?Types\CharacteristicUnit $unit = null
	): Characteristic {
		$metadata = $this->loader->loadCharacteristics();

		if (!$metadata->offsetExists($name)) {
			throw new Exceptions\InvalidArgument(sprintf(
				'Definition for characteristic: %s was not found',
				$name
			));
		}

		$characteristicsMetadata = $metadata->offsetGet($name);

		if (
			!$characteristicsMetadata instanceof Utils\ArrayHash
			|| !$characteristicsMetadata->offsetExists('UUID')
			|| !is_string($characteristicsMetadata->offsetGet('UUID'))
			|| !$characteristicsMetadata->offsetExists('Format')
			|| !is_string($characteristicsMetadata->offsetGet('Format'))
			|| !Types\DataType::isValidValue($characteristicsMetadata->offsetGet('Format'))
			|| !$characteristicsMetadata->offsetExists('Permissions')
			|| !$characteristicsMetadata->offsetGet('Permissions') instanceof Utils\ArrayHash
		) {
			throw new Exceptions\InvalidState('Characteristic definition is missing required attributes');
		}

		if (
			$unit === null
			&& $characteristicsMetadata->offsetExists('Unit')
			&& Types\CharacteristicUnit::isValidValue($characteristicsMetadata->offsetGet('Unit'))
		) {
			$unit = Types\CharacteristicUnit::get($characteristicsMetadata->offsetGet('Unit'));
		}

		if ($minValue === null && $characteristicsMetadata->offsetExists('MinValue')) {
			$minValue = floatval($characteristicsMetadata->offsetGet('MinValue'));
		}

		if ($maxValue === null && $characteristicsMetadata->offsetExists('MaxValue')) {
			$maxValue = floatval($characteristicsMetadata->offsetGet('MaxValue'));
		}

		if ($minStep === null && $characteristicsMetadata->offsetExists('MinStep')) {
			$minStep = floatval($characteristicsMetadata->offsetGet('MinStep'));
		}

		if ($maxLength === null && $characteristicsMetadata->offsetExists('MaximumLength')) {
			$maxLength = intval($characteristicsMetadata->offsetGet('MaximumLength'));
		}

		if ($characteristicsMetadata->offsetExists('ValidValues')) {
			$defaultValidValues = is_array($characteristicsMetadata->offsetGet('ValidValues')) ? array_values($characteristicsMetadata->offsetGet('ValidValues')) : null;

			if ($validValues !== null && $defaultValidValues !== null) {
				$validValues = array_values(array_intersect($validValues, $defaultValidValues));
			} else {
				$validValues = $defaultValidValues;
			}
		} else {
			$validValues = null;
		}

		return new Characteristic(
			Helpers\Protocol::hapTypeToUuid(strval($characteristicsMetadata->offsetGet('UUID'))),
			$name,
			Types\DataType::get($characteristicsMetadata->offsetGet('Format')),
			(array) $characteristicsMetadata->offsetGet('Permissions'),
			$service,
			$property,
			$validValues,
			$maxLength,
			$minValue,
			$maxValue,
			$minStep,
			$unit
		);
	}

}
