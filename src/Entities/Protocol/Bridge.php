<?php declare(strict_types = 1);

/**
 * Bridge.php
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

use FastyBird\HomeKitConnector;
use FastyBird\HomeKitConnector\Exceptions;
use FastyBird\HomeKitConnector\Types;
use FastyBird\Metadata\Entities as MetadataEntities;
use SplObjectStorage;

/**
 * HAP bridge accessory
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Bridge extends Accessory
{

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/** @var SplObjectStorage<Device, null> */
	private SplObjectStorage $accessories;

	/**
	 * @param string $name
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 */
	public function __construct(
		string $name,
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	) {
		parent::__construct(
			$name,
			HomeKitConnector\Constants::STANDALONE_AID,
			Types\AccessoryCategory::get(Types\AccessoryCategory::CATEGORY_BRIDGE)
		);

		$this->connector = $connector;

		$this->accessories = new SplObjectStorage();
	}

	/**
	 * @return MetadataEntities\Modules\DevicesModule\IConnectorEntity
	 */
	public function getConnector(): MetadataEntities\Modules\DevicesModule\IConnectorEntity
	{
		return $this->connector;
	}

	/**
	 * @param Device $accessory
	 *
	 * @return void
	 */
	public function addAccessory(Device $accessory): void
	{
		if ($accessory->getCategory()->equalsValue(Types\AccessoryCategory::CATEGORY_BRIDGE)) {
			throw new Exceptions\InvalidArgument('Bridges cannot be bridged');
		}

		if ($accessory->getAid() === null) {
			$this->accessories->rewind();

			$newAid = 2;
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

		if ($accessory->getAid() === $this->getAid()) {
			throw new Exceptions\InvalidArgument('Accessory added to bridge could not have same AID');
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
	 * @return Device[]
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
	 * @param int $aid
	 *
	 * @return Device|null
	 */
	public function getAccessory(int $aid): ?Device
	{
		$this->accessories->rewind();

		foreach ($this->accessories as $accessory) {
			if ($accessory->getAid() === $aid) {
				return $accessory;
			}
		}

		return null;
	}

}
