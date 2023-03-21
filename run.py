#!/usr/bin/env python3
import click
import subprocess
import yaml

FIXTURES_ENV = 'wp49-php72'

def run(cmd, **kwargs):
    """Run check output with click error handling"""
    try:
        return subprocess.check_output(cmd, **kwargs)
    except subprocess.CalledProcessError as e:
        raise click.ClickException(e)


def service_exec(service, cmd, **kwargs):
    return run(f'docker compose exec -T {service} {cmd}', **kwargs)

def db_sql(sql, db_user, db_password):
    return service_exec(
        'db', f'mysql -u{db_user} -p{db_password}', input=sql.encode('utf-8'), shell=True,
    )

@click.group()
@click.pass_context
def cli(ctx):
    with open('./docker-compose.yml', 'r') as ymlfile:
        ctx.obj = yaml.load(ymlfile, yaml.SafeLoader)


@cli.command()
@click.pass_obj
def setup_fixtures_env(dc):
    """
    Setup env with active Fakerpress plugin

    Allows to generate new set of fixtures for testing and local developement
    After DB is filled with test data use `save_fixtures` command to save slq dump
    """
    service = dc['services'][FIXTURES_ENV]
    port = service['ports'][0].split(':')[0]
    db_password = dc['x-vars']['db-password']
    db_user = dc['x-vars']['db-user']
    db_name = service['environment']['WORDPRESS_DB_NAME']

    db_sql(f'''
      DROP DATABASE IF EXISTS {db_name};
      CREATE DATABASE IF NOT EXISTS {db_name};
    ''', db_user, db_password)

    service_exec(
        FIXTURES_ENV,
        f'wp --allow-root core install'
        f' --url=localhost:{port}'
        f' --title="Organic WP Plugin Demo"'
        f' --admin_user=organic'
        f' --admin_password=organic'
        f' --admin_email=wpplugin@organic.ly',
        shell=True,
    )
    service_exec(FIXTURES_ENV, f'wp --allow-root plugin install fakerpress --activate', shell=True)


@cli.command()
@click.pass_obj
def save_fixtures(dc):
    """Makes fresh backup of DB that will be used as fixtures during tests"""
    db_password = dc['x-vars']['db-password']
    db_user = dc['x-vars']['db-user']
    db_name = dc['services'][FIXTURES_ENV]['environment']['WORDPRESS_DB_NAME']

    service_exec(FIXTURES_ENV, f'wp --allow-root cache flush', shell=True)
    service_exec(FIXTURES_ENV, f'wp --allow-root transient delete --all', shell=True)
    db_sql(f'''
        DELETE FROM {db_name}.wp_options WHERE option_name LIKE "_site_transient%";
        DELETE FROM {db_name}.wp_options WHERE option_name LIKE "_transient%";
        DELETE FROM {db_name}.wp_usermeta WHERE meta_key = "session_tokens";
    ''', db_user, db_password)
    dump_cmd = (
        f'mysqldump --max_allowed_packet=1G --single-transaction '
        f'-u{db_user} -p{db_password} -h db {db_name} '
        f'> ./fixtures/wpdb.sql'
    )
    service_exec('db', dump_cmd, shell=True)


@cli.command()
@click.argument('service_name')
@click.pass_obj
def reset_db_for(dc, service_name):
    service = dc['services'][service_name]
    port = service['ports'][0].split(':')[0]
    theme = service['x-theme']

    db_password = dc['x-vars']['db-password']
    db_user = dc['x-vars']['db-user']
    db_name = service['environment']['WORDPRESS_DB_NAME']

    db_sql(f'''
      DROP DATABASE IF EXISTS {db_name};
      CREATE DATABASE IF NOT EXISTS {db_name};
    ''', db_user, db_password)

    fixture = f'./fixtures/wpdb.sql'
    cmd = (
        f"sed 's/localhost:8031/localhost:{port}/g' {fixture} |"
        f'docker compose exec -T db mysql -u{db_user} -p{db_password} {db_name}'
    )
    run(cmd, shell=True)

    service_exec(service_name, f'wp --allow-root core update-db', shell=True)
    service_exec(service_name, f'wp --allow-root theme activate {theme}', shell=True)


if __name__ == '__main__':
    cli()