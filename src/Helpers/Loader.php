<?php declare(strict_types = 1);

/**
 * Loader.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Helpers
 * @since          1.0.0
 *
 * @date           13.09.22
 */

namespace FastyBird\Connector\HomeKit\Helpers;

use FastyBird\Connector\HomeKit;
use FastyBird\Connector\HomeKit\Exceptions;
use Nette;
use Nette\Utils;
use const DIRECTORY_SEPARATOR;

/**
 * Data structure loader
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Helpers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Loader
{

	private Utils\ArrayHash|null $accessories = null;

	private Utils\ArrayHash|null $services = null;

	private Utils\ArrayHash|null $characteristics = null;

	/**
	 * @throws Exceptions\InvalidState
	 * @throws Nette\IOException
	 */
	public function loadAccessories(): Utils\ArrayHash
	{
		if ($this->accessories === null) {
			$metadata = HomeKit\Constants::RESOURCES_FOLDER . DIRECTORY_SEPARATOR . 'accessories.json';
			$metadata = Utils\FileSystem::read($metadata);

			try {
				$this->accessories = Utils\ArrayHash::from(
					(array) Utils\Json::decode($metadata, forceArrays: true),
				);
			} catch (Utils\JsonException) {
				throw new Exceptions\InvalidState('Accessories metadata could not be loaded');
			}
		}

		return $this->accessories;
	}

	/**
	 * @throws Exceptions\InvalidState
	 * @throws Nette\IOException
	 */
	public function loadServices(): Utils\ArrayHash
	{
		if ($this->services === null) {
			$metadata = HomeKit\Constants::RESOURCES_FOLDER . DIRECTORY_SEPARATOR . 'services.json';
			$metadata = Utils\FileSystem::read($metadata);

			try {
				$this->services = Utils\ArrayHash::from((array) Utils\Json::decode($metadata, forceArrays: true));
			} catch (Utils\JsonException) {
				throw new Exceptions\InvalidState('Services metadata could not be loaded');
			}
		}

		return $this->services;
	}

	/**
	 * @throws Exceptions\InvalidState
	 * @throws Nette\IOException
	 */
	public function loadCharacteristics(): Utils\ArrayHash
	{
		if ($this->characteristics === null) {
			$metadata = HomeKit\Constants::RESOURCES_FOLDER . DIRECTORY_SEPARATOR . 'characteristics.json';
			$metadata = Utils\FileSystem::read($metadata);

			try {
				$this->characteristics = Utils\ArrayHash::from(
					(array) Utils\Json::decode($metadata, forceArrays: true),
				);
			} catch (Utils\JsonException) {
				throw new Exceptions\InvalidState('Characteristics metadata could not be loaded');
			}
		}

		return $this->characteristics;
	}

}
