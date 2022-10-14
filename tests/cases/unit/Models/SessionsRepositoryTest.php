<?php declare(strict_types = 1);

namespace Tests\Cases\Unit\Models;

use FastyBird\HomeKitConnector\Entities;
use FastyBird\HomeKitConnector\Exceptions;
use FastyBird\HomeKitConnector\Models;
use FastyBird\HomeKitConnector\Queries;
use IPub\DoctrineOrmQuery;
use Nette;
use RuntimeException;
use Tests\Cases\Unit\DbTestCase;

final class SessionsRepositoryTest extends DbTestCase
{

	/**
	 * @throws DoctrineOrmQuery\Exceptions\QueryException
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Nette\DI\MissingServiceException
	 * @throws RuntimeException
	 */
	public function testReadOne(): void
	{
		$repository = $this->getContainer()->getByType(Models\Clients\ClientsRepository::class);

		$findQuery = new Queries\FindClients();
		$findQuery->byUid('e348f5fc-42de-459e-926e-2f4cd039c665');

		$entity = $repository->findOneBy($findQuery);

		self::assertIsObject($entity);
		self::assertSame(Entities\Client::class, $entity::class);
		self::assertSame('e348f5fc-42de-459e-926e-2f4cd039c665', $entity->getUid());
	}

	/**
	 * @throws DoctrineOrmQuery\Exceptions\QueryException
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Nette\DI\MissingServiceException
	 * @throws RuntimeException
	 */
	public function testReadResultSet(): void
	{
		$repository = $this->getContainer()->getByType(Models\Clients\ClientsRepository::class);

		$findQuery = new Queries\FindClients();

		$resultSet = $repository->getResultSet($findQuery);

		self::assertSame(1, $resultSet->getTotalCount());
	}

}
