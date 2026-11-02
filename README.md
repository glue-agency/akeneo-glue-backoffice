# Glue Backoffice for Akeneo

Reports an Akeneo PIM to the Glue Dashboard after each deploy: the host it is served on, its Akeneo version and edition, its PHP version and the Composer packages it requires. The Akeneo counterpart of `glue-agency/craft-glue-backoffice`, `glue-agency/laravel-glue-backoffice` and `glue-agency/m2-glue-backoffice`.

## Installation

    composer require glue-agency/akeneo-glue-backoffice

Symfony Flex adds the bundle to `config/bundles.php` on its own. Without Flex, or when it didn't, add it there yourself:

```php
GlueAgency\Backoffice\GlueBackofficeBundle::class => ['all' => true],
```

Requires Akeneo 5.0 or later (Symfony 4.4 or later, PHP 7.4 or later, Composer 2) and PHP's curl extension, which Akeneo already requires.

## Configuration

Read from the environment, so from the PIM's `.env` files. Akeneo's own `.env` is overwritten on upgrades: set them in `.env.local`, or in whichever env file the deploy shares between releases.

| Variable | |
|---|---|
| `GLUE_BACKOFFICE_TOKEN` | The install's own reporting token, issued on its page on the Dashboard. |
| `GLUE_BACKOFFICE_URL` | The Dashboard. Defaults to `https://dashboard.glue.be/`; only set it to try a report against another one. |
| `AKENEO_PIM_URL` | Akeneo's own: the address it puts in its links. Its host is what the PIM reports as its name. |

None of them has to be set, and none is read before the command runs: a PIM without them boots as before, and adding the token needs no cache clear.

Only the `prod` environment reports. Set the token on the production host and nowhere else: Akeneo ships with `APP_ENV=prod`, so a staging or dev host that keeps it would report with the token too, and file its versions as live's.

## Reporting

    bin/console glue:backoffice:report --repository-name=git@bitbucket.org:glue-team/project.git

`--repository-name` is optional: without it, the repository is left out of the report.

`--dry-run` prints the report as JSON and sends nothing. It needs no token, keeps no uid and works in every environment, so it is the way to check what a PIM would report on any host, a developer's machine included.

The command reports nothing, says why, and exits cleanly when:

- the PIM runs in any environment but `prod` (`Running in the dev environment, not reporting: only prod reports.`);
- no token is configured (`No Glue Dashboard token configured, not reporting.`).

When the Dashboard refuses the report it prints every message it sent back, which tell the deployer what to do, and exits with 1. So does a Dashboard that can't be reached. Hooked in after `deploy:symlink`, that fails the deploy after the release is already live, as the Craft, Laravel and Magento reporters do.

What is reported:

- the host of `AKENEO_PIM_URL` as the PIM's name, which the Dashboard shows as its *PIM URL*. A PIM still on Akeneo's shipped `http://localhost:8080`, or without a URL, is named after the machine instead;
- Akeneo's version as Composer installed it, without the `v` of its tags (`7.0.88`, `2026.5`), and its edition: `EE` when `akeneo/pim-enterprise-dev` is installed, `CE` otherwise. Not Akeneo's own `version.txt`, which is missing before 7.0 and still read 7.0.85 on 2026.4;
- the PHP version;
- the Composer packages the PIM requires directly — its root `composer.json`'s `require`, not its whole lock file, and not `require-dev` — with the installed version and description; Akeneo itself is among them as `akeneo/pim-community-dev`, and platform requirements (`php`, `ext-*`) are left out;
- the repository, when given.

Every value is cut to 255 characters, the most the Dashboard keeps.

The report carries a uid, a UUID made on the first report and kept in `var/file_storage/glue-backoffice-uid`. Akeneo keeps its catalog's files in `var/file_storage`, so every deploy shares it between releases and the uid stays the same from one deploy to the next. The Dashboard knows the PIM by its token, not by this uid: a PIM on a fresh file storage reports a new uid and its own token accepts it, and a uid that can't be written is no reason to fail a deploy.

### Onboarding a PIM

The Dashboard has to know the Akeneo platform first (the release that adds it, Nov 2026). Before it, the Dashboard refuses the report with `platform: The selected platform is invalid.` and the deploy fails.

1. On the Dashboard, use the install the brand already has: a PIM beside a Craft site or a Magento shop is one install, and all of them report with that install's one token. If it has none yet, add it by hand (platform **Akeneo**, its domain and current version).
2. Switch the install to **automatic** and issue it a reporting token. A token can't be read back once issued: if another platform of the install already reports with it, issue a new one and give it to every platform.
3. On the PIM's production host only, add `GLUE_BACKOFFICE_TOKEN=<token>` to the shared `.env.local`.
4. Require this package, hook the report into the deploy (below) and deploy. The PIM's first report fills in its Akeneo section and its packages.

A PIM that isn't deployed any more can't report: keep it as a manual install on the Dashboard, with its version typed there.

### Deployer

From a Deployer 7 `deploy.php`:

```php
desc('Report to the Glue Dashboard');
task('glue:backoffice_report', function () {
    run('{{bin/php}} {{release_or_current_path}}/bin/console glue:backoffice:report --repository-name={{repository}}');
});

after('deploy:symlink', 'glue:backoffice_report');
```

On Deployer 6, which has no `{{release_or_current_path}}`, run `{{deploy_path}}/current/bin/console` instead.

## Testing

    composer install
    vendor/bin/phpunit
