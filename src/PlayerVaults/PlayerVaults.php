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
		$this->getLogger()->notice("Két_Sắt API 5 is enabled with the {$providerType} provider. Legacy aggregate files are read without being modified.");
	}

	public function getMaxVaults() : int{
		return $this->maxVaults;
	}

	public function getProvider() : Provider{
		return $this->provider;
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if(strtolower($command->getName()) !== "ketsat"){
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
			if(!$sender->hasPermission("ketsat.admin")){
				$sender->sendMessage(TextFormat::RED . "Bạn không có quyền dùng lệnh quản trị két sắt.");
				return true;
			}
			if(!isset($args[1]) || !$this->isValidPlayerName($args[1])){
				$sender->sendMessage(TextFormat::YELLOW . "Dùng: /{$command->getName()} of <người chơi> [1-{$this->maxVaults}]");
				return true;
			}
			$number = $this->parseVaultNumber($args[2] ?? "1");
			if($number === null){
				$sender->sendMessage(TextFormat::YELLOW . "Số két phải nằm trong khoảng 1-{$this->maxVaults}.");
				return true;
			}
			$this->openVault($sender, strtolower($args[1]), $number);
			return true;
		}

		if($subcommand === "empty"){
			if(!$sender->hasPermission("ketsat.admin")){
				$sender->sendMessage(TextFormat::RED . "Bạn không có quyền dùng lệnh quản trị két sắt.");
				return true;
			}
			if(!isset($args[1]) || !$this->isValidPlayerName($args[1]) || !isset($args[2])){
				$sender->sendMessage(TextFormat::YELLOW . "Dùng: /{$command->getName()} empty <người chơi> <1-{$this->maxVaults}|all>");
				return true;
			}
			$number = strtolower($args[2]) === "all" ? null : $this->parseVaultNumber($args[2]);
			if(strtolower($args[2]) !== "all" && $number === null){
				$sender->sendMessage(TextFormat::YELLOW . "Số két phải nằm trong khoảng 1-{$this->maxVaults}, hoặc dùng all.");
				return true;
			}
			try{
				$this->provider->deleteVaults(strtolower($args[1]), $number);
				$sender->sendMessage(TextFormat::GREEN . "Đã xóa " . ($number === null ? "tất cả két" : "két số {$number}") . " của {$args[1]}. Dữ liệu nguồn legacy vẫn được giữ nguyên.");
			}catch(\Throwable $exception){
				$this->getLogger()->error("Could not delete vault data: " . $exception->getMessage());
				$sender->sendMessage(TextFormat::RED . "Không thể cập nhật dữ liệu két sắt. Dữ liệu hiện có được giữ nguyên.");
			}
			return true;
		}

		$number = $this->parseVaultNumber($args[0]);
		if($number === null){
			$sender->sendMessage(TextFormat::YELLOW . "Dùng: /{$command->getName()} <1-{$this->maxVaults}>");
			return true;
		}
		if(!$sender instanceof Player){
			$sender->sendMessage(TextFormat::RED . "Chỉ người chơi mới có thể mở két sắt.");
			return true;
		}
		if(!$sender->hasPermission("ketsat.command")){
			$sender->sendMessage(TextFormat::RED . "Bạn không có quyền mở két sắt.");
			return true;
		}
		$this->openVault($sender, strtolower($sender->getName()), $number);
		return true;
	}

	private function openVault(CommandSender $viewer, string $owner, int $number) : void{
		if(!$viewer instanceof Player){
			$viewer->sendMessage(TextFormat::RED . "Chỉ người chơi mới có thể mở giao diện két sắt.");
			return;
		}
		try{
			if(!$this->provider->openVault($viewer, $owner, $number)){
				$viewer->sendMessage(TextFormat::RED . "Két này đang được mở trong một phiên khác hoặc không thể mở tại vị trí hiện tại.");
			}
		}catch(\Throwable $exception){
			$this->getLogger()->error("Could not open {$owner}'s vault {$number}: " . $exception->getMessage());
			$viewer->sendMessage(TextFormat::RED . "Không thể đọc dữ liệu két sắt. Dữ liệu cũ chưa bị thay đổi.");
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
		$sender->sendMessage(TextFormat::AQUA . "/{$command->getName()} <1-{$this->maxVaults}> " . TextFormat::GRAY . "- Mở két sắt của bạn");
		if($sender->hasPermission("ketsat.admin")){
			$this->sendAdminUsage($sender, $command);
		}
	}

	private function sendAdminUsage(CommandSender $sender, Command $command) : void{
		if(!$sender->hasPermission("ketsat.admin")){
			$sender->sendMessage(TextFormat::RED . "Bạn không có quyền xem lệnh quản trị két sắt.");
			return;
		}
		$sender->sendMessage(TextFormat::AQUA . "/{$command->getName()} of <người chơi> [1-{$this->maxVaults}] " . TextFormat::GRAY . "- Xem két sắt");
		$sender->sendMessage(TextFormat::AQUA . "/{$command->getName()} empty <người chơi> <1-{$this->maxVaults}|all> " . TextFormat::GRAY . "- Xóa dữ liệu két sắt");
	}
}
