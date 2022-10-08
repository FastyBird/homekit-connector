<?php declare(strict_types = 1);

namespace Tests\Cases\Unit;

use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\Queries as DevicesModuleQueries;
use FastyBird\HomeKitConnector\Entities;
use FastyBird\HomeKitConnector\Models;
use FastyBird\HomeKitConnector\Queries;
use Nette\Utils;
use Ramsey\Uuid;
use Tester\Assert;
use function is_object;
use function random_bytes;

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../DbTestCase.php';

/**
 * @testCase
 */
final class SessionsManagerTest extends DbTestCase
{

	public function testCreate(): void
	{
		/** @var DevicesModuleModels\Connectors\ConnectorsRepository $repository */
		$repository = $this->getContainer()->getByType(DevicesModuleModels\Connectors\ConnectorsRepository::class);

		$findConnectorQuery = new DevicesModuleQueries\FindConnectors();
		$findConnectorQuery->byId(Uuid\Uuid::fromString('f5a8691b-4917-4866-878f-5217193cf14b'));

		$connector = $repository->findOneBy($findConnectorQuery);

		Assert::true(is_object($connector));

		/** @var Models\Clients\ClientsManager $manager */
		$manager = $this->getContainer()->getByType(Models\Clients\ClientsManager::class);

		$clientPublicKey = random_bytes(32);

		$entity = $manager->create(Utils\ArrayHash::from([
			'connector' => $connector,
			'uid' => '7e11f659-a130-4eb1-b550-dc96c1160c85',
			'publicKey' => $clientPublicKey,
		]));

		Assert::true(is_object($entity));
		Assert::type(Entities\Client::class, $entity);
		Assert::same($clientPublicKey, $entity->getPublicKey());
		Assert::same('7e11f659-a130-4eb1-b550-dc96c1160c85', $entity->getUid());
	}

	public function testUpdate(): void
	{
		/** @var Models\Clients\ClientsManager $manager */
		$manager = $this->getContainer()->getByType(Models\Clients\ClientsManager::class);

		/** @var Models\Clients\ClientsRepository $repository */
		$repository = $this->getContainer()->getByType(Models\Clients\ClientsRepository::class);

		$findQuery = new Queries\FindClients();
		$findQuery->byUid('e348f5fc-42de-459e-926e-2f4cd039c665');

		$entity = $repository->findOneBy($findQuery);

		Assert::true(is_object($entity));
		Assert::type(Entities\Client::class, $entity);

		$clientPublicKey = random_bytes(32);

		$updatedEntity = $manager->update($entity, Utils\ArrayHash::from([
			'publicKey' => $clientPublicKey,
		]));

		Assert::true(is_object($entity));
		Assert::type(Entities\Client::class, $updatedEntity);
		Assert::same($clientPublicKey, $entity->getPublicKey());
	}

}

$test_case = new SessionsManagerTest();
$test_case->run();
