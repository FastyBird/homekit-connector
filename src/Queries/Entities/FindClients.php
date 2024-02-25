<?php declare(strict_types = 1);

/**
 * FindClients.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Queries
 * @since          1.0.0
 *
 * @date           13.09.22
 */

namespace FastyBird\Connector\HomeKit\Queries\Entities;

use Closure;
use Doctrine\ORM;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use IPub\DoctrineOrmQuery;
use Ramsey\Uuid;

/**
 * Find clients entities query
 *
 * @template T of Entities\Clients\Client
 * @extends  DoctrineOrmQuery\QueryObject<T>
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Queries
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class FindClients extends DoctrineOrmQuery\QueryObject
{

	/** @var array<Closure(ORM\QueryBuilder $qb): void> */
	private array $filter = [];

	/** @var array<Closure(ORM\QueryBuilder $qb): void> */
	private array $select = [];

	public function byId(Uuid\UuidInterface $id): void
	{
		$this->filter[] = static function (ORM\QueryBuilder $qb) use ($id): void {
			$qb->andWhere('c.id = :id')->setParameter('id', $id, Uuid\Doctrine\UuidBinaryType::NAME);
		};
	}

	public function byUid(string $uid): void
	{
		$this->filter[] = static function (ORM\QueryBuilder $qb) use ($uid): void {
			$qb->andWhere('c.uid = :uid')->setParameter('uid', $uid);
		};
	}

	public function byConnectorId(Uuid\UuidInterface $connectorId): void
	{
		$this->select[] = static function (ORM\QueryBuilder $qb): void {
			$qb->addSelect('connector');
			$qb->join('c.connector', 'connector');
		};

		$this->filter[] = static function (ORM\QueryBuilder $qb) use ($connectorId): void {
			$qb->andWhere('connector.id = :connector')
				->setParameter('connector', $connectorId, Uuid\Doctrine\UuidBinaryType::NAME);
		};
	}

	public function forConnector(DevicesEntities\Connectors\Connector $connector): void
	{
		$this->select[] = static function (ORM\QueryBuilder $qb): void {
			$qb->addSelect('connector');
			$qb->join('c.connector', 'connector');
		};

		$this->filter[] = static function (ORM\QueryBuilder $qb) use ($connector): void {
			$qb->andWhere('connector.id = :connector')
				->setParameter('connector', $connector->getId(), Uuid\Doctrine\UuidBinaryType::NAME);
		};
	}

	/**
	 * @param ORM\EntityRepository<T> $repository
	 */
	protected function doCreateQuery(ORM\EntityRepository $repository): ORM\QueryBuilder
	{
		return $this->createBasicDql($repository);
	}

	/**
	 * @param ORM\EntityRepository<T> $repository
	 */
	private function createBasicDql(ORM\EntityRepository $repository): ORM\QueryBuilder
	{
		$qb = $repository->createQueryBuilder('c');

		foreach ($this->select as $modifier) {
			$modifier($qb);
		}

		foreach ($this->filter as $modifier) {
			$modifier($qb);
		}

		return $qb;
	}

	/**
	 * @param ORM\EntityRepository<T> $repository
	 */
	protected function doCreateCountQuery(ORM\EntityRepository $repository): ORM\QueryBuilder
	{
		return $this->createBasicDql($repository)->select('COUNT(c.id)');
	}

}
