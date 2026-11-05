<?php

declare(strict_types=1);

namespace PlayerVaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

final class PlayerVaults extends PluginBase{
	private Provider $provider;
	private int $maxVaults;

	protected function onEnable() : void{
		$this->saveDefaultConfig();
		$config = $this->getConfig();

		$providerValue = $config->get("provider", "json");
		if(!is_string($providerValue)){
			$this->getLogger()->critical("The provider setting must be json or yaml.");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}
		$providerType = strtolower($providerValue);
		if(!in_array($providerType, [Provider::JSON, Provider::YAML], true)){
			$this->getLogger()->critical("The configured storage provider is unsupported. Use json or yaml; legacy MySQL storage is disabled because its original queries are incomplete.");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		$maxVaults = $config->get("max-vaults", 25);
		if(!is_int($maxVaults) || $maxVaults < 1 || $maxVaults > 1000){
			$this->getLogger()->critical("The max-vaults setting must be an integer from 1 to 1000.");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		$this->maxVaults = $maxVaults;
		$this->provider = new Provider($this, $providerType);
		$this->getLogger()->notice("PlayerVaults is enabled with the {$providerType} provider. Legacy aggregate files are read without being modified.");
	}

	public function getMaxVaults() : int{
		return $this->maxVaults;
	}

	public function getProvider() : Provider{
		return $this->provider;
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if(!in_array(strtolower($command->getName()), ["playervaults", "ketsat"], true)){
			return false;
		}

		if(!isset($args[0]) || strtolower($args[0]) === "help"){
			$this->sendUsage($sender, $command);
			return true;
		}

		$subcommand = strtolower($args[0]);
		if($subcommand === "admin"){
			$this->sendAdminUsage($sender, $command);
			return true;
		}

		if($subcommand === "of"){
			if(!$this->hasAdminPermission($sender)){
				$sender->sendMessage(TextFormat::RED . "You do not have permission to manage vaults.");
				return true;
			}
			if(!isset($args[1]) || !$this->isValidPlayerName($args[1])){
				$sender->sendMessage(TextFormat::YELLOW . "Usage: /{$command->getName()} of <player> [1-{$this->maxVaults}]");
				return true;
			}
			$number = $this->parseVaultNumber($args[2] ?? "1");
			if($number === null){
				$sender->sendMessage(TextFormat::YELLOW . "The vault number must be between 1 and {$this->maxVaults}.");
				return true;
			}
			$this->openVault($sender, strtolower($args[1]), $number);
			return true;
		}

		if($subcommand === "empty"){
			if(!$this->hasAdminPermission($sender)){
				$sender->sendMessage(TextFormat::RED . "You do not have permission to manage vaults.");
				return true;
			}
			if(!isset($args[1]) || !$this->isValidPlayerName($args[1]) || !isset($args[2])){
				$sender->sendMessage(TextFormat::YELLOW . "Usage: /{$command->getName()} empty <player> <1-{$this->maxVaults}|all>");
				return true;
			}
			$number = strtolower($args[2]) === "all" ? null : $this->parseVaultNumber($args[2]);
			if(strtolower($args[2]) !== "all" && $number === null){
				$sender->sendMessage(TextFormat::YELLOW . "The vault number must be between 1 and {$this->maxVaults}, or use all.");
				return true;
			}
			try{
				$this->provider->deleteVaults(strtolower($args[1]), $number);
				$sender->sendMessage(TextFormat::GREEN . "Deleted " . ($number === null ? "all vaults" : "vault {$number}") . " for {$args[1]}. Legacy data was preserved.");
			}catch(\Throwable $exception){
				$this->getLogger()->error("Could not delete vault data: " . $exception->getMessage());
				$sender->sendMessage(TextFormat::RED . "Could not update vault data. Existing data was preserved.");
			}
			return true;
		}

		$number = $this->parseVaultNumber($args[0]);
		if($number === null){
			$sender->sendMessage(TextFormat::YELLOW . "Usage: /{$command->getName()} <1-{$this->maxVaults}>");
			return true;
		}
		if(!$sender instanceof Player){
			$sender->sendMessage(TextFormat::RED . "Only players can open vaults.");
			return true;
		}
		if(!$this->hasCommandPermission($sender)){
			$sender->sendMessage(TextFormat::RED . "You do not have permission to open vaults.");
			return true;
		}
		$this->openVault($sender, strtolower($sender->getName()), $number);
		return true;
	}

	private function openVault(CommandSender $viewer, string $owner, int $number) : void{
		if(!$viewer instanceof Player){
			$viewer->sendMessage(TextFormat::RED . "Only players can open vault interfaces.");
			return;
		}
		try{
			if(!$this->provider->openVault($viewer, $owner, $number)){
				$viewer->sendMessage(TextFormat::RED . "That vault is already open or cannot be opened here.");
			}
		}catch(\Throwable $exception){
			$this->getLogger()->error("Could not open {$owner}'s vault {$number}: " . $exception->getMessage());
			$viewer->sendMessage(TextFormat::RED . "Could not read vault data. Existing data was not changed.");
		}
	}

	private function parseVaultNumber(string $value) : ?int{
		if(!preg_match('/^[1-9][0-9]*$/', $value)){
			return null;
		}
		$number = (int) $value;
		return $number <= $this->maxVaults ? $number : null;
	}

	private function isValidPlayerName(string $name) : bool{
		return preg_match('/^[A-Za-z0-9_]{1,16}$/', $name) === 1;
	}

	private function sendUsage(CommandSender $sender, Command $command) : void{
		$sender->sendMessage(TextFormat::AQUA . "/{$command->getName()} <1-{$this->maxVaults}> " . TextFormat::GRAY . "- Open one of your vaults");
		if($this->hasAdminPermission($sender)){
			$this->sendAdminUsage($sender, $command);
		}
	}

	private function sendAdminUsage(CommandSender $sender, Command $command) : void{
		if(!$this->hasAdminPermission($sender)){
			$sender->sendMessage(TextFormat::RED . "You do not have permission to view admin vault commands.");
			return;
		}
		$sender->sendMessage(TextFormat::AQUA . "/{$command->getName()} of <player> [1-{$this->maxVaults}] " . TextFormat::GRAY . "- Open a player's vault");
		$sender->sendMessage(TextFormat::AQUA . "/{$command->getName()} empty <player> <1-{$this->maxVaults}|all> " . TextFormat::GRAY . "- Delete vault data");
	}

	private function hasCommandPermission(CommandSender $sender) : bool{
		return $sender->hasPermission("playervaults.command") || $sender->hasPermission("ketsat.command");
	}

	private function hasAdminPermission(CommandSender $sender) : bool{
		return $sender->hasPermission("playervaults.admin") || $sender->hasPermission("ketsat.admin");
	}
}
