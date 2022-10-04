<?php declare(strict_types = 1);

/**
 * ClientsManager.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Models
 * @since          0.19.0
 *
 * @date           13.09.22
 */

namespace FastyBird\HomeKitConnector\Models\Clients;

use FastyBird\HomeKitConnector\Entities;
use FastyBird\HomeKitConnector\Models;
use IPub\DoctrineCrud\Crud;
use Nette;
use Nette\Utils;
use function assert;

/**
 * Clients entities manager
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Models
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ClientsManager
{

	use Nette\SmartObject;

	/**
	 * @param Crud\IEntityCrud<Entities\Client> $entityCrud
	 */
	public function __construct(private Crud\IEntityCrud $entityCrud)
	{
	}

	/**
	 * @param Utils\ArrayHash $values
	 *
	 * @return Entities\Client
	 */
	public function create(Utils\ArrayHash $values): Entities\Client
	{
		// Get entity creator
		$creator = $this->entityCrud->getEntityCreator();

		$entity = $creator->create($values);
		assert($entity instanceof Entities\Client);

		return $entity;
	}

	/**
	 * @param Entities\Client $entity
	 * @param Utils\ArrayHash $values
	 *
	 * @return Entities\Client
	 */
	public function update(
		Entities\Client $entity,
		Utils\ArrayHash $values,
	): Entities\Client {
		$entity = $this->entityCrud->getEntityUpdater()
			->update($values, $entity);
		assert($entity instanceof Entities\Client);

		return $entity;
	}

	/**
	 * @param Entities\Client $entity
	 *
	 * @return bool
	 */
	public function delete(Entities\Client $entity): bool
	{
		// Delete entity from database
		return $this->entityCrud->getEntityDeleter()
			->delete($entity);
	}

}
