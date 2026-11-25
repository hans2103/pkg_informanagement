# Ontwikkelworkflow

Repo-indeling, branching, commit-conventies en lokaal bouwen en testen. Voor releases: `docs/Release en deployment procedure.md`.

## Aan de slag (eenmalig per clone)

```bash
git config core.hooksPath .githooks        # hooks: commit-msg (issue-nummer verplicht) en pre-push (bin/ci-local.sh)
echo 'JOOMLA_DIR=/pad/naar/joomla/public_html' > .dev-local   # voor deploy-ddev.sh en setup-dev-links.sh (of zet INFORMANAGEMENT_JOOMLA_DIR)
```

Vereisten: PHP 8.3+, `zip`, `unzip`, `xmllint`, `rsync` en de GitHub CLI (`gh`, voor releases). De scripts werken op macOS (bash 3.2) en Linux.

## Repo-indeling

| Pad | Inhoud |
|---|---|
| `Src/plugins/task/informanagement/` | Taakplugin: de geplande taak *Informanagement-nieuws importeren* (`src/Feed`, `src/Import`, `sql/`, `forms/import.xml`) |
| `Src/plugins/system/informanagement/` | Systeemplugin: `?im_id=`-links met een 301 naar het artikel |
| `Src/modules/mod_inforwijzers/` | Sitemodule: de InforWijzers |
| `Src/language/` | Taalbestanden van het pakket (`pkg_informanagement.sys.ini`) |
| `package.xml`, `package_script.php`, `package.sh`, `.deployignore` | Het pakket `pkg_informanagement` |
| `bin/` | Gedeelde HKweb-tooling, byte-identiek aan `hkweb_adventcalendar`; wat per repo verschilt staat in `scripts.conf` |
| `tests/unit/` | Plain-PHP unit-tests (geen PHPUnit); `tests/bootstrap.php` laadt de klassen |
| `tests/shell/` | Tests voor `bin/` |
| `AIdocs/` | Geverifieerde documentatie voor AI-assistenten |

## Bouwen en testen

```bash
for f in tests/unit/*.php; do php "$f" || echo "FAIL $f"; done
bash tests/shell/run-all.sh
bash bin/preflight.sh
bash package.sh                      # packages/{versie}/pkg_informanagement-{versie}.zip
```

`package.sh` zonder versie stempelt niets. Installeer de zip op een testsite, of zet de extensies met `bash bin/deploy-ddev.sh` in de lokale site (rsync, geen symlinks).

Testen op een DDEV-site (bijv. jacc): installeer de zip, maak een geplande taak *Informanagement-nieuws importeren* met het sjabloon van het kantoor en draai hem:

```bash
ddev exec php public_html/cli/joomla.php scheduler:run --id=<taak-id>
```

Een storing nabootsen: zet `127.0.0.1 nl.informanagement.com` in `/etc/hosts` van de webcontainer (`docker exec -u root ddev-<project>-web …`) en draai de taak een paar keer. De eerste runs geven een waarschuwing en exit code 0, vanaf *Melden na mislukte pogingen* exit code 5.

## Branching model

- `main`: alleen getagde releases.
- `develop`: integratiebranch; elke PR gaat hierheen.
- Feature-branches: `feature/{issue}-{korte-omschrijving}` vanaf `develop`.
- Nooit rechtstreeks committen op `main` of `develop`. Geen PR van `develop` naar `main`: `bin/run-release.sh` merget zelf.

## Commit-conventies

Nederlands, conventional, met issuenummer: `feat(import): #12 omschrijving`. Zonder issue: `#000`. Geen `Co-Authored-By`. Release-commits (`chore(release): …`) en merges zijn uitgezonderd van de hook.

## Lokale CI en draft-PR's

Lokaal = snelle terugkoppeling, CI = bron van waarheid. `bash bin/ci-local.sh` draait vóór een push of `gh pr ready` dezelfde controles als Validate: preflight, PHP-lint over de hele repo, de unit-tests, de shell-tests en een testbuild met `package.sh` (en gitleaks als dat geïnstalleerd is). Wat het draait staat in `scripts.conf` (`CI_*`).

```bash
bash bin/ci-local.sh          # alle controles; bij succes een stempel voor HEAD
bash bin/ci-local.sh --quick  # alleen preflight en de snelle tests; geen stempel
bash bin/ci-local.sh --check  # exit 0 als HEAD een geldige stempel heeft
```

- **Stempel.** Bij succes schrijft het script `<git-common-dir>/ci-local/<commit>`. Die geldt alleen als HEAD die commit is en de werkboom schoon is (ook geen ongevolgde bestanden).
- **pre-push hook** (`.githooks/pre-push`, actief via `core.hooksPath`): zonder stempel voor de gepushte commit draait de hook eerst `bin/ci-local.sh`; faalt dat, dan gaat de push niet door. Tags, verwijderingen en pushes naar `main`/`develop` gaan altijd door. Noodknop: `INFORMANAGEMENT_SKIP_CI_LOCAL=1 git push …` (`${ENV_PREFIX}_SKIP_CI_LOCAL`, met `ENV_PREFIX` uit `scripts.conf`); `git push --no-verify` slaat alle hooks over.
- **Claude-hook** (`.claude/settings.json`, PreToolUse op Bash → `bin/claude-pr-guard.sh`): weigert `gh pr ready` en `gh pr create` zonder `--draft` als HEAD geen geldige stempel heeft. Een draft openen gaat altijd.
- **Draft-PR's draaien geen Actions:** `validate.yml` start bij een PR pas bij *ready for review* (`types: [..., ready_for_review]` en `if: !draft` op de jobs); een nieuwe push breekt de vorige run af (`concurrency`).
- **Werkwijze:** open de PR als draft (`gh pr create --draft --base develop`), draai `bash bin/ci-local.sh` tot het groen is en zet de PR dan met `gh pr ready` op ready for review. Vanuit Claude: draai `bin/ci-local.sh` eerst als eigen commando met een ruime time-out en push daarna.

## Pull requests

Eén PR per issue naar `develop`, als draft geopend; na een groene `bash bin/ci-local.sh` zet `gh pr ready` hem op ready for review (zie [Lokale CI en draft-PR's](#lokale-ci-en-draft-prs)). De validate-workflow draait lint (PHP 8.3 en 8.5), unit- en shell-tests, manifest- en lengtecontroles en een testbuild met controle op de inhoud van de zip.

## Taalbestanden

Elke nieuwe string in `en-GB` én `nl-NL`, in de `language/`-map van de extensie. `tests/unit/LanguageContractTest.php` faalt als een constante uit een manifest of formulier geen string heeft, of als de twee talen andere sleutels hebben.

## AIdocs bijhouden

Wijzig je een bestand dat een AIdocs-pagina dekt, werk die pagina in dezelfde PR bij (`Last updated`, `Verified against`, een `History`-regel). `bash bin/preflight.sh` (controle 14) meldt bestanden zonder pagina.
