<?php declare(strict_types = 1);

namespace FastyBird\Connector\HomeKit\Tests\Cases\Unit\Models;

use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Models;
use FastyBird\Connector\HomeKit\Queries;
use FastyBird\Connector\HomeKit\Tests\Cases\Unit\DbTestCase;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\Queries as DevicesModuleQueries;
use IPub\DoctrineCrud;
use Nette;
use Nette\Utils;
use Ramsey\Uuid;
use RuntimeException;
use Throwable;
use function random_bytes;

final class SessionsManagerTest extends DbTestCase
{

	/**
	 * @throws DoctrineCrud\Exceptions\InvalidArgumentException
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Nette\DI\MissingServiceException
	 * @throws RuntimeException
	 * @throws Throwable
	 */
	public function testCreate(): void
	{
		$repository = $this->getContainer()->getByType(DevicesModuleModels\Connectors\ConnectorsRepository::class);

		$findConnectorQuery = new DevicesModuleQueries\FindConnectors();
		$findConnectorQuery->byId(Uuid\Uuid::fromString('f5a8691b-4917-4866-878f-5217193cf14b'));

		$connector = $repository->findOneBy($findConnectorQuery);

		self::assertIsObject($connector);

		$manager = $this->getContainer()->getByType(Models\Clients\ClientsManager::class);

		$clientPublicKey = random_bytes(32);

		$entity = $manager->create(Utils\ArrayHash::from([
			'connector' => $connector,
			'uid' => '7e11f659-a130-4eb1-b550-dc96c1160c85',
			'publicKey' => $clientPublicKey,
		]));

		self::assertSame(Entities\Client::class, $entity::class);
		self::assertSame($clientPublicKey, $entity->getPublicKey());
		self::assertSame('7e11f659-a130-4eb1-b550-dc96c1160c85', $entity->getUid());
	}

	/**
	 * @throws DoctrineCrud\Exceptions\InvalidArgumentException
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Nette\DI\MissingServiceException
	 * @throws RuntimeException
	 * @throws Throwable
	 */
	public function testUpdate(): void
	{
		$manager = $this->getContainer()->getByType(Models\Clients\ClientsManager::class);

		$repository = $this->getContainer()->getByType(Models\Clients\ClientsRepository::class);

		$findQuery = new Queries\FindClients();
		$findQuery->byUid('e348f5fc-42de-459e-926e-2f4cd039c665');

		$entity = $repository->findOneBy($findQuery);

		self::assertIsObject($entity);
		self::assertSame(Entities\Client::class, $entity::class);

		$clientPublicKey = random_bytes(32);

		$manager->update($entity, Utils\ArrayHash::from([
			'publicKey' => $clientPublicKey,
		]));

		self::assertSame($clientPublicKey, $entity->getPublicKey());
	}

}
