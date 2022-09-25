<?php declare(strict_types = 1);

/**
 * SessionsManager.php
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

namespace FastyBird\HomeKitConnector\Models\Sessions;

use FastyBird\HomeKitConnector\Entities;
use FastyBird\HomeKitConnector\Models;
use IPub\DoctrineCrud\Crud;
use Nette;
use Nette\Utils;

/**
 * Sessions entities manager
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Models
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class SessionsManager
{

	use Nette\SmartObject;

	/**
	 * @var Crud\IEntityCrud
	 *
	 * @phpstan-var Crud\IEntityCrud<Entities\Session>
	 */
	private Crud\IEntityCrud $entityCrud;

	/**
	 * @param Crud\IEntityCrud $entityCrud
	 *
	 * @phpstan-param Crud\IEntityCrud<Entities\Session> $entityCrud
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
	 * @return Entities\Session
	 */
	public function create(
		Utils\ArrayHash $values
	): Entities\Session {
		// Get entity creator
		$creator = $this->entityCrud->getEntityCreator();

		/** @var Entities\Session $entity */
		$entity = $creator->create($values);

		return $entity;
	}

	/**
	 * @param Entities\Session $entity
	 * @param Utils\ArrayHash $values
	 *
	 * @return Entities\Session
	 */
	public function update(
		Entities\Session $entity,
		Utils\ArrayHash $values
	): Entities\Session {
		/** @var Entities\Session $entity */
		$entity = $this->entityCrud->getEntityUpdater()
			->update($values, $entity);

		return $entity;
	}

	/**
	 * @param Entities\Session $entity
	 *
	 * @return bool
	 */
	public function delete(
		Entities\Session $entity
	): bool {
		// Delete entity from database
		return $this->entityCrud->getEntityDeleter()
			->delete($entity);
	}

}
