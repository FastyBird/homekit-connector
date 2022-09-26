<?php declare(strict_types = 1);

/**
 * Session.php
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

namespace FastyBird\HomeKitConnector\Entities;

use Doctrine\ORM\Mapping as ORM;
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use FastyBird\HomeKitConnector\Helpers;
use IPub\DoctrineCrud;
use IPub\DoctrineCrud\Mapping\Annotation as IPubDoctrine;
use IPub\DoctrineTimestampable;
use Ramsey\Uuid;
use function Curve25519\publicKey;

/**
 * @ORM\Entity
 * @ORM\Table(
 *     name="fb_homekit_connector_sessions",
 *     options={
 *       "collate"="utf8mb4_general_ci",
 *       "charset"="utf8mb4",
 *       "comment"="HomeKit connector sessions"
 *     },
 *     uniqueConstraints={
 *       @ORM\UniqueConstraint(name="session_client_uid_unique", columns={"session_client_uid"})
 *     }
 * )
 */
class Session implements DoctrineCrud\Entities\IEntity,
	DoctrineTimestampable\Entities\IEntityCreated, DoctrineTimestampable\Entities\IEntityUpdated
{

	use DoctrineTimestampable\Entities\TEntityCreated;
	use DoctrineTimestampable\Entities\TEntityUpdated;

	/**
	 * @var Uuid\UuidInterface
	 *
	 * @ORM\Id
	 * @ORM\Column(type="uuid_binary", name="session_id")
	 * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
	 */
	private Uuid\UuidInterface $id;

	/**
	 * @var DevicesModuleEntities\Connectors\IConnector
	 *
	 * @IPubDoctrine\Crud(is="required")
	 * @ORM\ManyToOne(targetEntity="FastyBird\DevicesModule\Entities\Connectors\Connector")
	 * @ORM\JoinColumn(name="connector_id", referencedColumnName="connector_id")
	 */
	private DevicesModuleEntities\Connectors\IConnector $connector;

	/**
	 * @var string
	 *
	 * @IPubDoctrine\Crud(is="required")
	 * @ORM\Column(type="string", name="session_client_uid", length=255, nullable=false)
	 */
	private string $clientUid;

	/**
	 * @var string|resource
	 *
	 * @IPubDoctrine\Crud(is="required")
	 * @ORM\Column(name="session_client_ltpk", type="binary", nullable=false)
	 */
	private $clientLtpk;

	/**
	 * @var string|resource
	 *
	 * @IPubDoctrine\Crud(is="writable")
	 * @ORM\Column(name="session_server_private_key", type="binary", nullable=false)
	 */
	private $serverPrivateKey;

	/**
	 * @var string|resource|null
	 *
	 * @IPubDoctrine\Crud(is="writable")
	 * @ORM\Column(name="session_client_public_key", type="binary", nullable=true)
	 */
	private $clientPublicKey;

	/**
	 * @var string|resource|null
	 *
	 * @IPubDoctrine\Crud(is="writable")
	 * @ORM\Column(name="session_shared_key", type="binary", nullable=true)
	 */
	private $sharedKey;

	/**
	 * @var string|resource|null
	 *
	 * @IPubDoctrine\Crud(is="writable")
	 * @ORM\Column(name="session_hasing_key", type="binary", nullable=true)
	 */
	private $hashingKey;

	/**
	 * @var string|resource|null
	 *
	 * @IPubDoctrine\Crud(is="writable")
	 * @ORM\Column(name="session_decrypt_key", type="binary", nullable=true)
	 */
	private $decryptKey;

	/**
	 * @var string|resource|null
	 *
	 * @IPubDoctrine\Crud(is="writable")
	 * @ORM\Column(name="session_encrypt_key", type="binary", nullable=true)
	 */
	private $encryptKey;

	/**
	 * @var bool
	 *
	 * @IPubDoctrine\Crud(is="writable")
	 * @ORM\Column(type="boolean", name="session_admin", length=1, nullable=false, options={"default": true})
	 */
	private bool $admin = true;

	/**
	 * @param string $clientUid
	 * @param string $clientLtpk
	 * @param DevicesModuleEntities\Connectors\IConnector $connector
	 * @param Uuid\UuidInterface|null $id
	 */
	public function __construct(
		string $clientUid,
		string $clientLtpk,
		DevicesModuleEntities\Connectors\IConnector $connector,
		?Uuid\UuidInterface $id = null
	) {
		$this->id = $id ?? Uuid\Uuid::uuid4();

		$this->clientUid = $clientUid;
		$this->clientLtpk = $clientLtpk;

		$this->connector = $connector;

		$this->serverPrivateKey = hex2bin(Helpers\Protocol::generateSignKey()) ?: '';
	}

	/**
	 * @return Uuid\UuidInterface
	 */
	public function getId(): Uuid\UuidInterface
	{
		return $this->id;
	}

	/**
	 * @return DevicesModuleEntities\Connectors\IConnector
	 */
	public function getConnector(): DevicesModuleEntities\Connectors\IConnector
	{
		return $this->connector;
	}

	/**
	 * @return string
	 */
	public function getClientUid(): string
	{
		return $this->clientUid;
	}

	/**
	 * @return string
	 */
	public function getClientLtpk(): string
	{
		if (is_resource($this->clientLtpk)) {
			rewind($this->clientLtpk);

			return strval(stream_get_contents($this->clientLtpk));
		}

		return $this->clientLtpk;
	}

	/**
	 * @return string
	 */
	public function getServerPrivateKey(): string
	{
		if (is_resource($this->serverPrivateKey)) {
			rewind($this->serverPrivateKey);

			return strval(stream_get_contents($this->serverPrivateKey));
		}

		return $this->serverPrivateKey;
	}

	/**
	 * @return string|null
	 */
	public function getServerPublicKey(): ?string
	{
		return publicKey($this->getServerPrivateKey());
	}

	/**
	 * @return string|null
	 */
	public function getClientPublicKey(): ?string
	{
		if (is_resource($this->clientPublicKey)) {
			rewind($this->clientPublicKey);

			return strval(stream_get_contents($this->clientPublicKey));
		}

		return $this->clientPublicKey;
	}

	/**
	 * @param string $clientPublicKey
	 */
	public function setClientPublicKey(string $clientPublicKey): void
	{
		$this->clientPublicKey = $clientPublicKey;
	}

	/**
	 * @return string|null
	 */
	public function getSharedKey(): ?string
	{
		if (is_resource($this->sharedKey)) {
			rewind($this->sharedKey);

			return strval(stream_get_contents($this->sharedKey));
		}

		return $this->sharedKey;
	}

	/**
	 * @param string $sharedKey
	 */
	public function setSharedKey(string $sharedKey): void
	{
		$this->sharedKey = $sharedKey;
	}

	/**
	 * @return string|null
	 */
	public function getHashingKey(): ?string
	{
		if (is_resource($this->hashingKey)) {
			rewind($this->hashingKey);

			return strval(stream_get_contents($this->hashingKey));
		}

		return $this->hashingKey;
	}

	/**
	 * @param string $hashingKey
	 */
	public function setHashingKey(string $hashingKey): void
	{
		$this->hashingKey = $hashingKey;
	}

	/**
	 * @return string|null
	 */
	public function getDecryptKey(): ?string
	{
		if (is_resource($this->decryptKey)) {
			rewind($this->decryptKey);

			return strval(stream_get_contents($this->decryptKey));
		}

		return $this->decryptKey;
	}

	/**
	 * @param string $decryptKey
	 */
	public function setDecryptKey(string $decryptKey): void
	{
		$this->decryptKey = $decryptKey;
	}

	/**
	 * @return string|null
	 */
	public function getEncryptKey(): ?string
	{
		if (is_resource($this->encryptKey)) {
			rewind($this->encryptKey);

			return strval(stream_get_contents($this->encryptKey));
		}

		return $this->encryptKey;
	}

	/**
	 * @param string $encryptKey
	 */
	public function setEncryptKey(string $encryptKey): void
	{
		$this->encryptKey = $encryptKey;
	}

	/**
	 * @return bool
	 */
	public function isAdmin(): bool
	{
		return $this->admin;
	}

	/**
	 * @param bool $admin
	 */
	public function setAdmin(bool $admin): void
	{
		$this->admin = $admin;
	}

}
