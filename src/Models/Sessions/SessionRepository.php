<?php declare(strict_types = 1);

/**
 * SessionRepository.php
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

use Doctrine\ORM;
use Doctrine\Persistence;
use FastyBird\HomeKitConnector\Entities;
use FastyBird\HomeKitConnector\Exceptions;
use FastyBird\HomeKitConnector\Queries;
use Nette;
use Ramsey\Uuid;

/**
 * Session repository
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Models
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class SessionRepository
{

	use Nette\SmartObject;

	/** @var ORM\EntityRepository<Entities\Session>|null */
	private ?ORM\EntityRepository $repository = null;

	/** @var Persistence\ManagerRegistry */
	private Persistence\ManagerRegistry $managerRegistry;

	public function __construct(Persistence\ManagerRegistry $managerRegistry)
	{
		$this->managerRegistry = $managerRegistry;
	}

	/**
	 * @param string $identifier
	 *
	 * @return Entities\Session|null
	 */
	public function findOneByIdentifier(
		string $identifier
	): ?Entities\Session {
		$findQuery = new Queries\FindSessionsQuery();
		$findQuery->byId(Uuid\Uuid::fromString($identifier));

		return $this->findOneBy($findQuery);
	}

	/**
	 * @param string $clientUid
	 *
	 * @return Entities\Session|null
	 */
	public function findOneByClientUid(
		string $clientUid
	): ?Entities\Session {
		$findQuery = new Queries\FindSessionsQuery();
		$findQuery->byClientUid($clientUid);

		return $this->findOneBy($findQuery);
	}

	/**
	 * @param Queries\FindSessionsQuery<Entities\Session> $queryObject
	 *
	 * @return Entities\Session|null
	 */
	public function findOneBy(
		Queries\FindSessionsQuery $queryObject
	): ?Entities\Session {
		/** @var Entities\Session|null $session */
		$session = $queryObject->fetchOne($this->getRepository());

		return $session;
	}

	/**
	 * @return ORM\EntityRepository
	 *
	 * @phpstan-return ORM\EntityRepository<Entities\Session>
	 */
	private function getRepository(): ORM\EntityRepository
	{
		if ($this->repository === null) {
			$repository = $this->managerRegistry->getRepository(Entities\Session::class);

			if (!$repository instanceof ORM\EntityRepository) {
				throw new Exceptions\InvalidState('Entity repository could not be loaded');
			}

			$this->repository = $repository;
		}

		return $this->repository;
	}

}
