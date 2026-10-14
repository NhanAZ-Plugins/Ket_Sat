<?php

declare(strict_types=1);

namespace PlayerVaults\Vault;

use PlayerVaults\Provider;
use pocketmine\block\inventory\ChestInventory;
use pocketmine\player\Player;
use pocketmine\world\Position;

final class VaultInventory extends ChestInventory{
	public function __construct(
		Position $holder,
		private Provider $provider,
		private string $owner,
		private int $number
	){
		parent::__construct($holder);
	}

	public function onClose(Player $who) : void{
		parent::onClose($who);
		try{
			$this->provider->saveClosedVault($this, $this->owner, $this->number, $this->getContents());
		}catch(\Throwable $exception){
			$this->provider->getPlugin()->getLogger()->error("Could not save {$this->owner}'s vault {$this->number}: " . $exception->getMessage());
			$who->sendMessage("§cKét sắt chưa lưu được. Dữ liệu nguồn cũ vẫn được giữ nguyên.");
		}finally{
			$this->provider->restoreBlock($who, $this->getHolder());
		}
	}

	protected function animateBlock(bool $isOpen) : void{
		// The chest is a client-only block; do not broadcast world block events.
	}
}
