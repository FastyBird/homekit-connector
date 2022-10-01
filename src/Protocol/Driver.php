<?php declare(strict_types = 1);

/**
 * Driver.php
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

namespace FastyBird\HomeKitConnector\Protocol;

use FastyBird\HomeKitConnector\Entities;
use FastyBird\HomeKitConnector\Exceptions;
use FastyBird\HomeKitConnector\Types;
use Nette;
use Ramsey\Uuid;
use SplObjectStorage;

/**
 * HAP accessory driver service
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Protocol
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Driver
{

	use Nette\SmartObject;

	/** @var SplObjectStorage<Entities\Protocol\Accessory, null> */
	private SplObjectStorage $accessories;

	public function __construct()
	{
		$this->accessories = new SplObjectStorage();
	}

	/**
	 * @param Uuid\UuidInterface $connectorId
	 *
	 * @return Entities\Protocol\Bridge|null
	 */
	public function getBridge(Uuid\UuidInterface $connectorId): ?Entities\Protocol\Bridge
	{
		foreach ($this->getAccessories() as $accessory) {
			if (
				$accessory instanceof Entities\Protocol\Bridge
				&& $accessory->getConnector()->getId()->equals($connectorId)
			) {
				return $accessory;
			}
		}

		return null;
	}

	/**
	 * @param Entities\Protocol\Bridge $accessory
	 *
	 * @return void
	 */
	public function addBridge(Entities\Protocol\Bridge $accessory): void
	{
		$this->accessories->rewind();

		foreach ($this->accessories as $existingAccessory) {
			if ($existingAccessory->getCategory()->equalsValue(Types\AccessoryCategory::CATEGORY_BRIDGE)) {
				throw new Exceptions\InvalidState('There is already registered bridge');
			}
		}

		$this->addAccessory($accessory);
	}

	/**
	 * @param Entities\Protocol\Device $accessory
	 *
	 * @return void
	 */
	public function addBridgedAccessory(Entities\Protocol\Device $accessory): void
	{
		$this->accessories->rewind();

		foreach ($this->accessories as $existingAccessory) {
			if ($existingAccessory->getCategory()->equalsValue(Types\AccessoryCategory::CATEGORY_BRIDGE)) {
				if (!$existingAccessory instanceof Entities\Protocol\Bridge) {
					throw new Exceptions\InvalidState('Registered device in bridge category is not instance of bridge accessory');
				}

				if ($existingAccessory->getConnector()->getId()->equals($accessory->getDevice()->getConnector())) {
					$this->accessories->detach($existingAccessory);

					$existingAccessory->addAccessory($accessory);

					$this->accessories->attach($existingAccessory);

					return;
				}
			}
		}

		throw new Exceptions\InvalidState('Bridge for given device accessory is not registered. Register it first');
	}

	/**
	 * @param Entities\Protocol\Accessory $accessory
	 *
	 * @return void
	 */
	public function addAccessory(Entities\Protocol\Accessory $accessory): void
	{
		if ($accessory->getAid() === null) {
			$this->accessories->rewind();

			$newAid = 1;
			$searching = true;

			while ($searching || $newAid > 100) {
				// For some reason AID=7 gets unsupported
				if ($newAid === 7) {
					$newAid++;

					continue;
				}

				foreach ($this->accessories as $accessory) {
					if ($accessory->getAid() !== null && $accessory->getAid() === $newAid) {
						$newAid++;

						break;
					}
				}

				$searching = false;
			}

			$accessory->setAid($newAid);
		}

		$this->accessories->rewind();

		foreach ($this->accessories as $existingAccessory) {
			if ($existingAccessory->getAid() === $accessory->getAid()) {
				throw new Exceptions\InvalidArgument('Duplicate AID found when attempting to add accessory');
			}
		}

		$this->accessories->attach($accessory);
	}

	/**
	 * @return Entities\Protocol\Accessory[]
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

	/**
	 * @param Uuid\UuidInterface $connectorId
	 *
	 * @return Array<string, int|Array<string, string|int|bool|Array<string, bool|float|int|int[]|string|string[]|null>[]|null>[]|null>[]
	 */
	public function toHap(Uuid\UuidInterface $connectorId): array
	{
		$accessories = [];

		foreach ($this->getAccessories() as $accessory) {
			if (
				$accessory instanceof Entities\Protocol\Bridge
				&& $accessory->getConnector()->getId()->equals($connectorId)
			) {
				$accessories[] = $accessory->toHap();

				foreach ($accessory->getAccessories() as $bridgeAccessory) {
					$accessories[] = $bridgeAccessory->toHap();
				}
			} elseif ($accessory instanceof Entities\Protocol\Device) {
				$accessories[] = $accessory->toHap();
			}
		}

		return $accessories;
	}

}
