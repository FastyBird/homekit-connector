<?php declare(strict_types = 1);

/**
 * ClientsRepository.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Models
 * @since          1.0.0
 *
 * @date           13.09.22
 */

namespace FastyBird\Connector\HomeKit\Models\Entities\Clients;

use Doctrine\ORM;
use Doctrine\Persistence;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Entities\Clients\Client;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Queries;
use FastyBird\Library\Application\Exceptions as ApplicationExceptions;
use FastyBird\Library\Application\Helpers as ApplicationHelpers;
use IPub\DoctrineOrmQuery;
use Nette;
use function is_array;

/**
 * Clients repository
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Models
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ClientsRepository
{

	use Nette\SmartObject;

	/** @var ORM\EntityRepository<Client>|null */
	private ORM\EntityRepository|null $repository = null;

	public function __construct(
		private readonly ApplicationHelpers\Database $database,
		private readonly Persistence\ManagerRegistry $managerRegistry,
	)
	{
	}

	/**
	 * @param Queries\Entities\FindClients<Client> $queryObject
	 *
	 * @throws ApplicationExceptions\InvalidState
	 */
	public function findOneBy(
		Queries\Entities\FindClients $queryObject,
	): Entities\Clients\Client|null
	{
		return $this->database->query(
			fn (): Entities\Clients\Client|null => $queryObject->fetchOne($this->getRepository()),
		);
	}

	/**
	 * @param Queries\Entities\FindClients<Client> $queryObject
	 *
	 * @return DoctrineOrmQuery\ResultSet<Client>
	 *
	 * @throws ApplicationExceptions\InvalidState
	 * @throws Exceptions\InvalidState
	 */
	public function getResultSet(
		Queries\Entities\FindClients $queryObject,
	): DoctrineOrmQuery\ResultSet
	{
		$result = $this->database->query(
			fn (): DoctrineOrmQuery\ResultSet|array => $queryObject->fetch($this->getRepository()),
		);

		if (is_array($result)) {
			throw new Exceptions\InvalidState('Result set could not be created');
		}

		return $result;
	}

	/**
	 * @return ORM\EntityRepository<Client>
	 */
	private function getRepository(): ORM\EntityRepository
	{
		if ($this->repository === null) {
			$this->repository = $this->managerRegistry->getRepository(Entities\Clients\Client::class);
		}

		return $this->repository;
	}

}
