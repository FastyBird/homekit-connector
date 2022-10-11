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
use function array_intersect;
use function array_values;
use function floatval;
use function intval;
use function is_array;
use function is_string;
use function sprintf;
use function str_replace;
use function strval;
use function ucwords;

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

	public function __construct(private readonly Helpers\Loader $loader)
	{
	}

	/**
	 * @param Array<int>|null $validValues
	 */
	public function create(
		string $name,
		Service $service,
		MetadataEntities\DevicesModule\Property|null $property = null,
		array|null $validValues = [],
		int|null $maxLength = null,
		float|null $minValue = null,
		float|null $maxValue = null,
		float|null $minStep = null,
		Types\CharacteristicUnit|null $unit = null,
	): Characteristic
	{
		$name = str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));

		$metadata = $this->loader->loadCharacteristics();

		if (!$metadata->offsetExists($name)) {
			throw new Exceptions\InvalidArgument(sprintf(
				'Definition for characteristic: %s was not found',
				$name,
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
			$defaultValidValues = is_array($characteristicsMetadata->offsetGet('ValidValues'))
				? array_values(
					$characteristicsMetadata->offsetGet('ValidValues'),
				)
				: null;

			$validValues = $validValues !== null && $defaultValidValues !== null
				? array_values(
					array_intersect($validValues, $defaultValidValues),
				)
				: $defaultValidValues;
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
			$unit,
		);
	}

}
