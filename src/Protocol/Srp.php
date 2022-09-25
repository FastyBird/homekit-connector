<?php declare(strict_types = 1);

/**
 * Srp.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Protocol
 * @since          0.19.0
 *
 * @date           20.09.22
 */

namespace FastyBird\HomeKitConnector\Protocol;

use Brick\Math;
use FastyBird\HomeKitConnector\Exceptions;
use Nette;
use Throwable;

/**
 * Server Side SRP implementation
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Protocol
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Srp
{

	use Nette\SmartObject;

	private const N_3072 = 'FFFFFFFFFFFFFFFFC90FDAA22168C234C4C6628B80DC1CD129024E08'
		. '8A67CC74020BBEA63B139B22514A08798E3404DDEF9519B3CD3A431B'
		. '302B0A6DF25F14374FE1356D6D51C245E485B576625E7EC6F44C42E9'
		. 'A637ED6B0BFF5CB6F406B7EDEE386BFB5A899FA5AE9F24117C4B1FE6'
		. '49286651ECE45B3DC2007CB8A163BF0598DA48361C55D39A69163FA8'
		. 'FD24CF5F83655D23DCA3AD961C62F356208552BB9ED529077096966D'
		. '670C354E4ABC9804F1746C08CA18217C32905E462E36CE3BE39E772C'
		. '180E86039B2783A2EC07A28FB5C55DF06F4C52C9DE2BCBF695581718'
		. '3995497CEA956AE515D2261898FA051015728E5A8AAAC42DAD33170D'
		. '04507A33A85521ABDF1CBA64ECFB850458DBEF0A8AEA71575D060C7D'
		. 'B3970F85A6E1E4C7ABF5AE8CDB0933D71E8C94E04A25619DCEE3D226'
		. '1AD2EE6BF12FFA06D98A0864D87602733EC86A64521F2B18177B200C'
		. 'BBE117577A615D6C770988C0BAD946E208E24FA074E5AB3143DB5BFC'
		. 'E0FD108E4B82D120A93AD2CAFFFFFFFFFFFFFFFF';

	private const G = 5;

	/** @var string */
	private string $username;

	/** @var string */
	private string $salt;

	/** @var Math\BigInteger */
	private Math\BigInteger $serverPrivateKey;

	/** @var Math\BigInteger */
	private Math\BigInteger $serverPasswordVerifier;

	/** @var Math\BigInteger */
	private Math\BigInteger $serverPublicKey;

	/** @var Math\BigInteger|null */
	private ?Math\BigInteger $randomScramblingParameter = null;

	/** @var Math\BigInteger|null */
	private ?Math\BigInteger $premasterSecret = null;

	/** @var string|null */
	private ?string $sessionKey = null;

	/** @var string|null */
	private ?string $clientProof = null;

	/** @var string|null */
	private ?string $serverProof = null;

	/** @var Math\BigInteger */
	private Math\BigInteger $n3072;

	/**
	 * @param string $username
	 * @param string $password
	 * @param string|null $salt
	 * @param Math\BigInteger|null $serverPrivateKey
	 */
	public function __construct(
		string $username,
		string $password,
		?string $salt = null,
		?Math\BigInteger $serverPrivateKey = null
	) {
		$this->username = $username;
		$this->salt = $salt ?? $this->generateSalt();
		$this->serverPrivateKey = $serverPrivateKey ?? $this->generatePrivateKey();

		$this->n3072 = Math\BigInteger::fromBase(self::N_3072, 16);

		$gPadded = [];

		for ($i = 0; $i < 383; $i++) {
			$gPadded[] = 0;
		}

		$gPadded[] = 5;

		$k = Math\BigInteger::fromBytes(hash('sha512', ($this->n3072->toBytes(false) . pack('C*', ...$gPadded)), true), false);
		$x = Math\BigInteger::fromBytes(hash('sha512', ($this->salt . hash('sha512', ($username . ':' . $password), true)), true), false);

		$this->serverPasswordVerifier = Math\BigInteger::of(strval(gmp_powm((string) self::G, (string) $x, (string) $this->n3072)));
		$this->serverPublicKey = Math\BigInteger::of(strval(gmp_mod(
			gmp_add(
				gmp_mul((string) $k, (string) $this->serverPasswordVerifier),
				gmp_powm((string) self::G, (string) $this->serverPrivateKey, (string) $this->n3072)
			),
			(string) $this->n3072
		)));
	}

	/**
	 * @return string
	 */
	public function getSalt(): string
	{
		return $this->salt;
	}

	/**
	 * @return string|null
	 */
	public function getSessionKey(): ?string
	{
		return $this->sessionKey;
	}

	/**
	 * @return string|null
	 */
	public function getClientProof(): ?string
	{
		return $this->clientProof;
	}

	/**
	 * @return Math\BigInteger
	 */
	public function getServerPublicKey(): Math\BigInteger
	{
		return $this->serverPublicKey;
	}

	/**
	 * @return Math\BigInteger
	 */
	public function getServerPrivateKey(): Math\BigInteger
	{
		return $this->serverPrivateKey;
	}

	/**
	 * @return string|null
	 */
	public function getServerProof(): ?string
	{
		return $this->serverProof;
	}

	/**
	 * @return Math\BigInteger
	 */
	public function getServerPasswordVerifier(): Math\BigInteger
	{
		return $this->serverPasswordVerifier;
	}

	/**
	 * @return Math\BigInteger|null
	 */
	public function getRandomScramblingParameter(): ?Math\BigInteger
	{
		return $this->randomScramblingParameter;
	}

	/**
	 * @return Math\BigInteger|null
	 */
	public function getPremasterSecret(): ?Math\BigInteger
	{
		return $this->premasterSecret;
	}

	/**
	 * @param Math\BigInteger $clientPublicKey
	 *
	 * @return void
	 */
	public function computeSharedSessionKey(Math\BigInteger $clientPublicKey): void
	{
		$this->randomScramblingParameter = Math\BigInteger::fromBytes(
			hash('sha512', ($clientPublicKey->toBytes(false) . $this->serverPublicKey->toBytes(false)), true),
			false
		);

		$this->premasterSecret = Math\BigInteger::of(strval(gmp_powm(
			gmp_mul(
				(string) $clientPublicKey,
				gmp_powm((string) $this->serverPasswordVerifier, (string) $this->randomScramblingParameter, (string) $this->n3072)
			),
			(string) $this->serverPrivateKey,
			(string) $this->n3072
		)));

		$this->sessionKey = hash('sha512', $this->premasterSecret->toBytes(false), true);

		$gBytes = unpack('C*', hash('sha512', Math\BigInteger::of(self::G)->toBytes(false), true));
		$n3072Bytes = unpack('C*', hash('sha512', $this->n3072->toBytes(false), true));

		if ($gBytes === false || $n3072Bytes === false) {
			return;
		}

		$combined = array_slice(
			array_map(null, $gBytes, $n3072Bytes), // Zips values
			0, // Begins selection before first element
			min(array_map('count', [$gBytes, $n3072Bytes])) // Ends after shortest ends
		);

		$combined = array_map(function (array $row): int {
			return $row[0] ^ $row[1];
		}, $combined);

		$combined = pack('C*', ...$combined);

		$this->clientProof = hash(
			'sha512',
			(
				$combined
				. hash('sha512', $this->username, true)
				. $this->salt
				. $clientPublicKey->toBytes(false)
				. $this->serverPublicKey->toBytes(false)
				. $this->sessionKey
			),
			true
		);

		$this->serverProof = hash(
			'sha512',
			(
				$clientPublicKey->toBytes(false) .
				$this->clientProof .
				$this->sessionKey
			),
			true
		);
	}

	/**
	 * @param string $clientProof
	 *
	 * @return bool
	 */
	public function verifyProof(string $clientProof): bool
	{
		return $this->clientProof === $clientProof;
	}

	/**
	 * @return string
	 */
	private function generateSalt(): string
	{
		try {
			$bytes = random_bytes(16);
		} catch (Throwable) {
			return '';
		}

		$unpacked = unpack('n*', $bytes);

		if ($unpacked === false) {
			return '';
		}

		return implode('', $unpacked);
	}

	/**
	 * @return Math\BigInteger
	 */
	private function generatePrivateKey(): Math\BigInteger
	{
		try {
			$bytes = random_bytes(32);
		} catch (Throwable) {
			return Math\BigInteger::zero();
		}

		$unpacked = unpack('n*', $bytes);

		if ($unpacked === false) {
			return Math\BigInteger::zero();
		}

		return Math\BigInteger::fromBytes(implode('', $unpacked), false);
	}

	/**
	 * @param string $number
	 *
	 * @return int
	 */
	private function stringToInt(string $number): int
	{
		$number = unpack('P*', $number);

		if ($number === false) {
			throw new Exceptions\InvalidState('Error during SRP string to number transformation');
		}

		$number = array_pop($number);

		return (int) $number;
	}

}
