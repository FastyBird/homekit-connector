<?php declare(strict_types = 1);

/**
 * Loader.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Helpers
 * @since          0.19.0
 *
 * @date           13.09.22
 */

namespace FastyBird\HomeKitConnector\Helpers;

use FastyBird\HomeKitConnector;
use FastyBird\HomeKitConnector\Exceptions;
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

	/**
	 * @throws Exceptions\InvalidState
	 * @throws Nette\IOException
	 */
	public function loadServices(): Utils\ArrayHash
	{
		$metadata = HomeKitConnector\Constants::RESOURCES_FOLDER . DIRECTORY_SEPARATOR . 'services.json';
		$metadata = Utils\FileSystem::read($metadata);

		try {
			return Utils\ArrayHash::from((array) Utils\Json::decode($metadata, Utils\Json::FORCE_ARRAY));
		} catch (Utils\JsonException) {
			throw new Exceptions\InvalidState('Services metadata could not be loaded');
		}
	}

	/**
	 * @throws Exceptions\InvalidState
	 * @throws Nette\IOException
	 */
	public function loadCharacteristics(): Utils\ArrayHash
	{
		$metadata = HomeKitConnector\Constants::RESOURCES_FOLDER . DIRECTORY_SEPARATOR . 'characteristics.json';
		$metadata = Utils\FileSystem::read($metadata);

		try {
			return Utils\ArrayHash::from((array) Utils\Json::decode($metadata, Utils\Json::FORCE_ARRAY));
		} catch (Utils\JsonException) {
			throw new Exceptions\InvalidState('Characteristics metadata could not be loaded');
		}
	}

}
