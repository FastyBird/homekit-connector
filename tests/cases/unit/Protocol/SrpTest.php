<?php declare(strict_types = 1);

namespace FastyBird\Connector\HomeKit\Tests\Cases\Unit\Protocol;

use Brick\Math;
use Exception;
use FastyBird\Connector\HomeKit\Protocol;
use PHPUnit\Framework\TestCase;
use function hex2bin;
use function pack;
use function str_replace;
use function unpack;

final class SrpTest extends TestCase
{

	private const USERNAME = 'alice';

	private const PASSWORD = 'password123';

	private const TEST_SALT = 'BEB25379 D1A8581E B5A72767 3A2441EE';

	private const TEST_CLIENT_PUBLIC_KEY
		= 'FAB6F5D2 615D1E32 3512E799 1CC37443 F487DA60 4CA8C923 0FCB04E5 41DCE628'
		. '0B27CA46 80B0374F 179DC3BD C7553FE6 2459798C 701AD864 A91390A2 8C93B644'
		. 'ADBF9C00 745B942B 79F9012A 21B9B787 82319D83 A1F83628 66FBD6F4 6BFC0DDB'
		. '2E1AB6E4 B45A9906 B82E37F0 5D6F97F6 A3EB6E18 2079759C 4F684783 7B62321A'
		. 'C1B4FA68 641FCB4B B98DD697 A0C73641 385F4BAB 25B79358 4CC39FC8 D48D4BD8'
		. '67A9A3C1 0F8EA121 70268E34 FE3BBE6F F89998D6 0DA2F3E4 283CBEC1 393D52AF'
		. '724A5723 0C604E9F BCE583D7 613E6BFF D67596AD 121A8707 EEC46944 95703368'
		. '6A155F64 4D5C5863 B48F61BD BF19A53E AB6DAD0A 186B8C15 2E5F5D8C AD4B0EF8'
		. 'AA4EA500 8834C3CD 342E5E0F 167AD045 92CD8BD2 79639398 EF9E114D FAAAB919'
		. 'E14E8509 89224DDD 98576D79 385D2210 902E9F9B 1F2D86CF A47EE244 635465F7'
		. '1058421A 0184BE51 DD10CC9D 079E6F16 04E7AA9B 7CF7883C 7D4CE12B 06EBE160'
		. '81E23F27 A231D184 32D7D1BB 55C28AE2 1FFCF005 F57528D1 5A88881B B3BBB7FE';

	private const TEST_SERVER_PRIVATE_KEY = 'E487CB59 D31AC550 471E81F0 0F6928E0 1DDA08E9 74A004F4 9E61F5D1 05284D20';

	private const TEST_SERVER_PUBLIC_KEY
		= '40F57088 A482D4C7 733384FE 0D301FDD CA9080AD 7D4F6FDF 09A01006 C3CB6D56'
		. '2E41639A E8FA21DE 3B5DBA75 85B27558 9BDB2798 63C56280 7B2B9908 3CD1429C'
		. 'DBE89E25 BFBD7E3C AD3173B2 E3C5A0B1 74DA6D53 91E6A06E 465F037A 40062548'
		. '39A56BF7 6DA84B1C 94E0AE20 8576156F E5C140A4 BA4FFC9E 38C3B07B 88845FC6'
		. 'F7DDDA93 381FE0CA 6084C4CD 2D336E54 51C464CC B6EC65E7 D16E548A 273E8262'
		. '84AF2559 B6264274 215960FF F47BDD63 D3AFF064 D6137AF7 69661C9D 4FEE4738'
		. '2603C88E AA098058 1D077584 61B777E4 356DDA58 35198B51 FEEA308D 70F75450'
		. 'B71675C0 8C7D8302 FD7539DD 1FF2A11C B4258AA7 0D234436 AA42B6A0 615F3F91'
		. '5D55CC3B 966B2716 B36E4D1A 06CE5E5D 2EA3BEE5 A1270E87 51DA45B6 0B997B0F'
		. 'FDB0F996 2FEE4F03 BEE780BA 0A845B1D 92714217 83AE6601 A61EA2E3 42E4F2E8'
		. 'BC935A40 9EAD19F2 21BD1B74 E2964DD1 9FC845F6 0EFC0933 8B60B6B2 56D8CAC8'
		. '89CCA306 CC370A0B 18C8B886 E95DA0AF 5235FEF4 393020D2 B7F30569 04759042';

	private const TEST_SERVER_PASSWORD_VERIFIER
		= '9B5E0617 01EA7AEB 39CF6E35 19655A85 3CF94C75 CAF2555E F1FAF759 BB79CB47'
		. '7014E04A 88D68FFC 05323891 D4C205B8 DE81C2F2 03D8FAD1 B24D2C10 9737F1BE'
		. 'BBD71F91 2447C4A0 3C26B9FA D8EDB3E7 80778E30 2529ED1E E138CCFC 36D4BA31'
		. '3CC48B14 EA8C22A0 186B222E 655F2DF5 603FD75D F76B3B08 FF895006 9ADD03A7'
		. '54EE4AE8 8587CCE1 BFDE3679 4DBAE459 2B7B904F 442B041C B17AEBAD 1E3AEBE3'
		. 'CBE99DE6 5F4BB1FA 00B0E7AF 06863DB5 3B02254E C66E781E 3B62A821 2C86BEB0'
		. 'D50B5BA6 D0B478D8 C4E9BBCE C2176532 6FBD1405 8D2BBDE2 C33045F0 3873E539'
		. '48D78B79 4F0790E4 8C36AED6 E880F557 427B2FC0 6DB5E1E2 E1D7E661 AC482D18'
		. 'E528D729 5EF74372 95FF1A72 D4027717 13F16876 DD050AE5 B7AD53CC B90855C9'
		. '39566483 58ADFD96 6422F524 98732D68 D1D7FBEF 10D78034 AB8DCB6F 0FCF885C'
		. 'C2B2EA2C 3E6AC866 09EA058A 9DA8CC63 531DC915 414DF568 B09482DD AC1954DE'
		. 'C7EB714F 6FF7D44C D5B86F6B D1158109 30637C01 D0F6013B C9740FA2 C633BA89';

	private const TEST_RANDOM_SCRAMBLING_PARAMETER
		= '03AE5F3C 3FA9EFF1 A50D7DBB 8D2F60A1 EA66EA71 2D50AE97 6EE34641 A1CD0E51'
		. 'C4683DA3 83E8595D 6CB56A15 D5FBC754 3E07FBDD D316217E 01A391A1 8EF06DFF';

	private const TEST_PREMASTER_SECRET
		= 'F1036FEC D017C823 9C0D5AF7 E0FCF0D4 08B009E3 6411618A 60B23AAB BFC38339'
		. '72682312 14BAACDC 94CA1C53 F442FB51 C1B027C3 18AE238E 16414D60 D1881B66'
		. '486ADE10 ED02BA33 D098F6CE 9BCF1BB0 C46CA2C4 7F2F174C 59A9C61E 2560899B'
		. '83EF6113 1E6FB30B 714F4E43 B735C9FE 6080477C 1B83E409 3E4D456B 9BCA492C'
		. 'F9339D45 BC42E67C E6C02C24 3E49F5DA 42A869EC 855780E8 4207B8A1 EA6501C4'
		. '78AAC0DF D3D22614 F531A00D 826B7954 AE8B14A9 85A42931 5E6DD366 4CF47181'
		. '496A9432 9CDE8005 CAE63C2F 9CA4969B FE840019 24037C44 6559BDBB 9DB9D4DD'
		. '142FBCD7 5EEF2E16 2C843065 D99E8F05 762C4DB7 ABD9DB20 3D41AC85 A58C05BD'
		. '4E2DBF82 2A934523 D54E0653 D376CE8B 56DCB452 7DDDC1B9 94DC7509 463A7468'
		. 'D7F02B1B EB168571 4CE1DD1E 71808A13 7F788847 B7C6B7BF A1364474 B3B7E894'
		. '78954F6A 8E68D45B 85A88E4E BFEC1336 8EC0891C 3BC86CF5 00978801 78D86135'
		. 'E7287234 58538858 D715B7B2 47406222 C1019F53 603F0169 52D49710 0858824C';

	private const TEST_SESSION_KEY
		= '5CBC219D B052138E E1148C71 CD449896 3D682549 CE91CA24 F098468F 06015BEB'
		. '6AF245C2 093F98C3 651BCA83 AB8CAB2B 580BBF02 184FEFDF 26142F73 DF95AC50';

	private const TEST_CLIENT_PROOF_OF_SESSION_KEY
		= '5F7C14AB 57ED0E94 FD1D78C6 B4DD09ED 7E340B7E 05D419A9 FD760F6B 35E523D1'
		. '310777A1 AE1D2826 F596F3A8 5116CC45 7C7C964D 4F44DED5 559DA818 C88B617F';

	private const TEST_SERVER_PROOF_OF_SESSION_KEY
		= '2FA0E81F 5CB73B88 FA096427 0F321DD6 41F2227A 5D805C40 F1BFE96A AF6A19FF'
		. 'CE8E2328 7965A39E AB9D5A02 215F89E1 28177ED2 C4F103E6 55A04553 1BCBF7AD';

	private Protocol\Srp $srp;

	/**
	 * @throws Exception
	 */
	public function setUp(): void
	{
		parent::setUp();

		$salt = pack('C*', ...$this->hex2ByteArray(self::TEST_SALT));
		$serverPrivateKey = Math\BigInteger::fromBytes(
			(string) hex2bin(str_replace(' ', '', self::TEST_SERVER_PRIVATE_KEY)),
			false,
		);

		$this->srp = new Protocol\Srp(
			self::USERNAME,
			self::PASSWORD,
			$salt,
			$serverPrivateKey,
		);

		$clientPublicKey = Math\BigInteger::fromBytes(
			(string) hex2bin(str_replace(' ', '', self::TEST_CLIENT_PUBLIC_KEY)),
			false,
		);

		$this->srp->computeSharedSessionKey($clientPublicKey);
	}

	/**
	 * @throws Math\Exception\NumberFormatException
	 */
	public function testServerPasswordVerifier(): void
	{
		$serverPasswordVerifier = Math\BigInteger::fromBytes(
			(string) hex2bin(str_replace(' ', '', self::TEST_SERVER_PASSWORD_VERIFIER)),
			false,
		);

		self::assertEquals(
			(string) $serverPasswordVerifier,
			(string) $this->srp->getServerPasswordVerifier(),
		);
	}

	/**
	 * @throws Math\Exception\NumberFormatException
	 */
	public function testServerPublicKey(): void
	{
		$serverPublicKey = Math\BigInteger::fromBytes(
			(string) hex2bin(str_replace(' ', '', self::TEST_SERVER_PUBLIC_KEY)),
			false,
		);

		self::assertEquals(
			(string) $serverPublicKey,
			(string) $this->srp->getServerPublicKey(),
		);
	}

	/**
	 * @throws Math\Exception\NumberFormatException
	 */
	public function testRandomScramblingParameter(): void
	{
		$randomScramblingParameter = Math\BigInteger::fromBytes(
			(string) hex2bin(str_replace(' ', '', self::TEST_RANDOM_SCRAMBLING_PARAMETER)),
			false,
		);

		self::assertNotNull($this->srp->getRandomScramblingParameter());
		self::assertEquals(
			(string) $randomScramblingParameter,
			(string) $this->srp->getRandomScramblingParameter(),
		);
	}

	/**
	 * @throws Math\Exception\NumberFormatException
	 */
	public function testPremasteredSecret(): void
	{
		$premasteredSecret = Math\BigInteger::fromBytes(
			(string) hex2bin(str_replace(' ', '', self::TEST_PREMASTER_SECRET)),
			false,
		);

		self::assertNotNull($this->srp->getPremasterSecret());
		self::assertEquals(
			(string) $premasteredSecret,
			(string) $this->srp->getPremasterSecret(),
		);
	}

	public function testSessionKey(): void
	{
		self::assertNotNull($this->srp->getSessionKey());
		self::assertEquals(
			(string) hex2bin(str_replace(' ', '', self::TEST_SESSION_KEY)),
			$this->srp->getSessionKey(),
		);
	}

	public function testSessionKeyClientProof(): void
	{
		self::assertNotNull($this->srp->getClientProof());
		self::assertEquals(
			(string) hex2bin(str_replace(' ', '', self::TEST_CLIENT_PROOF_OF_SESSION_KEY)),
			$this->srp->getClientProof(),
		);
	}

	public function testSessionKeyServerProof(): void
	{
		self::assertNotNull($this->srp->getServerProof());
		self::assertEquals(
			(string) hex2bin(str_replace(' ', '', self::TEST_SERVER_PROOF_OF_SESSION_KEY)),
			$this->srp->getServerProof(),
		);
	}

	public function testSalt(): void
	{
		self::assertEquals(
			(string) hex2bin(str_replace(' ', '', self::TEST_SALT)),
			$this->srp->getSalt(),
		);
	}

	public function testVerifyProof(): void
	{
		self::assertTrue(
			$this->srp->verifyProof(
				(string) hex2bin(str_replace(' ', '', self::TEST_CLIENT_PROOF_OF_SESSION_KEY)),
			),
		);
	}

	/**
	 * @return array<int>
	 *
	 * @throws Exception
	 */
	private function hex2ByteArray(string $hexString): array
	{
		$string = hex2bin(str_replace(' ', '', $hexString));

		$converted = unpack('C*', ((string) $string));

		if ($converted === false) {
			throw new Exception('Could not convert input to bytearray');
		}

		return $converted;
	}

}
