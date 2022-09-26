<?php declare(strict_types = 1);

namespace Tests\Cases;

use FastyBird\HomeKitConnector\Entities;
use FastyBird\HomeKitConnector\Models;
use FastyBird\HomeKitConnector\Queries;
use IPub\DoctrineOrmQuery;
use Tester\Assert;

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../DbTestCase.php';

/**
 * @testCase
 */
final class SessionsRepositoryTest extends DbTestCase
{

	public function testReadOne(): void
	{
		/** @var Models\Sessions\SessionsRepository $repository */
		$repository = $this->getContainer()->getByType(Models\Sessions\SessionsRepository::class);

		$findQuery = new Queries\FindSessionsQuery();
		$findQuery->byClientUid('e348f5fc-42de-459e-926e-2f4cd039c665');

		$entity = $repository->findOneBy($findQuery);

		Assert::true(is_object($entity));
		Assert::type(Entities\Session::class, $entity);
		Assert::same('e348f5fc-42de-459e-926e-2f4cd039c665', $entity->getClientUid());
	}

	public function testReadResultSet(): void
	{
		/** @var Models\Sessions\SessionsRepository $repository */
		$repository = $this->getContainer()->getByType(Models\Sessions\SessionsRepository::class);

		$findQuery = new Queries\FindSessionsQuery();

		$resultSet = $repository->getResultSet($findQuery);

		Assert::type(DoctrineOrmQuery\ResultSet::class, $resultSet);
		Assert::same(1, $resultSet->getTotalCount());
	}

}

$test_case = new SessionsRepositoryTest();
$test_case->run();
