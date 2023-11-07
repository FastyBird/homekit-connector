<?php declare(strict_types = 1);

namespace FastyBird\Connector\HomeKit\Tests\Cases\Unit\Models;

use Error;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Models;
use FastyBird\Connector\HomeKit\Queries;
use FastyBird\Connector\HomeKit\Tests\Cases\Unit\DbTestCase;
use FastyBird\Library\Bootstrap\Exceptions as BootstrapExceptions;
use IPub\DoctrineOrmQuery;
use Nette;
use RuntimeException;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class SessionsRepositoryTest extends DbTestCase
{

	/**
	 * @throws BootstrapExceptions\InvalidArgument
	 * @throws DoctrineOrmQuery\Exceptions\QueryException
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Nette\DI\MissingServiceException
	 * @throws RuntimeException
	 * @throws Error
	 */
	public function testReadOne(): void
	{
		$repository = $this->getContainer()->getByType(Models\Entities\Clients\ClientsRepository::class);

		$findQuery = new Queries\Entities\FindClients();
		$findQuery->byUid('e348f5fc-42de-459e-926e-2f4cd039c665');

		$entity = $repository->findOneBy($findQuery);

		self::assertIsObject($entity);
		self::assertSame(Entities\Client::class, $entity::class);
		self::assertSame('e348f5fc-42de-459e-926e-2f4cd039c665', $entity->getUid());
	}

	/**
	 * @throws BootstrapExceptions\InvalidArgument
	 * @throws DoctrineOrmQuery\Exceptions\QueryException
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Nette\DI\MissingServiceException
	 * @throws RuntimeException
	 * @throws Error
	 */
	public function testReadResultSet(): void
	{
		$repository = $this->getContainer()->getByType(Models\Entities\Clients\ClientsRepository::class);

		$findQuery = new Queries\Entities\FindClients();

		$resultSet = $repository->getResultSet($findQuery);

		self::assertSame(1, $resultSet->getTotalCount());
	}

}
