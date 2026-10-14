<?php

declare(strict_types=1);

namespace PlayerVaults;

use pocketmine\item\Item;
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\TreeRoot;

/** Preserves the base64 + zlib + big-endian NBT vault payload used by the legacy plugin. */
final class VaultDataCodec{
	/** @param array<int, Item> $contents */
	public static function encode(array $contents) : string{
		$items = [];
		foreach($contents as $slot => $item){
			if($slot < 0 || $slot >= 27){
				throw new \UnexpectedValueException("Vault item slot {$slot} is outside the 27-slot inventory");
			}
			if(!$item->isNull()){
				$items[] = $item->nbtSerialize($slot);
			}
		}
		$root = CompoundTag::create()->setTag("ItemList", new ListTag($items, NBT::TAG_Compound));
		$binary = (new BigEndianNbtSerializer())->write(new TreeRoot($root, "Items"));
		$compressed = gzcompress($binary);
		if($compressed === false){
			throw new \RuntimeException("Could not compress vault item data");
		}
		return base64_encode($compressed);
	}

	/** @return array<int, Item> */
	public static function decode(string $encoded) : array{
		$compressed = base64_decode($encoded, true);
		if($compressed === false){
			throw new \UnexpectedValueException("Vault payload is not valid base64");
		}
		$binary = gzuncompress($compressed);
		if($binary === false){
			throw new \UnexpectedValueException("Vault payload is not valid zlib data");
		}

		$root = (new BigEndianNbtSerializer())->read($binary)->mustGetCompoundTag();
		$itemList = $root->getListTag("ItemList");
		if($itemList === null){
			return [];
		}

		$contents = [];
		foreach($itemList->getValue() as $index => $itemTag){
			if(!$itemTag instanceof CompoundTag){
				throw new \UnexpectedValueException("Vault item data contains a non-compound list entry");
			}
			$slotTag = $itemTag->getTag("Slot");
			$slot = match(true){
				$slotTag instanceof ByteTag, $slotTag instanceof IntTag => $slotTag->getValue(),
				default => $index
			};
			if($slot < 0 || $slot >= 27){
				throw new \UnexpectedValueException("Vault item data contains invalid slot {$slot}");
			}
			$contents[$slot] = Item::nbtDeserialize($itemTag);
		}
		return $contents;
	}
}
