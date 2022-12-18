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

namespace FastyBird\Connector\HomeKit\Entities\Protocol;

use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Helpers;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use Nette;
use Nette\Utils;
use function array_intersect;
use function array_map;
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
	 * @param array<int>|null $validValues
	 *
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Nette\IOException
	 */
	public function create(
		string $name,
		Service $service,
		DevicesEntities\Channels\Properties\Mapped|DevicesEntities\Channels\Properties\Variable|null $property = null,
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

			if (is_array($validValues)) {
				$validValues = array_map(static fn ($item): int => intval($item), $validValues);
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
			$unit,
		);
	}

}
