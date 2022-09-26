<?php declare(strict_types = 1);

namespace Tests\Cases;

use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\Queries as DevicesModuleQueries;
use FastyBird\HomeKitConnector\Entities;
use FastyBird\HomeKitConnector\Models;
use FastyBird\HomeKitConnector\Queries;
use Nette\Utils;
use Ramsey\Uuid;
use Tester\Assert;

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

		$findConnectorQuery = new DevicesModuleQueries\FindConnectorsQuery();
		$findConnectorQuery->byId(Uuid\Uuid::fromString('f5a8691b-4917-4866-878f-5217193cf14b'));

		$connector = $repository->findOneBy($findConnectorQuery);

		Assert::true(is_object($connector));

		/** @var Models\Sessions\SessionsManager $manager */
		$manager = $this->getContainer()->getByType(Models\Sessions\SessionsManager::class);

		$clientLtpk = random_bytes(32);

		$entity = $manager->create(Utils\ArrayHash::from([
			'connector' => $connector,
			'clientUid' => '7e11f659-a130-4eb1-b550-dc96c1160c85',
			'clientLtpk' => $clientLtpk,
		]));

		Assert::true(is_object($entity));
		Assert::type(Entities\Session::class, $entity);
		Assert::same($clientLtpk, $entity->getClientLtpk());
		Assert::same('7e11f659-a130-4eb1-b550-dc96c1160c85', $entity->getClientUid());
		Assert::notSame('', $entity->getServerPrivateKey());
	}

	public function testUpdate(): void
	{
		/** @var Models\Sessions\SessionsManager $manager */
		$manager = $this->getContainer()->getByType(Models\Sessions\SessionsManager::class);

		/** @var Models\Sessions\SessionsRepository $repository */
		$repository = $this->getContainer()->getByType(Models\Sessions\SessionsRepository::class);

		$findQuery = new Queries\FindSessionsQuery();
		$findQuery->byClientUid('e348f5fc-42de-459e-926e-2f4cd039c665');

		$entity = $repository->findOneBy($findQuery);

		Assert::true(is_object($entity));
		Assert::type(Entities\Session::class, $entity);

		$clientPublicKey = random_bytes(32);

		$updatedEntity = $manager->update($entity, Utils\ArrayHash::from([
			'clientPublicKey' => $clientPublicKey,
		]));

		Assert::true(is_object($entity));
		Assert::type(Entities\Session::class, $updatedEntity);
		Assert::same($clientPublicKey, $entity->getClientPublicKey());
	}

}

$test_case = new SessionsManagerTest();
$test_case->run();
