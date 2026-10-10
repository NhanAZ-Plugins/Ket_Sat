# Két_Sắt

Két_Sắt is a historical PocketMine-MP player vault plugin derived from PlayerVaults. Its manifest records an unknown original author and an edit by Ghast Noob.

## Compatibility and data safety

The manifest lists PocketMine-MP API 2 and early 3 versions. The source uses legacy tile, inventory and asynchronous task APIs. It has not been ported or tested on Axolotl-PM 5. Do not install it on a current server or point it at existing player vault data.

The plugin can use JSON, YAML or MySQL storage and contains migration code for older vault files. `resources/config.yml` includes example MySQL credentials in plain text. Replace those values before any isolated test, and keep real vault files and databases outside the test environment. A port needs backup, migration, permission and failure tests before accessing player items.

There is no LICENSE file, CI workflow, DevTools PHAR, Axolotl-PM 5 runtime test or verified current release. Preserve the PlayerVaults attribution and resolve redistribution rights before publishing a modified build.

For questions about the NhanAZ ecosystem, use [NhanAZ Discord](https://discord.gg/j2X83ujT6c).
