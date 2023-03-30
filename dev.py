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
]


def empty_dir(path):
    return not os.path.isdir(path) or not os.listdir(path)


class ComposeConfig(dict):
    def get_service_port(self, service):
        return self['services'][service]['ports'][0].split(':')[0]

    def get_db_creds(self, service):
        db_name = self['services'][service]['environment']['WORDPRESS_DB_NAME']
        db_user = self['x-vars']['db-user']
        db_password = self['x-vars']['db-password']
        return db_name, db_user, db_password

    def get_wp_services(self):
        config = get_compose_config()
        return [s for s in config['services'] if s.startswith('wp')]


@functools.cache
def get_compose_config():
    with open('./docker-compose.yml', 'r') as ymlfile:
        return ComposeConfig(yaml.load(ymlfile, yaml.SafeLoader))


def host_run(run_args, exit_on_nonzero=True, **kwargs):
    result = subprocess.run(run_args, **kwargs)
    if exit_on_nonzero and result.returncode:
        click.secho(f'The {run_args} return non-zero exit code: {result.returncode}', fg='red')
        sys.exit(result.returncode)

    return result


def _service_exec_or_run(service, exec_or_run, cmd, service_env=None, exit_on_nonzero=True, **kwargs):
    service_env = service_env or {}

    env = []
    for key, value in service_env.items():
        env.extend(['--env', f'{key}={value}'])

    run_args = [ 'docker', 'compose', *exec_or_run, *env, service, *shlex.split(cmd)]
    return host_run(run_args, exit_on_nonzero, **kwargs)


def service_exec(service, cmd, **kwargs):
    return _service_exec_or_run(service, ['exec', '--no-TTY'], cmd, **kwargs)


def service_run(service, cmd, **kwargs):
    return _service_exec_or_run(service, ['run', '--build', '--no-TTY', '--rm'], cmd, **kwargs)


def db_sql(sql, db_user, db_password):
    return service_exec('db', f'mysql -u{db_user}',
        service_env={'MYSQL_PWD': db_password},
        input=sql.encode('utf-8'),
    )


def wp_is_installed(service):
    db_name, db_user, db_password = get_compose_config().get_db_creds(service)
    db_sql(f'CREATE DATABASE IF NOT EXISTS {db_name}', db_user, db_password)

    res = service_exec(service, 'wp --allow-root core is-installed', exit_on_nonzero=False)
    if res.returncode == 0:
        return True

    return False


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
@click.pass_obj
def up(config, services, build, reset):
    if not services:
        services = (DEFAULT_WP_SERVICE,)

    up_cmd = ['docker', 'compose', 'up', '--detach' , '--wait']
    if build:
        up_cmd.append('--build')
        # Differnt case, but good description of --force-recreate and --renew-anon-volumes
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

    host_run(up_cmd)

    service_run('nodejs', 'npm install')
    if build or empty_dir(PHP_VENDOR_DIR):
        service_run('composer', 'composer install')


    for service in services:
        if not wp_is_installed(service) or reset:
            setup_wp_env(config, service)

        port = config.get_service_port(service)
        click.secho(textwrap.dedent(f"""

            Done! The URLs for {service}
            Site:  http://localhost:{port}
            Admin: http://localhost:{port}/wp-admin/ (user: {ADMIN_USER}, pass: {ADMIN_PASSWORD})

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
        host_run(f'sudo rm -rf {PHP_VENDOR_DIR}'.split())
        click.echo(f"PHP dependencies deleted from '{PHP_VENDOR_DIR}'")


@cli.command()
@click.argument('filenames', nargs=-1)
@click.option('--php', is_flag=True, default=False, help="Lint PHP files")
@click.option('--js', is_flag=True, default=False, help="Lint JS files")
def lint(filenames, php, js):
    if not any([php, js]):
        php = js = True

    if php:
        service_run('composer', 'composer run lint')

    if js:
        service_run('nodejs', 'npm run lint:js')


@cli.command()
def build_zip():
    service_run('builder', './build-zip.sh')


if __name__ == '__main__':
    cli()
