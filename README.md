# Két_Sắt

Két_Sắt is a personal item-vault plugin ported to Axolotl-PM API 5.0.0. It opens a 27-slot chest interface for a numbered vault and stores each player's vaults in JSON or YAML files.

## Compatibility

The plugin manifest requires API 5.0.0. CI runs PHPStan at maximum level and builds the PHAR against pinned Axolotl-PM API 5.0.0 and 5.49.1 sources. CI also performs an isolated server startup smoke test on 5.49.1. These checks do not claim that every API 5.x patch has been tested, and a connected-player deposit/withdraw test has not been recorded.

The build workflow uses [DevTools](https://github.com/NhanAZ/DevTools) to build and validate the PHAR.

## Commands

- `/ketsat <number>` opens one of your vaults.
- `/ketsat of <player> [number]` opens a player's vault; requires `ketsat.admin`.
- `/ketsat empty <player> <number|all>` deletes vault data; requires `ketsat.admin`.

## Storage and migration

The supported providers are JSON and YAML. Each player gets a separate file under the plugin data folder. Existing aggregate `vaults.json` and `vaults.yml` files are read as a fallback and are never renamed or overwritten automatically. After a vault is saved or deleted, that player's separate file takes precedence; keep a backup of all existing vault files before deploying the port.

The historical MySQL implementation contained incomplete query strings and did not provide a verifiable database schema. Selecting `mysql` therefore disables the plugin with an error instead of connecting or claiming to preserve database data. Export any required SQL data with a separately verified migration before switching to JSON or YAML.

## Attribution and license

This source is derived from [Muqsit's PlayerVaults](https://github.com/Muqsit/PlayerVaults/tree/b7fc7fec73522837868e13c86a9b09343d9dc6a9), whose v2.0 source is distributed under GNU Lesser General Public License v3.0 or later. The original and edit attributions recorded in the historical manifest/config are preserved in the plugin metadata. This API 5 port and its changes are covered by the included [LGPL-3.0-or-later license](LICENSE).
