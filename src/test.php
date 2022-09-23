<?php

const TEST_SESSION_KEY = '5CBC219D B052138E E1148C71 CD449896 3D682549 CE91CA24 F098468F 06015BEB'
	. '6AF245C2 093F98C3 651BCA83 AB8CAB2B 580BBF02 184FEFDF 26142F73 DF95AC50';

$sessionKey = (string) hex2bin(str_replace(' ', '', TEST_SESSION_KEY));

$decryptKey = hash_hkdf(
	'sha512',
	$sessionKey,
	32,
	'Pair-Setup-Encrypt-Info',
	'Pair-Setup-Encrypt-Salt'
);

var_dump(unpack('C*', $decryptKey));
