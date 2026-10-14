<?php

declare(strict_types=1);

use PlayerVaults\VaultDataCodec;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\TreeRoot;

$autoload = getenv("AXOLOTL_AUTOLOAD");
if(!is_string($autoload) || !is_file($autoload)){
	fwrite(STDERR, "Set AXOLOTL_AUTOLOAD to an Axolotl-PM vendor/autoload.php file.\n");
	exit(2);
}
require $autoload;
require dirname(__DIR__) . "/src/PlayerVaults/VaultDataCodec.php";

$diamond = VanillaItems::DIAMOND();
$iron = VanillaItems::IRON_INGOT()->setCount(12);
$roundTrip = VaultDataCodec::decode(VaultDataCodec::encode([3 => $diamond, 26 => $iron]));
if(!isset($roundTrip[3], $roundTrip[26]) || !$diamond->equals($roundTrip[3], true, true) || !$iron->equals($roundTrip[26], true, true)){
	fwrite(STDERR, "New vault payload did not preserve item identities, counts and slots.\n");
	exit(1);
}

// Historical payloads stored an ItemList without slot tags. Keep the old list index fallback.
$legacyRoot = CompoundTag::create()->setTag("ItemList", new ListTag([$diamond->nbtSerialize()], NBT::TAG_Compound));
$legacyBytes = (new BigEndianNbtSerializer())->write(new TreeRoot($legacyRoot, "Items"));
$legacyPayload = base64_encode(gzcompress($legacyBytes));
$legacyRoundTrip = VaultDataCodec::decode($legacyPayload);
if(!isset($legacyRoundTrip[0]) || !$diamond->equals($legacyRoundTrip[0], true, true)){
	fwrite(STDERR, "Legacy vault payload did not retain its item.\n");
	exit(1);
}

try{
	VaultDataCodec::decode("not base64!");
	fwrite(STDERR, "Corrupt payload was accepted.\n");
	exit(1);
}catch(UnexpectedValueException){
}

fwrite(STDOUT, "Vault data codec checks passed.\n");
