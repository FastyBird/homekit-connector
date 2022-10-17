<?php declare(strict_types = 1);

/**
 * Server.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 * @since          0.19.0
 *
 * @date           13.09.22
 */

namespace FastyBird\Connector\HomeKit\Entities;

use Doctrine\ORM\Mapping as ORM;
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use IPub\DoctrineCrud;
use IPub\DoctrineCrud\Mapping\Annotation as IPubDoctrine;
use IPub\DoctrineTimestampable;
use Ramsey\Uuid;
use function is_resource;
use function PHPUnit\Framework\assertNotNull;
use function rewind;
use function stream_get_contents;
use function strval;

/**
 * @ORM\Entity
 * @ORM\Table(
 *     name="fb_homekit_connector_clients",
 *     options={
 *       "collate"="utf8mb4_general_ci",
 *       "charset"="utf8mb4",
 *       "comment"="HomeKit connector clients"
 *     },
 *     uniqueConstraints={
 *       @ORM\UniqueConstraint(name="client_uid_unique", columns={"client_uid"})
 *     }
 * )
 */
class Client implements DoctrineCrud\Entities\IEntity,
	DoctrineTimestampable\Entities\IEntityCreated, DoctrineTimestampable\Entities\IEntityUpdated
{

	use DoctrineTimestampable\Entities\TEntityCreated;
	use DoctrineTimestampable\Entities\TEntityUpdated;

	/**
	 * @ORM\Id
	 * @ORM\Column(type="uuid_binary", name="client_id")
	 * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
	 */
	private Uuid\UuidInterface $id;

	/**
	 * @IPubDoctrine\Crud(is="required")
	 * @ORM\ManyToOne(targetEntity="FastyBird\DevicesModule\Entities\Connectors\Connector")
	 * @ORM\JoinColumn(name="connector_id", referencedColumnName="connector_id", nullable="false")
	 */
	private DevicesModuleEntities\Connectors\Connector|null $connector;

	/**
	 * @IPubDoctrine\Crud(is="required")
	 * @ORM\Column(type="string", name="client_uid", length=255, nullable=false)
	 */
	private string $uid;

	/**
	 * @var string|resource
	 *
	 * @IPubDoctrine\Crud(is={"required", "writable"})
	 * @ORM\Column(name="client_public_key", type="binary", nullable=false)
	 */
	private $publicKey;

	/**
	 * @IPubDoctrine\Crud(is="writable")
	 * @ORM\Column(type="boolean", name="client_admin", length=1, nullable=false, options={"default": true})
	 */
	private bool $admin = true;

	public function __construct(
		string $uid,
		string $publicKey,
		DevicesModuleEntities\Connectors\Connector $connector,
		Uuid\UuidInterface|null $id = null,
	)
	{
		$this->id = $id ?? Uuid\Uuid::uuid4();

		$this->uid = $uid;
		$this->publicKey = $publicKey;

		$this->connector = $connector;
	}

	public function getId(): Uuid\UuidInterface
	{
		return $this->id;
	}

	public function getConnector(): DevicesModuleEntities\Connectors\Connector
	{
		assertNotNull($this->connector);

		return $this->connector;
	}

	public function getUid(): string
	{
		return $this->uid;
	}

	public function getPublicKey(): string
	{
		if (is_resource($this->publicKey)) {
			rewind($this->publicKey);

			return strval(stream_get_contents($this->publicKey));
		}

		return $this->publicKey;
	}

	public function setPublicKey(string $publicKey): void
	{
		$this->publicKey = $publicKey;
	}

	public function isAdmin(): bool
	{
		return $this->admin;
	}

	public function setAdmin(bool $admin): void
	{
		$this->admin = $admin;
	}

}
