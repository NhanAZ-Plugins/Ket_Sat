<?php

declare(strict_types=1);

namespace PlayerVaults;

use PlayerVaults\Vault\VaultInventory;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\tile\Spawnable;
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\world\Position;
use pocketmine\world\World;

final class Provider{
	public const JSON = "json";
	public const YAML = "yaml";

	private int $configType;
	private string $storageDirectory;
	/** @var array<string, VaultInventory> */
	private array $openVaults = [];

	public function __construct(private PlayerVaults $plugin, private string $type){
		$this->configType = match($type){
			self::JSON => Config::JSON,
			self::YAML => Config::YAML,
			default => throw new \InvalidArgumentException("Unsupported vault provider: {$type}")
		};
		$this->storageDirectory = $plugin->getDataFolder() . "vaults" . DIRECTORY_SEPARATOR;
		if(!is_dir($this->storageDirectory) && !mkdir($this->storageDirectory, 0775, true) && !is_dir($this->storageDirectory)){
			throw new \RuntimeException("Could not create the vault storage directory");
		}
	}

	public function getPlugin() : PlayerVaults{
		return $this->plugin;
	}

	public function openVault(Player $viewer, string $owner, int $number) : bool{
		$key = $this->key($owner, $number);
		if(isset($this->openVaults[$key])){
			if(count($this->openVaults[$key]->getViewers()) > 0){
				return false;
			}
			unset($this->openVaults[$key]);
		}

		$vaultData = $this->loadPlayerVaultData($owner);
		$encoded = $vaultData[$number] ?? "";
		$contents = $encoded === "" ? [] : VaultDataCodec::decode($encoded);

		$world = $viewer->getWorld();
		$position = $viewer->getPosition();
		$x = $position->getFloorX();
		$y = $position->getFloorY() + 2;
		$z = $position->getFloorZ();
		if($y < World::Y_MIN || $y >= $world->getMaxY()){
			return false;
		}

		$holder = new Position($x, $y, $z, $world);
		$titleSetting = $this->plugin->getConfig()->get("vaultinv-name", "Vault {VAULTNO}");
		$titleTemplate = is_string($titleSetting) ? $titleSetting : "Vault {VAULTNO}";
		$title = str_replace("{VAULTNO}", (string) $number, $titleTemplate);
		$inventory = new VaultInventory($holder, $this, $owner, $number);
		$inventory->setContents($contents);
		$this->showFakeChest($viewer, $holder, $title);
		$this->openVaults[$key] = $inventory;
		if(!$viewer->setCurrentWindow($inventory)){
			$this->restoreBlock($viewer, $holder);
			unset($this->openVaults[$key]);
			return false;
		}
		return true;
	}

	/** @param array<int, \pocketmine\item\Item> $contents */
	public function saveClosedVault(VaultInventory $inventory, string $owner, int $number, array $contents) : void{
		$key = $this->key($owner, $number);
		try{
			$vaultData = $this->loadPlayerVaultData($owner);
			$vaultData[$number] = VaultDataCodec::encode($contents);
			$this->savePlayerVaultData($owner, $vaultData);
		}finally{
			if(($this->openVaults[$key] ?? null) === $inventory){
				unset($this->openVaults[$key]);
			}
		}
	}

	public function restoreBlock(Player $player, Position $position) : void{
		if(!$position->isValid()){
			return;
		}
		$world = $position->getWorld();
		$x = $position->getFloorX();
		$y = $position->getFloorY();
		$z = $position->getFloorZ();
		$blockPosition = BlockPosition::fromVector3($position);
		$block = $world->getBlockAt($x, $y, $z);
		$translator = $player->getNetworkSession()->getTypeConverter()->getBlockTranslator();
		$networkId = $translator->internalIdToNetworkId($block->getStateId());
		$player->getNetworkSession()->sendDataPacket(UpdateBlockPacket::create(
			$blockPosition,
			$networkId,
			UpdateBlockPacket::FLAG_NETWORK,
			UpdateBlockPacket::DATA_LAYER_NORMAL
		));

		$tile = $world->getTileAt($x, $y, $z);
		if($tile instanceof Spawnable){
			$player->getNetworkSession()->sendDataPacket(BlockActorDataPacket::create($blockPosition, $tile->getSerializedSpawnCompound()));
		}
	}

	private function showFakeChest(Player $player, Position $position, string $title) : void{
		$blockPosition = BlockPosition::fromVector3($position);
		$chestNetworkId = $player->getNetworkSession()->getTypeConverter()->getBlockTranslator()->internalIdToNetworkId(VanillaBlocks::CHEST()->getStateId());
		$player->getNetworkSession()->sendDataPacket(UpdateBlockPacket::create(
			$blockPosition,
			$chestNetworkId,
			UpdateBlockPacket::FLAG_NETWORK,
			UpdateBlockPacket::DATA_LAYER_NORMAL
		));
		$chestTag = \pocketmine\nbt\tag\CompoundTag::create()
			->setString("id", "Chest")
			->setInt("x", $position->getFloorX())
			->setInt("y", $position->getFloorY())
			->setInt("z", $position->getFloorZ())
			->setString("CustomName", $title);
		$player->getNetworkSession()->sendDataPacket(BlockActorDataPacket::create($blockPosition, new CacheableNbt($chestTag)));
	}

	public function deleteVaults(string $owner, ?int $number) : void{
		if($number === null){
			foreach($this->openVaults as $key => $_inventory){
				if(str_starts_with($key, strtolower($owner) . ":")){
					throw new \RuntimeException("Close the player's open vault before deleting all vault data");
				}
			}
			$this->savePlayerVaultData($owner, []);
			return;
		}

		$key = $this->key($owner, $number);
		if(isset($this->openVaults[$key])){
			throw new \RuntimeException("Close the vault before deleting its data");
		}
		$vaultData = $this->loadPlayerVaultData($owner);
		unset($vaultData[$number]);
		$this->savePlayerVaultData($owner, $vaultData);
	}

	/** @return array<int|string, string> */
	private function loadPlayerVaultData(string $owner) : array{
		$owner = $this->validateOwner($owner);
		$playerFiles = [
			$this->storageDirectory . $owner . "." . $this->type => $this->configType,
			$this->storageDirectory . $owner . ($this->type === self::JSON ? ".yml" : ".json") => $this->type === self::JSON ? Config::YAML : Config::JSON
		];
		foreach($playerFiles as $playerPath => $format){
			if(is_file($playerPath)){
				return $this->normaliseVaultData((new Config($playerPath, $format))->getAll());
			}
		}

		foreach([["json", Config::JSON], ["yml", Config::YAML]] as [$extension, $format]){
			$legacyPath = $this->plugin->getDataFolder() . "vaults." . $extension;
			if(!is_file($legacyPath)){
				continue;
			}
			/** @var array<array-key, mixed> $legacy */
			$legacy = (new Config($legacyPath, $format))->getAll();
			foreach($legacy as $legacyOwner => $vaultData){
				if(is_string($legacyOwner) && strtolower($legacyOwner) === $owner){
					if(!is_array($vaultData)){
						throw new \UnexpectedValueException("Legacy vault data for {$owner} is not a mapping");
					}
					return $this->normaliseVaultData($vaultData);
				}
			}
		}
		return [];
	}

	/** @param array<array-key, mixed> $vaultData
	 *  @return array<int|string, string>
	 */
	private function normaliseVaultData(array $vaultData) : array{
		$result = [];
		foreach($vaultData as $number => $contents){
			if(!preg_match('/^[1-9][0-9]*$/', (string) $number) || !is_string($contents)){
				throw new \UnexpectedValueException("Vault data has an invalid slot or payload; refusing to overwrite it");
			}
			$result[(string) $number] = $contents;
		}
		return $result;
	}

	/** @param array<int|string, string> $vaultData */
	private function savePlayerVaultData(string $owner, array $vaultData) : void{
		$owner = $this->validateOwner($owner);
		$path = $this->storageDirectory . $owner . "." . $this->type;
		$config = new Config($path, $this->configType, []);
		$config->setAll([]);
		foreach($vaultData as $number => $contents){
			$config->set((string) $number, $contents);
		}
		$config->save();
	}

	private function validateOwner(string $owner) : string{
		$owner = strtolower($owner);
		if(preg_match('/^[a-z0-9_]{1,16}$/', $owner) !== 1){
			throw new \InvalidArgumentException("Invalid Minecraft player name");
		}
		return $owner;
	}

	private function key(string $owner, int $number) : string{
		return $this->validateOwner($owner) . ":" . $number;
	}
}
