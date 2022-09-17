<?php declare(strict_types = 1);

/**
 * Bridge.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Protocol
 * @since          0.19.0
 *
 * @date           13.09.22
 */

namespace FastyBird\HomeKitConnector\Protocol\Accessories;

use FastyBird\HomeKitConnector;
use FastyBird\HomeKitConnector\Exceptions;
use FastyBird\HomeKitConnector\Protocol;
use FastyBird\HomeKitConnector\Types;
use SplObjectStorage;

/**
 * HAP bridge accessory
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Bridge extends Accessory
{

	/** @var SplObjectStorage<Accessory, null> */
	private SplObjectStorage $accessories;

	/**
	 * @param Protocol\AccessoryDriver $driver
	 * @param string $displayName
	 */
	public function __construct(
		Protocol\AccessoryDriver $driver,
		string $displayName
	) {
		parent::__construct(
			$driver,
			$displayName,
			HomeKitConnector\Constants::STANDALONE_AID,
			Types\Category::get(Types\Category::CATEGORY_BRIDGE)
		);

		$this->accessories = new SplObjectStorage();
	}

	/**
	 * @param Accessory $accessory
	 *
	 * @return void
	 */
	public function addAccessory(Accessory $accessory): void
	{
		if ($accessory->getCategory()->equalsValue(Types\Category::CATEGORY_BRIDGE)) {
			throw new Exceptions\InvalidArgument('Bridges cannot be bridged');
		}

		$this->accessories->attach($accessory);
	}

	/**
	 * @return Accessory[]
	 */
	public function getAccessories(): array
	{
		$this->accessories->rewind();

		$accessories = [];

		foreach ($this->accessories as $accessory) {
			$accessories[] = $accessory;
		}

		return $accessories;
	}

}
