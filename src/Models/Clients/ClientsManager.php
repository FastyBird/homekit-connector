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
	 * @var Crud\IEntityCrud
	 *
	 * @phpstan-var Crud\IEntityCrud<Entities\Client>
	 */
	private Crud\IEntityCrud $entityCrud;

	/**
	 * @param Crud\IEntityCrud $entityCrud
	 *
	 * @phpstan-param Crud\IEntityCrud<Entities\Client> $entityCrud
	 */
	public function __construct(
		Crud\IEntityCrud $entityCrud
	) {
		// Entity CRUD for handling entities
		$this->entityCrud = $entityCrud;
	}

	/**
	 * @param Utils\ArrayHash $values
	 *
	 * @return Entities\Client
	 */
	public function create(
		Utils\ArrayHash $values
	): Entities\Client {
		// Get entity creator
		$creator = $this->entityCrud->getEntityCreator();

		/** @var Entities\Client $entity */
		$entity = $creator->create($values);

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
		Utils\ArrayHash $values
	): Entities\Client {
		/** @var Entities\Client $entity */
		$entity = $this->entityCrud->getEntityUpdater()
			->update($values, $entity);

		return $entity;
	}

	/**
	 * @param Entities\Client $entity
	 *
	 * @return bool
	 */
	public function delete(
		Entities\Client $entity
	): bool {
		// Delete entity from database
		return $this->entityCrud->getEntityDeleter()
			->delete($entity);
	}

}
