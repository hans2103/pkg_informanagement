# Package (pkg_informanagement)

### History
- 2026-11-25: page created when the package moved from jacc.nl to its own public repo

Last updated: 2026-11-25
Verified against: VERIFIED_SHA
Covers:
- package.xml
- package_script.php
- package.sh
- Src/language/

## Purpose

`pkg_informanagement` installs three extensions in one go: `plg_task_informanagement`, `plg_system_informanagement` and `mod_inforwijzers`. The shared tooling in `bin/` reads the version from `package.xml` and writes `<type>package</type>` entries to `updates.xml`.

## package.xml

- `<version>` is the single source of truth (calendar versioning `YY.WW.PATCH`). Up to 1.0.2 the package lived in jacc.nl with semantic versions; 26.48.0 is the first release from this repo and installs over 1.0.x as an update.
- `<files>`: `plg_task_informanagement.zip` (plugin, group task), `plg_system_informanagement.zip` (plugin, group system), `mod_inforwijzers.zip` (module, site).
- `<blockChildUninstall>`: the extensions can only be uninstalled together, through the package.
- `<languages folder="language">`: `pkg_informanagement.sys.ini` for en-GB and nl-NL.
- `<updateservers>`: `https://raw.githubusercontent.com/hans2103/pkg_informanagement/main/updates.xml`; the zip downloads from the GitHub Release. No `<dlid>`.

## package_script.php

Class `Pkg_InformanagementInstallerScript` (Joomla finds it by the package element; a wrong class name means the script silently does not run).

- `preflight()`: refuses PHP below 8.3.0 and Joomla below 5.2.0 (`PKG_INFORMANAGEMENT_MIN_PHP`, `PKG_INFORMANAGEMENT_MIN_JOOMLA`). Keep these equal to `UPDATE_PHP_MINIMUM` and `UPDATE_TARGETPLATFORM` in `scripts.conf`.
- `postflight()`: on `install` and `discover_install` only, enables the plugins with element `informanagement` in the folders `task` and `system`. An update keeps the site's choice. The module is not placed anywhere: the site adds it in Content → Site Modules.

## package.sh

- `bash package.sh` builds `packages/{version}/pkg_informanagement-{version}.zip` with the version from `package.xml` and stamps nothing.
- `bash package.sh 26.48.1` checks the format (`JJ.WW.N`), stamps `<version>` and `<creationDate>` (Europe/Amsterdam) into `package.xml` and the three extension manifests and replaces `__DEPLOY_VERSION__` in `Src/` and `package_script.php`.
- Each extension folder is zipped with its contents in the zip root, without the patterns in `.deployignore` (also used by `bin/preflight.sh`). A missing folder stops the build.
- The package zip holds the manifest as `pkg_informanagement.xml`, `package_script.php`, `language/{tag}/pkg_informanagement.sys.ini` and the three extension zips.
- `tests/shell/test_package_sh.sh` builds in a copy of the repo and checks all of the above.
