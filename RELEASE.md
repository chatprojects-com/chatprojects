# Releasing ChatProjects (Free)

How a Free release goes from a branch to WordPress.org, and the traps the 1.3.0 release walked into. Pro has its own `RELEASE.md` in its repository; the two products share data, so read [CONTRACT.md](CONTRACT.md) first.

## Order with Pro

If a release touches anything in `CONTRACT.md` (shared tables, options, key format, model catalogue), Pro ships first or together:

1. Pro on demo-pro, then the store (product 1982), then app.chatprojects.com.
2. Free on WordPress.org.
3. The store copy of Free (product 1912).

Releases that don't touch the contract ship independently.

## 1. Branch and version

* Work on `release/x.y.z`, branched from `main`.
* Bump the version in three places, which `svn-prepare.sh` checks agree: the plugin header `Version:`, `CHATPROJECTS_VERSION` in `chatprojects.php`, and `Stable tag:` in `readme.txt`.
* Add a `= x.y.z =` changelog entry and an upgrade notice.
* If the database changes, bump `Installer::DB_VERSION`. Free's schema version lives in `chatprojects_free_db_version`, never in the legacy `chatprojects_db_version`.

## 2. Gates

All of these must pass on the code you are about to ship.

| Gate | How | Pass |
|---|---|---|
| Syntax | `php -l` on every PHP file, PHP 8.0 to 8.5 | no errors |
| Contract | `php bin/contract-check.php --free=. --pro=<pro checkout>` | "Contract check passed." |
| Plugin Check | `wp plugin check chatprojects` on the **built zip's contents** | 0 errors |
| Browser sweep | local test site with the mock AI provider, plugin replaced by the **built zip's contents** | all checks pass |
| Upgrade matrix | MariaDB matrix: upgrades from the WP.org version and the previous release, Free↔Pro switches, co-install in both orders, uninstall with the other product present | all checks pass |

The browser sweep and upgrade matrix currently live outside this repository, on the release machine (`~/.cache/cp-upgrade-matrix`). Run the matrix with `./run.sh`; add `PRO_SRC=pro-zip` to test against a Pro build zip.

## 3. Build and carry the checksum

```
./build-zip.sh /path/to/chatprojects-x.y.z.zip
sha256sum /path/to/chatprojects-x.y.z.zip
```

Record the sha256 of the exact file you ship and carry it through every step: the store attachment, the release notes, and the WordPress.org comparison below. Version strings can't tell two builds apart; the checksum can. Zip checksums change on every rebuild (file timestamps) even when the contents are identical, so to compare two builds, unzip both and use `diff -r`.

## 4. Ship

1. **Demo site.** Deploy to demo-free.chatprojects.com with the local deploy script (kept out of this repository because it contains server logins). Check the upgrade ran (`chatprojects_free_db_version`), API keys still decrypt, and the debug log is clean.
2. **WordPress.org trunk.** Run `./svn-prepare.sh`. It builds the zip, checks the versions agree, syncs `trunk` in the SVN working copy (default `~/svn/chatprojects`) and stages added and removed files. It never commits. A maintainer with WordPress.org SVN credentials then runs the two printed commands. Commit `assets/` (screenshots, banners) in the same commit as trunk if they changed:
   ```
   svn commit ~/svn/chatprojects/trunk ~/svn/chatprojects/assets -m "Release x.y.z" --username <wporg user>
   svn copy https://plugins.svn.wordpress.org/chatprojects/trunk https://plugins.svn.wordpress.org/chatprojects/tags/x.y.z -m "Tag x.y.z" --username <wporg user>
   ```
3. **Verify WordPress.org.**
   * `svn ls https://plugins.svn.wordpress.org/chatprojects/tags/` lists `x.y.z/`.
   * `https://downloads.wordpress.org/plugin/chatprojects.x.y.z.zip` returns 200.
   * Unzip that download and `diff -r` it against your build: they must be identical.
4. **Store product 1912.** Pro's maintainer updates it, with the owner's approval, using the same build and checksum.
5. **GitHub.** Open a pull request from `release/x.y.z` into `main`; the owner merges it.

## 5. After release

* Watch the WordPress.org support forum and reviews for a week.
* Keep a `release/x.y.(z+1)` branch ready for a hotfix.

## Traps from 1.3.0

Each of these passed review or a check at least once while being wrong.

**Release process**
* **Forgetting the tag.** If `tags/x.y.z` doesn't exist, WordPress.org serves trunk as the stable version, and every later trunk commit goes live immediately. Always verify the tag.
* **The directory reads the tag's readme**, not trunk's. A readme-only fix after tagging goes to both `trunk/readme.txt` and `tags/x.y.z/readme.txt`.
* **More than 5 readme tags** are silently ignored. The warning is only shown to committers on the plugin page.
* **Testing the development folder.** Plugin Check reports hidden and application files from the dev folder that never ship, and misses problems that only exist in the build. Test the build.
* **Stale screenshots.** Re-shoot them every release. The 1.3.0 shoot found the upload panel advertising file types the plugin refuses.
* **Locked features.** A limit that only Pro lifts (the old 100-post Auto-RAG cap) is restricted functionality under WordPress.org guideline 5. Free must be fully usable on its own.

**WordPress behaviour**
* **Adding `type="module"` by editing the whole script tag string** also hit the inline "before" script. Use `WP_HTML_Tag_Processor` and change only tags with `src`.
* **`allowed_options` at priority 10** runs before core fills in the option list, so saving one settings tab wiped the others. Use priority 20.
* **An action added to `plugins_loaded` at the priority that is currently running never runs.** `plugins_loaded` fires once per request, so an upgrade hooked that way never happens.
* **Code that runs on `plugins_loaded` (upgrades, migrations) must not call `__()` or touch taxonomies.** WordPress 6.7+ logs a notice for early translations. Queue that work for `init`.
* **WordPress sorts `active_plugins` alphabetically on activation**, so `chatprojects-pro/` always loads before `chatprojects/` after a normal activation.
* **A plugin activated while the other product is loaded never registers its activation hook**, because it stands down in that request. `Installer::complete_activation()` therefore runs on `init` for a site where Free was never installed.
* **`dbDelta()` never changes an existing column default to `NULL`**: it only compares quoted defaults. Contract changes to defaults don't reach old installs, so code must not rely on column defaults.

**Data**
* **Never call `Model_Registry::resolve()` on stored data in a migration.** It falls back to the provider default, which replaces custom models that a theme registers later than upgrades run. Migrations only apply a legacy remap to a model that is known at that moment.
* **Never delete an API key you can't decrypt.** Show it as not set; restoring the old salts makes it readable again.

**Checks that tested nothing**
* **Source scans that match comments** fail (or pass) on prose. `bin/contract-check.php` strips comments before scanning.
* **A pattern that can never match** passes forever. When adding a check, make it fail once on purpose before trusting it.

**Local machine**
* The release machine's `/tmp` is shared with other projects and can fill up. Keep test databases and sites under `~/.cache`.
