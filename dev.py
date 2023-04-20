#!/usr/bin/env python3
import functools
import os
import shlex
import shutil
import subprocess
import sys
import textwrap

import click
import yaml


ADMIN_USER = 'organic'
ADMIN_PASSWORD = 'organic'
DEFAULT_WP_SERVICE = 'wp61-php74'
PHP_VENDOR_DIR = 'src/vendor'
REQUIRED_ENVVARS = [
    'ORGANIC_DEMO_SITE_UUID',
    'ORGANIC_DEMO_SITE_APIKEY',
    'COMPOSER_AUTH',
    'WP49_PHP72_PORT',
    'WP59_PHP74_PORT',
    'WP61_PHP74_PORT',
    'WP61_PHP82_PORT'
]


def info(msg, color='bright_magenta'):
    click.echo()
    click.secho(f"# Organic WP plugin |> {msg}", fg=color)


def empty_dir(path):
    return not os.path.isdir(path) or not os.listdir(path)


class ComposeConfig(dict):
    def get_service_port(self, service):
        port_raw = self['services'][service]['ports'][0].split(':')[0]
        # The YAML has {$SOME_VAR}, but we need the actual value of SOME_VAR from .env.
        return os.environ[port_raw[2:-1]]

    def get_db_creds(self, service):
        db_name = self['services'][service]['environment']['WORDPRESS_DB_NAME']
        db_user = self['x-vars']['db-user']
        db_password = self['x-vars']['db-password']
        return db_name, db_user, db_password

    def get_wp_services(self):
        config = get_compose_config()
        return [s for s in config['services'] if s.startswith('wp')]

    def get_running_wp_services(self):
        return [s for s in ComposeConfig.get_wp_services(self) if _service_is_running(s)]

    def get_wp_version(self, service):
        # WordPress versions are always digit1.digit2.somethingsomething
        return f'{service[2]}.{service[3]}'


@functools.cache
def get_compose_config():
    with open('./docker-compose.yml', 'r') as ymlfile:
        return ComposeConfig(yaml.load(ymlfile, yaml.SafeLoader))


def host_run(run_args, exit_on_nonzero=True, **kwargs):
    if isinstance(run_args, str):
        run_args = shlex.split(run_args)

    result = subprocess.run(run_args, **kwargs)
    if exit_on_nonzero and result.returncode:
        click.secho(f'The {run_args} return non-zero exit code: {result.returncode}', fg='red')
        sys.exit(result.returncode)

    return result


def _service_exec_or_run(service, exec_or_run, cmd, service_env=None, exit_on_nonzero=True, detach=False, **kwargs):
    if detach:
        exec_or_run += ['--detach']

    env = []
    for key, value in (service_env or {}).items():
        env.extend(['--env', f'{key}={value}'])

    if isinstance(cmd, str):
        cmd = shlex.split(cmd)

    run_args = [ 'docker', 'compose', *exec_or_run, *env, service, *cmd]
    return host_run(run_args, exit_on_nonzero, **kwargs)


def service_exec(service, cmd, **kwargs):
    return _service_exec_or_run(service, ['exec', '--no-TTY'], cmd, **kwargs)


def service_run(service, cmd, **kwargs):
    return _service_exec_or_run(service, ['run', '--build', '--no-TTY', '--rm'], cmd, **kwargs)


def _service_is_running(service):
    result = host_run('docker compose ps --services --status running', capture_output=True)
    services = [*filter(None, result.stdout.decode().split('\n'))]
    return service in services


def service_trigger(service, cmd, **kwargs):
    if _service_is_running(service):
        return service_exec(service, cmd, **kwargs)

    return service_run(service, cmd, **kwargs)


def service_restart(service):
    return host_run(f'docker compose restart {service}')


def db_sql(sql, db_user, db_password):
    return service_exec('db', f'mysql -u{db_user}',
        service_env={'MYSQL_PWD': db_password},
        input=sql.encode('utf-8'),
    )


def wp_is_installed(service):
    db_name, db_user, db_password = get_compose_config().get_db_creds(service)
    db_sql(f'CREATE DATABASE IF NOT EXISTS {db_name}', db_user, db_password)

    res = service_exec(service, 'wp --allow-root core is-installed', exit_on_nonzero=False)
    return res.returncode == 0


def npm_deps_are_installed():
    res = service_exec('nodejs', 'npm list --depth 0', exit_on_nonzero=False, capture_output=True)
    return res.returncode == 0


def composer_deps_are_installed():
    return not empty_dir(PHP_VENDOR_DIR)


def configure_organic_plugin_sql(db_name, site_id, api_key):
    return f"""
        INSERT INTO {db_name}.wp_options (option_name,option_value,autoload) VALUES
             ('organic::enabled','1','no'),
             ('organic::percent_test','','no'),
             ('organic::test_value','','no'),
             ('organic::sdk_version','v2','no'),
             ('organic::sdk_key','{api_key}','no'),
             ('organic::site_id','{site_id}','no'),
             ('organic::cmp','','no'),
             ('organic::one_trust_id','','no'),
             ('organic::amp_ads_enabled','1','no'),
             ('organic::ad_slots_prefill_enabled','1','no'),
             ('organic::affiliate_enabled','1','no'),
             ('organic::ads_txt_redirect_enabled','1','no'),
             ('organic::post_types','a:2:{{i:0;s:4:"post";i:1;s:4:"page";}}','no');
     """


def get_env_var(varname):
    value = os.environ.get(varname, '')
    if not value:
        click.secho(f"The {varname} is not passed! Ensure it's defined in `.env` file ", fg='red')
        sys.exit(1)

    return value


def setup_wp_env(config, service):
    """
    Setup WP env with dummy data and active Organic plugin
    """
    site_id = get_env_var('ORGANIC_DEMO_SITE_UUID')
    api_key = get_env_var('ORGANIC_DEMO_SITE_APIKEY')

    port = config.get_service_port(service)
    db_name, db_user, db_password = config.get_db_creds(service)

    db_sql(f'''
      DROP DATABASE IF EXISTS {db_name};
      CREATE DATABASE IF NOT EXISTS {db_name};
    ''', db_user, db_password)

    service_exec(service, (
            f'wp --allow-root core install'
            f' --url=localhost:{port}'
            f' --title="Organic WP Plugin ({service})"'
            f' --admin_user={ADMIN_USER}'
            f' --admin_password={ADMIN_PASSWORD}'
            f' --admin_email=wpplugin@organic.ly'
        ),
    )
    service_exec(service,
        'wp --allow-root theme install /tmp/base-theme.zip --activate --force',
    )
    service_exec(service,
        'wp --allow-root fixtures load --file=/tmp/docker-context/fixtures/data.yml',
    )
    service_exec(service,
        'wp --allow-root plugin activate "wordpress-plugin/organic.php"',
    )
    db_sql(configure_organic_plugin_sql(db_name, site_id, api_key), db_user, db_password)


@click.group()
@click.pass_context
def cli(ctx):
    if not os.path.isfile('.env'):
        click.secho("The `.env` file is not found!", fg='yellow')
        click.pause("Press any key to create it...")
        shutil.copy('.env.template', '.env')
        click.secho("Done! Check the `.env` file, set missing values and re-run command", fg='green')
        ctx.exit()

    for envvar in REQUIRED_ENVVARS:
        get_env_var(envvar)

    ctx.obj = get_compose_config()


@cli.command()
@click.argument('services', nargs=-1, type=click.Choice(get_compose_config().get_wp_services()))
@click.option('--build', is_flag=True, default=False, help="Rebuild images, containers and dependencies")
@click.option('--reset', is_flag=True, default=False, help="Reset DB for Wordpress")
@click.option('--install-amp', is_flag=True, default=False, help="Install WPAMP plugin")
@click.option('--pull-configs', is_flag=True, default=False, help="Pull Ads/Affiliate configs from API")
@click.pass_obj
def up(config, services, build, reset, install_amp, pull_configs):
    if not services:
        services = (DEFAULT_WP_SERVICE,)

    up_cmd = ['docker', 'compose', 'up', '--detach' , '--wait']
    if build:
        up_cmd.append('--build')
        # Different case, but good description of --force-recreate and --renew-anon-volumes
        # https://github.com/docker/compose/issues/8728#issuecomment-939858721
        # Make sure that we "renew" volumes with build artefacts
        # with content from image
        up_cmd.append('--renew-anon-volumes')

    # Recreate containers just to be sure all re-deployed from scratch
    # with latest docker-compose config
    up_cmd.append('--force-recreate')
    # Remove orphaned containers after docker-compose.yml edits
    up_cmd.append('--remove-orphans')
    up_cmd.extend(services)

    info("Building/starting services..")
    host_run(up_cmd)

    if build or not npm_deps_are_installed():
        info("Installing JS dependencies..")
        service_exec('nodejs', 'npm install')
        service_restart('nodejs')

    if build or not composer_deps_are_installed():
        info("Installing PHP dependencies..")
        service_exec('composer', 'composer install')

    for service in services:
        if not wp_is_installed(service) or reset:
            info(f"Setting up WP env for {service}..")
            setup_wp_env(config, service)

        if install_amp:
            info(f"Installing WPAMP plugin..")
            service_exec(service,
                'wp --allow-root plugin install /tmp/wpamp-plugin.zip --activate --force',
            )

        if pull_configs:
            info(f"Pulling configs from API..")
            service_exec(service, 'wp --allow-root organic-sync-ad-config')
            service_exec(service, 'wp --allow-root organic-sync-affiliate-config')

        port = config.get_service_port(service)
        click.secho(textwrap.dedent(f"""

            Done! The URLs for {service}
            Site: http://wpplugin.lcl.organic.ly:{port}
            Admin: http://wpplugin.lcl.organic.ly:{port}/wp-admin (user: {ADMIN_USER}, pass: {ADMIN_PASSWORD})

        """), fg='green')


@cli.command()
@click.option('--nuke', is_flag=True, default=False,
              help="Cleanup all images/volumes/DBs/artifacts/dependencies")
def down(nuke):
    down_cmd = ['docker', 'compose', 'down']
    if nuke:
        down_cmd.extend(['--rmi', 'all'])
        down_cmd.append('--volumes')

    host_run(down_cmd)

    php_deps_deletion_msg = f"Delete PHP dependencies from '{PHP_VENDOR_DIR}'? (may require sudo password)"
    if nuke and click.confirm(php_deps_deletion_msg, default=True):
        host_run(f'sudo rm -rf {PHP_VENDOR_DIR}')
        click.echo(f"PHP dependencies deleted from '{PHP_VENDOR_DIR}'")


@cli.command()
@click.argument('filenames', nargs=-1)
@click.option('--php', is_flag=True, default=False, help="Lint PHP files")
@click.option('--js', is_flag=True, default=False, help="Lint JS files")
def lint(filenames, php, js):
    if not any([php, js]):
        php = js = True

    if php:
        service_trigger('composer', 'composer run lint')

    if js:
        service_trigger('nodejs', 'npm run lint:js')


@cli.command()
@click.argument('services', nargs=-1, type=click.Choice(get_compose_config().get_wp_services()))
@click.option('--exclude', type=click.Choice(['selenium_test']))
@click.pass_obj
def run_tests(config, services, exclude):

    if not _service_is_running("composer"):
        info(
            "The composer service needs to be running in order to run tests. "
            "Have you run the up command?",
            "red",
        )
        sys.exit(1)

    if not services:
        if len(services := config.get_running_wp_services()) > 1:
            info(
                "Multiple WP services are running! "
                "Please specify which service(s) over which to run the tests.",
                "red",
            )
            sys.exit(1)

    for service in services:
        if not _service_is_running(service):
            info(f"Cannot run tests for {service}: {service} is not running.", "red")
            continue
        port = config.get_service_port(service)
        version = config.get_wp_version(service)
        info(f"Running tests for {service} (port {port})")
        cmd = f'/bin/bash -c "export WP_PORT={port} WP_VERSION={version}; composer run phpunit'
        if exclude:
            cmd += f' -- --exclude-group {exclude}'
        cmd += '"'
        service_trigger(
            "composer",
            cmd,
        )


@cli.command()
def build_zip():
    service_run('builder', './build-zip.sh')


if __name__ == '__main__':
    cli()
