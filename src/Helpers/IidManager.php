<?php declare(strict_types = 1);

/**
 * IidManager.php
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

use FastyBird\HomeKitConnector\Entities;
use Nette;
use SplObjectStorage;

/**
 * Maintains a mapping between Service/Characteristic objects and IIDs
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Helpers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class IidManager
{

	use Nette\SmartObject;

	private int $counter;

	/** @var SplObjectStorage<Entities\Protocol\Accessory|Entities\Protocol\Service|Entities\Protocol\Characteristic, int> */
	private SplObjectStorage $storage;

	public function __construct()
	{
		$this->counter = 0;

		$this->storage = new SplObjectStorage();
	}

	/**
	 * Assign an IID to given object. Print warning if already assigned
	 */
	public function assign(
		Entities\Protocol\Accessory|Entities\Protocol\Service|Entities\Protocol\Characteristic $object,
	): void
	{
		if ($this->storage->contains($object)) {
			return;
		}

		$this->counter += 1;
		$this->storage->attach($object, $this->counter);
	}

	/**
	 * Get the object that is assigned the given IID
	 */
	public function getObject(
		int $iid,
	): Entities\Protocol\Accessory|Entities\Protocol\Service|Entities\Protocol\Characteristic|null
	{
		$this->storage->rewind();

		foreach ($this->storage as $object) {
			if ($this->storage[$object] === $iid) {
				return $object;
			}
		}

		return null;
	}

	/**
	 * Get the IID assigned to the given object
	 */
	public function getIid(
		Entities\Protocol\Accessory|Entities\Protocol\Service|Entities\Protocol\Characteristic $object,
	): int|null
	{
		$this->storage->rewind();

		if ($this->storage->contains($object)) {
			return $this->storage[$object];
		}

		return null;
	}

	/**
	 * Remove an object from the IID list
	 */
	public function removeObject(
		Entities\Protocol\Accessory|Entities\Protocol\Service|Entities\Protocol\Characteristic $object,
	): int|null
	{
		$iid = $this->getIid($object);

		if ($iid !== null) {
			$this->storage->detach($object);
		}

		return $iid;
	}

	/**
	 * Remove an object with an IID from the IID list
	 */
	public function removeIid(
		int $iid,
	): Entities\Protocol\Accessory|Entities\Protocol\Service|Entities\Protocol\Characteristic|null
	{
		$object = $this->getObject($iid);

		if ($object !== null) {
			$this->storage->detach($object);
		}

		return $object;
	}

}
