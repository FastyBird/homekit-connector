<?php declare(strict_types = 1);

/**
 * LightBulb.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 * @since          1.0.0
 *
 * @date           12.04.23
 */

namespace FastyBird\Connector\HomeKit\Entities\Protocol\Devices;

use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Library\Metadata\ValueObjects as MetadataValueObjects;
use function is_float;
use function is_int;

/**
 * HAP light bulb device accessory
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class LightBulb extends Entities\Protocol\Device
{

	public function recalculateValues(
		Entities\Protocol\Service $service,
		Entities\Protocol\Characteristic $characteristic,
		bool $fromDevice,
	): void
	{
		$updatePropertyType = MetadataTypes\PropertyType::get(
			$fromDevice ? MetadataTypes\PropertyType::TYPE_DYNAMIC : MetadataTypes\PropertyType::TYPE_MAPPED,
		);

		if ($service->getName() === Types\ServiceType::LIGHTBULB) {
			if (
				$characteristic->getName() === Types\CharacteristicType::COLOR_RED
				|| $characteristic->getName() === Types\CharacteristicType::COLOR_GREEN
				|| $characteristic->getName() === Types\CharacteristicType::COLOR_BLUE
				|| $characteristic->getName() === Types\CharacteristicType::COLOR_WHITE
			) {
				$this->calculateRgbToHsb($service, $updatePropertyType);

			} elseif (
				$characteristic->getName() === Types\CharacteristicType::HUE
				|| $characteristic->getName() === Types\CharacteristicType::SATURATION
				|| $characteristic->getName() === Types\CharacteristicType::BRIGHTNESS
			) {
				$this->calculateHsbToRgb($service, $updatePropertyType);
			}
		}
	}

	private function calculateRgbToHsb(
		Entities\Protocol\Service $service,
		MetadataTypes\PropertyType $updatePropertyType,
	): void
	{
		$redCharacteristic = $service->findCharacteristic(Types\CharacteristicType::COLOR_RED);
		$greenCharacteristic = $service->findCharacteristic(Types\CharacteristicType::COLOR_GREEN);
		$blueCharacteristic = $service->findCharacteristic(Types\CharacteristicType::COLOR_BLUE);
		// Optional white channel
		$whiteCharacteristic = $service->findCharacteristic(Types\CharacteristicType::COLOR_WHITE);

		if (
			is_int($redCharacteristic?->getValue())
			&& is_int($greenCharacteristic?->getValue())
			&& is_int($blueCharacteristic?->getValue())
		) {
			$rgb = new MetadataValueObjects\RgbTransformer(
				$redCharacteristic->getValue(),
				$greenCharacteristic->getValue(),
				$blueCharacteristic->getValue(),
				is_int($whiteCharacteristic?->getValue()) ? $whiteCharacteristic->getValue() : null,
			);

			$hsb = $rgb->toHsb();

		} else {
			$hsb = new MetadataValueObjects\HsbTransformer(0, 0, 0);
		}

		$hue = $service->findCharacteristic(Types\CharacteristicType::HUE);

		if (
			$hue !== null
			&& (
				$hue->getProperty() === null
				|| $hue->getProperty()->getType()->equals($updatePropertyType)
			)
		) {
			$hue->setValue($hsb->getHue());
		}

		$saturation = $service->findCharacteristic(Types\CharacteristicType::SATURATION);

		if (
			$saturation !== null
			&& (
				$saturation->getProperty() === null
				|| $saturation->getProperty()->getType()->equals($updatePropertyType)
			)
		) {
			$saturation->setValue($hsb->getSaturation());
		}

		$brightness = $service->findCharacteristic(Types\CharacteristicType::BRIGHTNESS);

		if (
			$brightness !== null
			&& (
				$brightness->getProperty() === null
				|| $brightness->getProperty()->getType()->equals($updatePropertyType)
			)
		) {
			$brightness->setValue($hsb->getBrightness());
		}
	}

	private function calculateHsbToRgb(
		Entities\Protocol\Service $service,
		MetadataTypes\PropertyType $updatePropertyType,
	): void
	{
		$hueCharacteristic = $service->findCharacteristic(Types\CharacteristicType::HUE);
		$saturationCharacteristic = $service->findCharacteristic(Types\CharacteristicType::SATURATION);
		$brightnessCharacteristic = $service->findCharacteristic(Types\CharacteristicType::BRIGHTNESS);

		if (
			(
				is_int($hueCharacteristic?->getValue())
				|| is_float($hueCharacteristic?->getValue())
			)
			&& (
				is_int($saturationCharacteristic?->getValue())
				|| is_float($saturationCharacteristic?->getValue())
			)
			&& is_int($brightnessCharacteristic?->getValue())
		) {
			$brightness = $brightnessCharacteristic->getValue();

			// If brightness is controlled with separate property, we will use 100% brightness for calculation
			if (
				$brightnessCharacteristic->getProperty() !== null
				&& $brightnessCharacteristic->getProperty()->getType()->equalsValue(
					MetadataTypes\PropertyType::TYPE_MAPPED,
				)
			) {
				$brightness = 100;
			}

			$hsb = new MetadataValueObjects\HsbTransformer(
				$hueCharacteristic->getValue(),
				$saturationCharacteristic->getValue(),
				$brightness,
			);

			$rgb = $hsb->toRgb();

		} else {
			$rgb = new MetadataValueObjects\RgbTransformer(0, 0, 0);
		}

		if ($service->hasCharacteristic(Types\CharacteristicType::COLOR_WHITE)) {
			$rgb = $rgb->toHsi()->toRgbw();
		}

		$red = $service->findCharacteristic(Types\CharacteristicType::COLOR_RED);

		if (
			$red !== null
			&& (
				$red->getProperty() === null
				|| $red->getProperty()->getType()->equals($updatePropertyType)
			)
		) {
			$red->setValue($rgb->getRed());
		}

		$green = $service->findCharacteristic(Types\CharacteristicType::COLOR_GREEN);

		if (
			$green !== null
			&& (
				$green->getProperty() === null
				|| $green->getProperty()->getType()->equals($updatePropertyType)
			)
		) {
			$green->setValue($rgb->getGreen());
		}

		$blue = $service->findCharacteristic(Types\CharacteristicType::COLOR_BLUE);

		if (
			$blue !== null
			&& (
				$blue->getProperty() === null
				|| $blue->getProperty()->getType()->equals($updatePropertyType)
			)
		) {
			$blue->setValue($rgb->getBlue());
		}

		$white = $service->findCharacteristic(Types\CharacteristicType::COLOR_WHITE);

		if (
			$white !== null
			&& (
				$white->getProperty() === null
				|| $white->getProperty()->getType()->equals($updatePropertyType)
			)
			&& $rgb->getWhite() !== null
		) {
			$white->setValue($rgb->getWhite());
		}
	}

}
